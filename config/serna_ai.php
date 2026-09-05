<?php

return [
    'enabled' => (bool) env('SERNA_AI_ENABLED', true),
    'api_key' => env('OPENAI_API_KEY', env('SERNA_AI_API_KEY')),
    'base_url' => rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    'timeout' => (int) env('SERNA_AI_TIMEOUT', 45),
];
