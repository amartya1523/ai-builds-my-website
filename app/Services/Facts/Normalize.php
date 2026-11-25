<?php

namespace App\Services\Facts;

final class Normalize
{
    /** Compare key used to decide if two values are "the same" (so +91 98290 12345 == 09829012345). */
    public static function key(string $field, string $value): string
    {
        return match ($field) {
            'phone' => self::phoneKey($value),
            'email' => strtolower(trim($value)),
            'website', 'social_link' => rtrim(preg_replace('#^https?://(www\.)?#i', '', strtolower(trim($value))), '/'),
            default => preg_replace('/[^a-z0-9]+/', ' ', strtolower(trim($value))),
        };
    }

    public static function phoneKey(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) > 10 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }

        return ltrim($digits, '0');
    }

    /** Human display format for Indian numbers, leaves anything else as typed. */
    public static function phone(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) === 10) {
            return preg_match('/^[6-9]/', $digits)
                ? '+91 '.substr($digits, 0, 5).' '.substr($digits, 5)
                : '+91 '.substr($digits, 0, 3).' '.substr($digits, 3);
        }

        return trim($value);
    }

    /** Lower-case, no punctuation, single spaces: used for "is this quote really in the source?" */
    public static function text(string $s): string
    {
        $s = mb_strtolower(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $s = str_replace(['₹', 'rs.', 'inr'], ' rs ', $s);

        return trim(preg_replace('/[^\p{L}\p{N}@]+/u', ' ', $s));
    }
}
