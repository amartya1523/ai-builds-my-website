<?php

namespace App\Services\Ingest;

use DOMDocument;
use DOMXPath;

/** Pulls the useful, machine-readable bits out of a web page. */
class HtmlExtractor
{
    public const SOCIAL_HOSTS = [
        'instagram.com' => 'Instagram', 'facebook.com' => 'Facebook', 'fb.com' => 'Facebook',
        'x.com' => 'X', 'twitter.com' => 'X', 'linkedin.com' => 'LinkedIn', 'youtube.com' => 'YouTube',
        'wa.me' => 'WhatsApp', 'zomato.com' => 'Zomato', 'swiggy.com' => 'Swiggy', 'justdial.com' => 'Justdial',
    ];

    public function extract(string $html, ?string $baseUrl = null): array
    {
        $out = [
            'title' => null, 'description' => null, 'og' => [], 'jsonld' => [], 'text' => '',
            'phones' => [], 'emails' => [], 'social' => [], 'hidden_text' => '', 'links' => [],
        ];

        if (trim($html) === '') {
            return $out;
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $xp = new DOMXPath($doc);

        $title = $xp->query('//title')->item(0);
        $out['title'] = $title ? $this->clean($title->textContent) : null;

        foreach ($xp->query('//meta[@name or @property]') as $meta) {
            $key = strtolower($meta->getAttribute('property') ?: $meta->getAttribute('name'));
            $content = $this->clean($meta->getAttribute('content'));
            if ($content === '') {
                continue;
            }
            if ($key === 'description') {
                $out['description'] = $content;
            }
            if (str_starts_with($key, 'og:')) {
                $out['og'][substr($key, 3)] = $content;
            }
        }

        foreach ($xp->query('//script[@type="application/ld+json"]') as $script) {
            $data = json_decode(trim($script->textContent), true);
            if (is_array($data)) {
                foreach ($this->flattenJsonLd($data) as $node) {
                    $out['jsonld'][] = $node;
                }
            }
        }

        foreach ($xp->query('//a[@href]') as $a) {
            $href = trim($a->getAttribute('href'));
            if (str_starts_with($href, 'tel:')) {
                $out['phones'][] = urldecode(substr($href, 4));
            } elseif (str_starts_with($href, 'mailto:')) {
                $out['emails'][] = strtok(substr($href, 7), '?');
            } elseif ($label = $this->socialLabel($href)) {
                $out['social'][$href] = $label;
            } elseif ($baseUrl && ($abs = $this->absolute($href, $baseUrl))) {
                $out['links'][$abs] = $this->clean($a->textContent);
            }
        }

        // Text the visitor cannot see is a classic hiding place for instructions aimed at AI tools.
        $hidden = [];
        foreach ($xp->query('//*[@hidden or @aria-hidden="true" or contains(translate(@style," ",""),"display:none") or contains(translate(@style," ",""),"visibility:hidden") or contains(translate(@style," ",""),"font-size:0")]') as $node) {
            $hidden[] = $this->clean($node->textContent);
            $node->parentNode?->removeChild($node);
        }
        $out['hidden_text'] = trim(implode("\n", array_filter($hidden)));

        foreach (['script', 'style', 'noscript', 'svg', 'template', 'iframe'] as $tag) {
            foreach (iterator_to_array($xp->query('//'.$tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $body = $xp->query('//body')->item(0);
        $text = $body ? $this->blockText($body) : '';
        $out['text'] = trim(preg_replace("/\n{3,}/", "\n\n", $text));
        $out['phones'] = array_values(array_unique($out['phones']));
        $out['emails'] = array_values(array_unique(array_filter($out['emails'])));

        return $out;
    }

    /** Resolve a link against the page URL; only same-site http(s) links are returned. */
    public function absolute(string $href, string $base): ?string
    {
        $href = html_entity_decode(trim($href));
        if ($href === '' || str_starts_with($href, '#') || preg_match('#^(javascript|data|whatsapp|sms):#i', $href)) {
            return null;
        }
        $b = parse_url($base);
        if (! isset($b['scheme'], $b['host'])) {
            return null;
        }
        if (str_starts_with($href, '//')) {
            $href = $b['scheme'].':'.$href;
        } elseif (! preg_match('#^https?://#i', $href)) {
            $dir = isset($b['path']) ? preg_replace('#/[^/]*$#', '/', $b['path']) : '/';
            $href = $b['scheme'].'://'.$b['host'].(str_starts_with($href, '/') ? $href : $dir.$href);
        }
        $host = preg_replace('/^www\./', '', strtolower((string) parse_url($href, PHP_URL_HOST)));
        if ($host !== preg_replace('/^www\./', '', strtolower($b['host']))) {
            return null;
        }

        return strtok($href, '#');
    }

    public function socialLabel(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^(www\.|m\.)/', '', $host);
        foreach (self::SOCIAL_HOSTS as $domain => $label) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return $label;
            }
        }

        return null;
    }

    /** Turn JSON-LD (incl. @graph) into a flat list of typed nodes. */
    private function flattenJsonLd(array $data): array
    {
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            return array_merge(...array_map(fn ($n) => is_array($n) ? $this->flattenJsonLd($n) : [], $data['@graph']));
        }
        if (array_is_list($data)) {
            return array_merge([], ...array_map(fn ($n) => is_array($n) ? $this->flattenJsonLd($n) : [], $data));
        }

        return isset($data['@type']) ? [$data] : [];
    }

    private function blockText(\DOMNode $node): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= preg_replace('/\s+/u', ' ', $child->textContent);
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $block = in_array(strtolower($child->nodeName), ['p', 'div', 'li', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'tr', 'section', 'article', 'header', 'footer', 'address', 'td'], true);
                $inner = $this->blockText($child);
                $text .= $block ? "\n".trim($inner)."\n" : $inner;
            }
        }

        return preg_replace("/[ \t]*\n[ \t]*/", "\n", $text);
    }

    private function clean(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
