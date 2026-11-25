<?php

namespace App\Providers;

use App\Services\Ai\Ai;
use App\Services\Ai\AiClient;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\NullClient;
use App\Services\Ai\OpenAiCompatibleClient;
use App\Services\Ingest\InjectionGuard;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiClient::class, function () {
            $c = config('aiwb');

            return match ($c['provider']) {
                'gemini' => new GeminiClient($c['gemini']['key'], $c['gemini']['model'], $c['timeout']),
                'none' => new NullClient(),
                default => new OpenAiCompatibleClient($c['groq']['key'], $c['groq']['base_url'], $c['groq']['model'], $c['groq']['guard_model'], $c['timeout']),
            };
        });

        $this->app->bind(Ai::class, fn ($app) => new Ai($app->make(AiClient::class), (int) config('aiwb.retries')));
        $this->app->bind(InjectionGuard::class, fn ($app) => new InjectionGuard($app->make(Ai::class), (float) config('aiwb.injection_threshold')));
    }
}
