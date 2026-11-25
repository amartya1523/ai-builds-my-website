<?php

namespace App\Services\Build;

use App\Services\Facts\Normalize;

/**
 * Last line of defence for the Golden Rule. After the AI writes copy, every sentence that contains a
 * checkable claim (a number, a year, a phone, an email, a link, or a boast like "best"/"award-winning")
 * must be supported by the accepted facts. Sentences that are not are removed and shown to the owner.
 */
class ClaimGuard
{
    private const BOASTS = [
        'best', 'award', 'awarded', 'award-winning', 'no. 1', 'no 1', 'number one', '#1', 'leading', 'top-rated',
        'top rated', 'renowned', 'famous', 'legendary', 'iconic', 'certified', 'guaranteed', 'guarantee', 'trusted by',
        'since', 'years of experience', 'decades', 'generations', 'family-owned', 'family owned', 'organic', 'authentic',
        'free delivery', 'free home delivery', 'cheapest', 'lowest price', 'luxury', 'world-class', 'world class', 'premium',
        'michelin', 'five-star', '5-star', 'rated',
    ];

    public function __construct(private string $factsBlob)
    {
        $this->factsBlob = Normalize::text($factsBlob);
    }

    /** @return array{text:string, removed:array<int,string>} */
    public function filter(string $text): array
    {
        $removed = [];
        $kept = [];
        foreach (preg_split('/(?<=[.!?])\s+/u', trim($text)) as $sentence) {
            if (trim($sentence) === '') {
                continue;
            }
            if ($this->supported($sentence)) {
                $kept[] = $sentence;
            } else {
                $removed[] = $sentence;
            }
        }

        return ['text' => trim(implode(' ', $kept)), 'removed' => $removed];
    }

    public function supported(string $sentence): bool
    {
        $norm = Normalize::text($sentence);

        preg_match_all('/\d+/', $norm, $nums);
        foreach ($nums[0] as $n) {
            if (! preg_match('/(^|\s)'.preg_quote($n, '/').'(\s|$)/', $this->factsBlob) && ! str_contains($this->factsBlob, $n)) {
                return false;
            }
        }

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}|https?:\/\/|www\./i', $sentence)) {
            return false; // contact details are rendered from facts, never from free text
        }

        foreach (self::BOASTS as $boast) {
            $b = Normalize::text($boast);
            if ($b !== '' && preg_match('/(^|\s)'.preg_quote($b, '/').'(\s|$)/', $norm) && ! str_contains($this->factsBlob, $b)) {
                return false;
            }
        }

        return true;
    }
}
