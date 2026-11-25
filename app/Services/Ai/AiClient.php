<?php

namespace App\Services\Ai;

interface AiClient
{
    public function name(): string;

    public function model(): string;

    public function configured(): bool;

    /**
     * Ask the model for a JSON object.
     *
     * @throws AiUnavailableException
     */
    public function json(string $system, string $user): array;

    /**
     * Probability (0..1) that the text is a prompt-injection attempt, or null if the provider has no classifier.
     */
    public function injectionScore(string $text): ?float;
}
