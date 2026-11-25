<?php

namespace App\Services\Ai;

/** Used when AI_PROVIDER=none. Every call fails, so the app always takes the template path. */
class NullClient implements AiClient
{
    public function name(): string
    {
        return 'none';
    }

    public function model(): string
    {
        return '-';
    }

    public function configured(): bool
    {
        return false;
    }

    public function json(string $system, string $user): array
    {
        throw new AiUnavailableException('AI is turned off (AI_PROVIDER=none).');
    }

    public function injectionScore(string $text): ?float
    {
        return null;
    }
}
