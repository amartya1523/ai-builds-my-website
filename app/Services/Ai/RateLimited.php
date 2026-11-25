<?php

namespace App\Services\Ai;

/** HTTP 429 from the provider. Carries how long the provider asked us to wait. */
class RateLimited extends AiUnavailableException
{
    public function __construct(string $message, public readonly int $retryAfter = 10)
    {
        parent::__construct($message);
    }
}
