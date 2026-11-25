<?php

namespace App\Services\Ingest;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches a link the owner gave us. Social networks and Google Maps often block bots; that is
 * reported as "blocked" with a clear next step for the owner instead of failing the whole build.
 */
class LinkReader
{
    private const LOGIN_WALL = [
        'Log in to Instagram', 'Log into Facebook', 'You must log in', 'login_form', 'Sign in to LinkedIn',
        'authwall', 'Join LinkedIn', 'JavaScript is not available', 'Something went wrong, but don’t fret',
        'consent.google.com', 'Before you continue to Google',
    ];

    public function __construct(private HtmlExtractor $html)
    {
    }

    public static function kindFor(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (preg_match('/(^|\.)(maps\.app\.goo\.gl|goo\.gl)$/', $host)
            || (str_contains($host, 'google.') && (str_starts_with($path, '/maps') || str_contains($url, 'g.page')))
            || str_ends_with($host, 'g.page')) {
            return 'google_business';
        }

        return (new HtmlExtractor())->socialLabel($url) ? 'social' : 'website';
    }

    /**
     * @return array{status:string, note:?string, text:?string, meta:array}
     */
    public function read(string $url, string $kind): array
    {
        $meta = ['requested_url' => $url];

        if ($kind === 'google_business') {
            $meta += $this->parseMapsUrl($url);
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => config('aiwb.user_agent'),
                'Accept-Language' => 'en-IN,en;q=0.9',
            ])->timeout(config('aiwb.fetch_timeout'))->get($url);
        } catch (ConnectionException $e) {
            return $this->blockedOrFailed($kind, 'failed', 'Could not connect: '.$this->short($e->getMessage()), $meta);
        } catch (Throwable $e) {
            return $this->blockedOrFailed($kind, 'failed', $this->short($e->getMessage()), $meta);
        }

        $final = (string) ($response->effectiveUri() ?? $url);
        $meta['final_url'] = $final;
        if ($kind === 'google_business') {
            $meta = array_merge($meta, array_filter($this->parseMapsUrl($final)));
        }

        $body = (string) $response->body();
        $status = $response->status();

        if (in_array($status, [401, 403, 429, 999], true) || $status >= 500) {
            return $this->blockedOrFailed($kind, 'blocked', "The site answered HTTP {$status} to our reader.", $meta);
        }
        if ($status >= 400) {
            return ['status' => 'failed', 'note' => "Page not found (HTTP {$status}). Check the link.", 'text' => null, 'meta' => $meta];
        }

        $x = $this->html->extract($body, $final);
        $meta['title'] = $x['title'];
        $meta['jsonld'] = array_slice($x['jsonld'], 0, 5);
        $meta['phones'] = $x['phones'];
        $meta['emails'] = $x['emails'];
        $meta['social'] = $x['social'];
        $meta['og'] = $x['og'];
        $meta['hidden_text'] = $x['hidden_text'];

        $loginWall = collect(self::LOGIN_WALL)->contains(fn ($m) => str_contains($body, $m));
        $useful = trim(($x['og']['title'] ?? '').' '.($x['og']['description'] ?? '').' '.$x['description']);

        if ($kind !== 'website' && ($loginWall || mb_strlen($x['text']) < 200)) {
            // Most social / maps pages still expose a public title + description in meta tags.
            if ($useful !== '' && ! $loginWall) {
                return ['status' => 'ok', 'note' => 'Only the public preview (title/description) was readable.', 'text' => $this->previewText($x), 'meta' => $meta];
            }
            if ($useful !== '' && $kind === 'social') {
                return ['status' => 'blocked', 'note' => $this->blockedNote($kind).' We kept the public preview only.', 'text' => $this->previewText($x), 'meta' => $meta];
            }

            return $this->blockedOrFailed($kind, 'blocked', 'The page needs a login or JavaScript.', $meta, $this->previewText($x));
        }

        $text = $this->previewText($x)."\n\n".$x['text'];

        if ($kind === 'website') {
            foreach ($this->subPages($x['links'], $final) as $pageUrl) {
                $page = $this->fetchPage($pageUrl);
                if (! $page) {
                    continue;
                }
                $meta['pages'][] = $pageUrl;
                $meta['phones'] = array_values(array_unique(array_merge($meta['phones'], $page['phones'])));
                $meta['emails'] = array_values(array_unique(array_merge($meta['emails'], $page['emails'])));
                $meta['social'] = $meta['social'] + $page['social'];
                $meta['jsonld'] = array_slice(array_merge($meta['jsonld'], $page['jsonld']), 0, 8);
                if ($page['hidden_text'] !== '') {
                    $meta['hidden_text'] = trim($meta['hidden_text']."\n".$page['hidden_text']);
                }
                $text .= "\n\n--- Page: {$pageUrl}\n".mb_substr($page['text'], 0, 5000);
            }
        }

        foreach ($x['jsonld'] as $node) {
            $text .= "\n\nStructured data: ".json_encode($this->compactJsonLd($node), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return ['status' => 'ok', 'note' => null, 'text' => trim($text), 'meta' => $meta];
    }

    /** Pick up to 4 pages on the same site that usually hold facts (contact, about, menu, prices…). */
    private function subPages(array $links, string $home): array
    {
        $pattern = '/contact|about|menu|service|price|pricing|tariff|location|visit|hours|our-story|treatments|products|reach/i';
        $picked = [];
        foreach ($links as $url => $text) {
            if (rtrim($url, '/') === rtrim($home, '/') || preg_match('/login|register|account|cart|checkout|privacy|policy|terms|refund|wp-json|feed|\.(pdf|jpg|png|zip)$/i', $url)) {
                continue;
            }
            if (preg_match($pattern, $url) || preg_match($pattern, (string) $text)) {
                $picked[$url] = true;
            }
            if (count($picked) >= 4) {
                break;
            }
        }

        return array_keys($picked);
    }

    private function fetchPage(string $url): ?array
    {
        try {
            $r = Http::withHeaders(['User-Agent' => config('aiwb.user_agent')])->timeout(config('aiwb.fetch_timeout'))->get($url);
        } catch (Throwable) {
            return null;
        }
        if (! $r->successful()) {
            return null;
        }

        return $this->html->extract((string) $r->body(), $url);
    }

    /** /maps/place/Laxmi+Mishthan+Bhandar/@26.92,75.82 -> name + coordinates, readable even when the page is not. */
    public function parseMapsUrl(string $url): array
    {
        $out = [];
        if (preg_match('#/maps/place/([^/@?]+)#', $url, $m)) {
            $out['place_name'] = trim(urldecode(str_replace('+', ' ', $m[1])));
        }
        if (preg_match('#@(-?\d+\.\d+),(-?\d+\.\d+)#', $url, $m)) {
            $out['lat'] = (float) $m[1];
            $out['lng'] = (float) $m[2];
        } elseif (preg_match('#!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)#', $url, $m)) {
            $out['lat'] = (float) $m[1];
            $out['lng'] = (float) $m[2];
        }

        return $out;
    }

    private function previewText(array $x): string
    {
        return trim(implode("\n", array_filter([
            $x['title'] ? 'Page title: '.$x['title'] : null,
            ($x['og']['title'] ?? null) ? 'Preview title: '.$x['og']['title'] : null,
            $x['description'] ? 'Description: '.$x['description'] : null,
            (($x['og']['description'] ?? null) && ($x['og']['description'] ?? null) !== $x['description']) ? 'Preview description: '.$x['og']['description'] : null,
        ])));
    }

    private function blockedOrFailed(string $kind, string $status, string $why, array $meta, ?string $text = null): array
    {
        $note = $kind === 'website' ? $why : $this->blockedNote($kind).' ('.$why.')';
        if ($kind === 'google_business' && ! empty($meta['place_name'])) {
            $note .= ' We still read the place name from the link.';
            $text = trim(($text ?? '')."\nGoogle listing name: ".$meta['place_name']);
        }

        return ['status' => $kind === 'website' ? $status : 'blocked', 'note' => $note, 'text' => $text ?: null, 'meta' => $meta];
    }

    private function blockedNote(string $kind): string
    {
        return match ($kind) {
            'social' => 'This social network blocks automated reading. The link is kept for your site; paste your bio or key details as a note if you want them used.',
            'google_business' => 'Google Maps does not allow automated reading of listings. Paste your address, phone and hours as a note if they are not in your other sources.',
            default => 'This page could not be read.',
        };
    }

    private function compactJsonLd(array $node): array
    {
        $keep = ['@type', 'name', 'description', 'telephone', 'email', 'address', 'openingHours', 'openingHoursSpecification', 'priceRange', 'servesCuisine', 'url', 'sameAs', 'foundingDate', 'slogan', 'areaServed', 'hasMenu'];

        return array_intersect_key($node, array_flip($keep));
    }

    private function short(string $s): string
    {
        return mb_substr($s, 0, 160);
    }
}
