<?php

return [
    'api_key'      => env('OPENAI_API_KEY'),
    'base_uri'     => env('OPENAI_BASE_URI', 'api.openai.com/v1'),
    'http_client'  => null,
    'request_timeout' => 30,
];
