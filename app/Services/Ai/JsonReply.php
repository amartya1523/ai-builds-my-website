<?php

namespace App\Services\Ai;

final class JsonReply
{
    /** Decode a model reply into an array, tolerating ```json fences and stray text around the object. */
    public static function decode(string $text): array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);

        $data = json_decode($text, true);
        if (! is_array($data)) {
            $start = strpos($text, '{');
            $end = strrpos($text, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $data = json_decode(substr($text, $start, $end - $start + 1), true);
            }
        }

        if (! is_array($data)) {
            throw new AiUnavailableException('AI reply was not valid JSON.');
        }

        return $data;
    }
}
