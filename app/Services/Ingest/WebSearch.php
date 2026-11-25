<?php

namespace App\Services\Ingest;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Free, key-less lookups:
 *  - OpenStreetMap Nominatim to find real places with this name in this city (disambiguation)
 *  - DuckDuckGo's HTML endpoint for general web mentions
 * Everything found here is treated as UNVERIFIED until the owner confirms it.
 */
class WebSearch
{
    /** @return array{status:string, note:?string, candidates:array} */
    public function places(string $name, ?string $city): array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'AIBuildsMyWebsite/1.0 (local demo)'])
                ->timeout(config('aiwb.fetch_timeout'))
                ->get(config('aiwb.nominatim_url'), [
                    'q' => trim($name.', '.$city, ', '),
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'extratags' => 1,
                    'limit' => 8,
                ]);
        } catch (Throwable $e) {
            return ['status' => 'failed', 'note' => 'Map search unavailable right now.', 'candidates' => []];
        }

        if (! $response->successful()) {
            return ['status' => 'failed', 'note' => 'Map search answered HTTP '.$response->status().'.', 'candidates' => []];
        }

        $candidates = [];
        foreach ((array) $response->json() as $place) {
            $placeName = $place['name'] ?? '';
            if ($placeName === '' || ! $this->similar($name, $placeName)) {
                continue;
            }
            $key = strtolower($place['display_name'] ?? '');
            $candidates[$key] = [
                'name' => $placeName,
                'address' => $place['display_name'] ?? '',
                'category' => str_replace('_', ' ', (string) ($place['type'] ?? '')),
                'lat' => (float) ($place['lat'] ?? 0),
                'lng' => (float) ($place['lon'] ?? 0),
                'phone' => $place['extratags']['phone'] ?? $place['extratags']['contact:phone'] ?? null,
                'website' => $place['extratags']['website'] ?? $place['extratags']['contact:website'] ?? null,
                'opening_hours' => $place['extratags']['opening_hours'] ?? null,
                'osm_url' => isset($place['osm_type'], $place['osm_id']) ? 'https://www.openstreetmap.org/'.$place['osm_type'].'/'.$place['osm_id'] : null,
            ];
        }

        return ['status' => 'ok', 'note' => null, 'candidates' => array_values($candidates)];
    }

    /** @return array{status:string, note:?string, results:array} */
    public function web(string $query): array
    {
        try {
            $response = Http::asForm()
                ->withHeaders(['User-Agent' => config('aiwb.user_agent')])
                ->timeout(config('aiwb.fetch_timeout'))
                ->post(config('aiwb.search_url'), ['q' => $query, 'kl' => 'in-en']);
        } catch (Throwable) {
            return ['status' => 'failed', 'note' => 'Web search unavailable right now.', 'results' => []];
        }

        if (! $response->successful() || str_contains($response->body(), 'anomaly-modal')) {
            return ['status' => 'blocked', 'note' => 'The search engine rate-limited us; continuing with your own sources.', 'results' => []];
        }

        $results = [];
        if (preg_match_all('#<a[^>]+class="result__a"[^>]+href="([^"]+)"[^>]*>(.*?)</a>.*?class="result__snippet"[^>]*>(.*?)</a>#s', $response->body(), $m, PREG_SET_ORDER)) {
            foreach (array_slice($m, 0, 8) as $hit) {
                $url = html_entity_decode($hit[1]);
                if (preg_match('/[?&]uddg=([^&]+)/', $url, $u)) {
                    $url = urldecode($u[1]);
                }
                if (str_contains($url, 'duckduckgo.com/y.js')) {
                    continue; // ads
                }
                $results[] = [
                    'title' => $this->strip($hit[2]),
                    'url' => $url,
                    'snippet' => $this->strip($hit[3]),
                ];
            }
        }

        return ['status' => 'ok', 'note' => $results ? null : 'No web results found.', 'results' => $results];
    }

    public function similar(string $a, string $b): bool
    {
        $norm = fn ($s) => preg_replace('/[^a-z0-9 ]/', '', strtolower($s));
        $a = $norm($a);
        $b = $norm($b);
        if ($a === '' || $b === '') {
            return false;
        }
        if (str_contains($a, $b) || str_contains($b, $a)) {
            return true;
        }
        similar_text($a, $b, $pct);

        return $pct >= 70;
    }

    private function strip(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
