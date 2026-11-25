<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI provider
    |--------------------------------------------------------------------------
    | "groq"   -> any OpenAI-compatible chat API (Groq by default)
    | "gemini" -> Google Gemini generateContent API
    | "none"   -> no AI; the builder uses the deterministic template writer
    */
    'provider' => env('AI_PROVIDER', 'groq'),

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
        // Small classifier model used to score uploaded text for prompt injection.
        'guard_model' => env('GROQ_GUARD_MODEL', 'meta-llama/llama-prompt-guard-2-86m'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    'timeout' => (int) env('AI_TIMEOUT', 45),
    'retries' => (int) env('AI_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | Fetching and search
    |--------------------------------------------------------------------------
    */
    'user_agent' => env('FETCH_USER_AGENT', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36'),
    'fetch_timeout' => (int) env('FETCH_TIMEOUT', 12),
    'search_enabled' => (bool) env('WEB_SEARCH_ENABLED', true),
    'nominatim_url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org/search'),
    'search_url' => env('SEARCH_URL', 'https://html.duckduckgo.com/html/'),

    // Max characters of text we keep per source (keeps prompts small).
    'max_source_chars' => 12000,

    // Score above which a chunk of uploaded text is treated as an injection attempt.
    'injection_threshold' => 0.8,
];
