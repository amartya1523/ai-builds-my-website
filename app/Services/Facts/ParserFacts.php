<?php

namespace App\Services\Facts;

use App\Models\Source;

/**
 * Deterministic facts that need no AI: phone numbers, emails, links, JSON-LD.
 * These still work when the AI is down.
 */
class ParserFacts
{
    private const PHONE = '/(?<![\d])(?:\+91[\s\-]?|0091[\s\-]?|0)?(?:[6-9]\d{4}[\s\-]?\d{5}|\d{2,4}[\s\-]\d{6,8})(?![\d])/';

    private const EMAIL = '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i';

    /** @return array<int, array{field:string, value:string, evidence:string}> */
    public function extract(Source $source): array
    {
        $facts = [];
        $text = (string) $source->content;
        $meta = $source->meta ?? [];

        if (in_array($source->kind, ['website', 'social', 'google_business'], true) && $source->url) {
            $facts[] = [
                'field' => $source->kind === 'website' ? 'website' : 'social_link',
                'value' => $meta['final_url'] ?? $source->url,
                'evidence' => 'Link given by the owner',
            ];
        }

        if ($source->kind === 'google_business' && ! empty($meta['place_name'])) {
            $facts[] = ['field' => 'listing_name', 'value' => $meta['place_name'], 'evidence' => 'Google Maps link: '.$meta['place_name']];
        }

        foreach ($meta['phones'] ?? [] as $phone) {
            if (strlen(Normalize::phoneKey($phone)) >= 8) {
                $facts[] = ['field' => 'phone', 'value' => Normalize::phone($phone), 'evidence' => 'tel: link '.$phone];
            }
        }
        foreach ($meta['emails'] ?? [] as $email) {
            $facts[] = ['field' => 'email', 'value' => strtolower($email), 'evidence' => 'mailto: link '.$email];
        }
        foreach ($meta['social'] ?? [] as $url => $label) {
            $facts[] = ['field' => 'social_link', 'value' => $url, 'evidence' => $label.' link on the website'];
        }

        foreach ($meta['jsonld'] ?? [] as $node) {
            foreach ($this->fromJsonLd($node) as $f) {
                $facts[] = $f;
            }
        }

        if (preg_match_all(self::PHONE, $text, $m)) {
            foreach ($m[0] as $raw) {
                $key = Normalize::phoneKey($raw);
                if (strlen($key) >= 10 && strlen($key) <= 11) {
                    $facts[] = ['field' => 'phone', 'value' => Normalize::phone($raw), 'evidence' => $this->around($text, $raw)];
                }
            }
        }
        if (preg_match_all(self::EMAIL, $text, $m)) {
            foreach ($m[0] as $email) {
                if (! preg_match('/\.(png|jpe?g|gif|webp|svg)$/i', $email) && ! str_contains($email, 'sentry')) {
                    $facts[] = ['field' => 'email', 'value' => strtolower($email), 'evidence' => $this->around($text, $email)];
                }
            }
        }

        // Dedupe by field + normalised value.
        $seen = [];

        return array_values(array_filter($facts, function ($f) use (&$seen) {
            $k = $f['field'].'|'.Normalize::key($f['field'], $f['value']);
            if (isset($seen[$k])) {
                return false;
            }

            return $seen[$k] = true;
        }));
    }

    private function fromJsonLd(array $node): array
    {
        $types = (array) ($node['@type'] ?? []);
        $isBusiness = (bool) array_intersect($types, ['LocalBusiness', 'Organization', 'Restaurant', 'Store', 'CafeOrCoffeeShop', 'Bakery', 'FoodEstablishment', 'HealthAndBeautyBusiness', 'ProfessionalService', 'MedicalBusiness', 'Hotel', 'EducationalOrganization'])
            || collect($types)->contains(fn ($t) => is_string($t) && str_ends_with($t, 'Business'));
        if (! $isBusiness) {
            return [];
        }

        $out = [];
        $ev = fn ($k, $v) => 'Structured data '.$k.': '.(is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE));

        if (! empty($node['telephone']) && is_string($node['telephone'])) {
            $out[] = ['field' => 'phone', 'value' => Normalize::phone($node['telephone']), 'evidence' => $ev('telephone', $node['telephone'])];
        }
        if (! empty($node['email']) && is_string($node['email'])) {
            $out[] = ['field' => 'email', 'value' => strtolower(str_replace('mailto:', '', $node['email'])), 'evidence' => $ev('email', $node['email'])];
        }
        if (! empty($node['address'])) {
            $addr = $node['address'];
            if (is_array($addr)) {
                $addr = implode(', ', array_filter([
                    $addr['streetAddress'] ?? null, $addr['addressLocality'] ?? null,
                    $addr['addressRegion'] ?? null, $addr['postalCode'] ?? null,
                ], fn ($v) => is_string($v) && trim($v) !== ''));
            }
            if (is_string($addr) && trim($addr) !== '') {
                $out[] = ['field' => 'address', 'value' => trim($addr), 'evidence' => $ev('address', $node['address'])];
            }
        }
        if (! empty($node['openingHours'])) {
            $hours = implode('; ', (array) $node['openingHours']);
            $out[] = ['field' => 'hours', 'value' => $hours, 'evidence' => $ev('openingHours', $node['openingHours'])];
        }
        if (! empty($node['priceRange']) && is_string($node['priceRange'])) {
            $out[] = ['field' => 'price_range', 'value' => $node['priceRange'], 'evidence' => $ev('priceRange', $node['priceRange'])];
        }
        foreach ((array) ($node['sameAs'] ?? []) as $url) {
            if (is_string($url)) {
                $out[] = ['field' => 'social_link', 'value' => $url, 'evidence' => $ev('sameAs', $url)];
            }
        }

        return $out;
    }

    private function around(string $text, string $needle): string
    {
        $pos = mb_strpos($text, $needle);
        if ($pos === false) {
            return $needle;
        }
        $start = max(0, $pos - 50);

        return trim(preg_replace('/\s+/', ' ', mb_substr($text, $start, mb_strlen($needle) + 100)));
    }
}
