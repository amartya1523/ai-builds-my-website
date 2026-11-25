<?php

namespace App\Services\Ai;

use App\Models\AiCall;
use Throwable;

/**
 * Thin wrapper that adds retries, timing and an audit row (ai_calls) around the configured client.
 */
class Ai
{
    public function __construct(private AiClient $client, private int $retries = 2)
    {
    }

    public function client(): AiClient
    {
        return $this->client;
    }

    public function available(): bool
    {
        return $this->client->configured();
    }

    /** @throws AiUnavailableException */
    public function json(string $purpose, string $system, string $user, ?int $siteId = null, bool $fast = false): array
    {
        $attempt = 0;
        $last = null;

        while ($attempt <= $this->retries) {
            $started = microtime(true);
            try {
                $data = $this->client->json($system, $user, $fast);
                $this->log($siteId, $purpose, true, $started);

                return $data;
            } catch (AiUnavailableException $e) {
                $last = $e;
                $this->log($siteId, $purpose, false, $started, $e->getMessage());
                if (! $this->client->configured()) {
                    break; // retrying will not help
                }
            } catch (Throwable $e) {
                $last = new AiUnavailableException($e->getMessage());
                $this->log($siteId, $purpose, false, $started, $e->getMessage());
            }

            $attempt++;
            if ($attempt <= $this->retries && ! app()->runningUnitTests()) {
                // On a rate limit wait as long as the provider asks (capped), otherwise a short backoff.
                $last instanceof RateLimited
                    ? sleep(min($last->retryAfter + 1, 30))
                    : usleep(400_000 * $attempt);
            }
        }

        throw $last ?? new AiUnavailableException('Unknown AI failure.');
    }

    public function injectionScore(string $text): ?float
    {
        try {
            return $this->client->injectionScore($text);
        } catch (Throwable) {
            return null;
        }
    }

    private function log(?int $siteId, string $purpose, bool $ok, float $started, ?string $error = null): void
    {
        AiCall::create([
            'site_id' => $siteId,
            'purpose' => $purpose,
            'provider' => $this->client->name(),
            'model' => $this->client->model(),
            'ok' => $ok,
            'latency_ms' => (int) ((microtime(true) - $started) * 1000),
            'error' => $error ? mb_substr($error, 0, 500) : null,
        ]);
    }
}
