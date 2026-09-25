<?php

return [

    /** |-------------------------------------------------------------------------- | Third Party Services... */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'ai_feedback_driver' => env('AI_FEEDBACK_DRIVER', 'mock'),

    'ai_coach_driver' => env('AI_COACH_DRIVER', 'mock'),

    'ai_question_generator_driver' => env('AI_QUESTION_GENERATOR_DRIVER', 'mock'),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        // "-latest" aliases track Google's current models, so a retired model id cannot disable AI features.
        'model' => env('GEMINI_MODEL', 'gemini-flash-latest'),
        'translation_model' => env('GEMINI_TRANSLATION_MODEL', 'gemini-flash-latest'),
    ],

    'ml_service' => [
        'url' => env('ML_SERVICE_URL', 'http://127.0.0.1:8100'),
    ],

];
