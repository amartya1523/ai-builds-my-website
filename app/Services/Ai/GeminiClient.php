<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class GeminiClient implements AiClient
{
    public function __construct(private ?string $key, private string $model, private int $timeout = 45)
    {
    }

    public function name(): string
    {
        return 'gemini';
    }

    public function model(): string
    {
        return $this->model;
    }

    public function configured(): bool
    {
        return filled($this->key);
    }

    public function json(string $system, string $user, bool $fast = false): array
    {
        if (! $this->configured()) {
            throw new AiUnavailableException('No API key configured.');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->key])
                ->timeout($this->timeout)
                ->post($url, [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
                    'generationConfig' => ['temperature' => 0.2, 'responseMimeType' => 'application/json'],
                ]);
        } catch (ConnectionException $e) {
            throw new AiUnavailableException('Could not reach Gemini: '.$e->getMessage());
        }

        if ($response->status() === 429) {
            throw new RateLimited('Gemini rate limit reached.', 20);
        }
        if (! $response->successful()) {
            throw new AiUnavailableException('Gemini returned HTTP '.$response->status().'.');
        }

        return JsonReply::decode((string) data_get($response->json(), 'candidates.0.content.parts.0.text', ''));
    }

    public function injectionScore(string $text): ?float
    {
        return null; // Gemini has no separate classifier; the heuristic guard still runs.
    }
}
