<?php

namespace App\Services\Ai;

use RuntimeException;

/** Thrown when the AI provider is missing, down, rate-limited or returns something we cannot parse. */
class AiUnavailableException extends RuntimeException
{
}
