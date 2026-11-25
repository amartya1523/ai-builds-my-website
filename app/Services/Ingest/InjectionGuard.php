<?php

namespace App\Services\Ingest;

use App\Services\Ai\Ai;

/**
 * Finds instructions hidden in uploaded files or pages ("ignore previous instructions…") and removes them
 * before any text reaches the model. Two layers:
 *  1. fast heuristics (always on, also when AI is down)
 *  2. a small prompt-injection classifier (Llama Prompt Guard 2 on Groq) when available
 */
class InjectionGuard
{
    private const PATTERNS = [
        '/\b(ignore|disregard|forget|override)\b.{0,40}\b(previous|prior|above|earlier|all|any|system|your)\b.{0,30}\b(instruction|instructions|prompt|prompts|rules|directions)\b/i',
        '/\byou\s+are\s+now\b/i',
        '/\b(system|developer)\s*(prompt|message|instruction)s?\b/i',
        '/\bnew\s+instructions?\s*:/i',
        '/\b(as an ai|as a language model|ai assistant|dear ai|note to (the )?ai|to the ai|ai model)\b/i',
        '/\b(do not|don\'t|never)\s+(tell|inform|mention|show)\b.{0,30}\b(user|owner|customer)s?\b/i',
        '/^\s*(assistant|system)\s*:/im',
        '/<\|?(im_start|system|endoftext)\|?>/i',
        '/\b(reveal|print|output)\b.{0,30}\b(api key|secret|password|prompt)\b/i',
        '/\b(write|say|state|claim)\s+that\b.{0,60}\b(best|award|free|guarantee|#?1|number one|rated)\b/i',
    ];

    public function __construct(private Ai $ai, private float $threshold = 0.8)
    {
    }

    /**
     * @return array{clean:string, flagged:bool, score:?float, removed:array<int,string>}
     */
    public function scan(string $text): array
    {
        $removed = [];
        $kept = [];
        $maxScore = null;

        foreach ($this->chunks($text) as $chunk) {
            if (trim($chunk) === '') {
                $kept[] = $chunk;

                continue;
            }
            if ($this->heuristicHit($chunk)) {
                $removed[] = trim($chunk);

                continue;
            }
            $kept[] = $chunk;
        }

        // Second opinion from the classifier on the remaining text (a few calls at most).
        $classifierChunks = array_slice(array_values(array_filter(
            $this->windows(implode("\n", $kept)), fn ($c) => mb_strlen(trim($c)) > 20
        )), 0, 6);

        foreach ($classifierChunks as $window) {
            $score = $this->ai->injectionScore($window);
            if ($score === null) {
                break; // classifier not available; heuristics already ran
            }
            $maxScore = max($maxScore ?? 0, $score);
            if ($score >= $this->threshold) {
                foreach ($this->chunks($window) as $line) {
                    if (trim($line) !== '' && ($this->ai->injectionScore($line) ?? 0) >= $this->threshold) {
                        $removed[] = trim($line);
                        $kept = array_values(array_filter($kept, fn ($k) => trim($k) !== trim($line)));
                    }
                }
            }
        }

        return [
            'clean' => trim(implode("\n", $kept)),
            'flagged' => $removed !== [],
            'score' => $maxScore,
            'removed' => array_values(array_unique($removed)),
        ];
    }

    public function heuristicHit(string $text): bool
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /** Split into lines, then long lines into sentences, so one bad sentence doesn't cost a whole page. */
    private function chunks(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/', $text) as $line) {
            if (mb_strlen($line) <= 300) {
                $out[] = $line;

                continue;
            }
            foreach (preg_split('/(?<=[.!?])\s+/', $line) as $sentence) {
                $out[] = $sentence;
            }
        }

        return $out;
    }

    private function windows(string $text, int $size = 1500): array
    {
        $windows = [];
        $current = '';
        foreach (preg_split('/\R/', $text) as $line) {
            if (mb_strlen($current) + mb_strlen($line) > $size && $current !== '') {
                $windows[] = $current;
                $current = '';
            }
            $current .= $line."\n";
        }
        if (trim($current) !== '') {
            $windows[] = $current;
        }

        return $windows;
    }
}
