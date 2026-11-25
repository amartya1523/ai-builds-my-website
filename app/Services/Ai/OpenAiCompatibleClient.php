<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Works with Groq (default) and any other OpenAI-compatible chat completions API. */
class OpenAiCompatibleClient implements AiClient
{
    public function __construct(
        private ?string $key,
        private string $baseUrl,
        private string $model,
        private ?string $guardModel = null,
        private int $timeout = 45,
    ) {
    }

    public function name(): string
    {
        return 'groq';
    }

    public function model(): string
    {
        return $this->model;
    }

    public function configured(): bool
    {
        return filled($this->key);
    }

    public function json(string $system, string $user): array
    {
        if (! $this->configured()) {
            throw new AiUnavailableException('No API key configured.');
        }

        try {
            $response = Http::withToken($this->key)
                ->timeout($this->timeout)
                ->acceptJson()
                ->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                    'model' => $this->model,
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new AiUnavailableException('Could not reach the AI provider: '.$e->getMessage());
        }

        if (! $response->successful()) {
            throw new AiUnavailableException('AI provider returned HTTP '.$response->status().'.');
        }

        $text = (string) data_get($response->json(), 'choices.0.message.content', '');

        return JsonReply::decode($text);
    }

    public function injectionScore(string $text): ?float
    {
        if (! $this->configured() || ! $this->guardModel) {
            return null;
        }

        try {
            $response = Http::withToken($this->key)->timeout(15)
                ->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                    'model' => $this->guardModel,
                    'messages' => [['role' => 'user', 'content' => mb_substr($text, 0, 2000)]],
                ]);
        } catch (ConnectionException) {
            return null;
        }

        $value = data_get($response->json(), 'choices.0.message.content');

        return is_numeric($value) ? (float) $value : null;
    }
}
