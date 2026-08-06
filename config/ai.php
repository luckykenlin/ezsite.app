<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider Names
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the AI providers below should be the
    | default for AI operations when no explicit provider is provided
    | for the operation. This should be any provider defined below.
    |
    */

    'default' => env('AI_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Text Failover Chain
    |--------------------------------------------------------------------------
    |
    | The providers a text agent tries, in order. With no fallback configured
    | this is a one-entry list and behaves exactly like calling the default
    | provider directly. Failover only happens when a provider refuses to
    | START answering (down, rate-limited, bad key) — a stream that already
    | emitted output is never silently switched mid-reply.
    |
    */

    'failover' => array_values(array_filter([
        env('AI_PROVIDER', 'openai'),
        env('AI_FAILOVER_PROVIDER'),
    ])),

    /*
    |--------------------------------------------------------------------------
    | Vision Chain
    |--------------------------------------------------------------------------
    |
    | The providers used for chat turns that involve attachments — images or
    | documents the operator sent, in the current message or anywhere in the
    | agent's conversation window. The default text provider (DeepSeek) cannot
    | read documents at all (its gateway throws on Document attachments), so
    | such turns are routed here instead. See ChatEditPage::ask().
    |
    */

    'vision' => array_filter([
        env('AI_VISION_PROVIDER', 'gemini'),
    ]),

    'default_for_images' => 'gemini',
    'default_for_audio' => 'openai',
    'default_for_transcription' => 'openai',
    'default_for_embeddings' => 'openai',
    'default_for_reranking' => 'cohere',

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Below you may configure caching strategies for AI related operations
    | such as embedding generation. You are free to adjust these values
    | based on your application's available caching stores and needs.
    |
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Below are each of your AI providers defined for this application. Each
    | represents an AI provider and API key combination which can be used
    | to perform tasks like text, image, and audio creation via agents.
    |
    */

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        ],

        'azure' => [
            'driver' => 'azure',
            'key' => env('AZURE_OPENAI_API_KEY'),
            'url' => env('AZURE_OPENAI_URL'),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2025-04-01-preview'),
            'deployment' => env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o'),
            'embedding_deployment' => env('AZURE_OPENAI_EMBEDDING_DEPLOYMENT', 'text-embedding-3-small'),
            'image_deployment' => env('AZURE_OPENAI_IMAGE_DEPLOYMENT', 'gpt-image-1'),
            'store' => env('AZURE_OPENAI_STORE', true),
        ],

        'bedrock' => [
            'driver' => 'bedrock',
            'region' => env('AWS_BEDROCK_REGION', 'us-east-1'),
            'key' => env('AWS_BEARER_TOKEN_BEDROCK'),
            'access_key_id' => env('AWS_ACCESS_KEY_ID'),
            'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
            'session_token' => env('AWS_SESSION_TOKEN'),
            'use_default_credential_provider' => env('AWS_USE_DEFAULT_CREDENTIALS', true),
        ],

        'cohere' => [
            'driver' => 'cohere',
            'key' => env('COHERE_API_KEY'),
        ],

        'deepseek' => [
            'driver' => 'deepseek',
            'key' => env('DEEPSEEK_API_KEY'),
            // The SDK's built-in defaults (deepseek-chat/-reasoner) were
            // retired by DeepSeek; only the v4 model names are accepted now.
            'models' => [
                'text' => [
                    'default' => env('DEEPSEEK_MODEL', 'deepseek-v4-pro'),
                    'cheapest' => 'deepseek-v4-flash',
                    'smartest' => 'deepseek-v4-pro',
                ],
            ],
        ],

        'eleven' => [
            'driver' => 'eleven',
            'key' => env('ELEVENLABS_API_KEY'),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
            'url' => env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta/'),
            // Pinned so #[UseCheapestModel] does not resolve to the SDK's
            // flash-lite tier: vision turns carry the full 13-tool roster, and
            // the lite models fumble exact tool arguments (block keys, media
            // ids) often enough to burn the operator's turn.
            'models' => [
                'text' => [
                    'default' => env('GEMINI_CHAT_MODEL', 'gemini-3.5-flash'),
                    'cheapest' => env('GEMINI_CHAT_MODEL', 'gemini-3.5-flash'),
                    'smartest' => 'gemini-3.5-flash',
                ],
            ],
        ],

        'groq' => [
            'driver' => 'groq',
            'key' => env('GROQ_API_KEY'),
        ],

        'jina' => [
            'driver' => 'jina',
            'key' => env('JINA_API_KEY'),
        ],

        'mistral' => [
            'driver' => 'mistral',
            'key' => env('MISTRAL_API_KEY'),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        ],

        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            'store' => env('OPENAI_STORE', true),
        ],

        'openai-compatible' => [
            'driver' => 'openai-compatible',
            'url' => env('OPENAI_COMPATIBLE_URL'),
            'key' => env('OPENAI_COMPATIBLE_API_KEY'),
        ],

        'openrouter' => [
            'driver' => 'openrouter',
            'key' => env('OPENROUTER_API_KEY'),
        ],

        'voyageai' => [
            'driver' => 'voyageai',
            'key' => env('VOYAGEAI_API_KEY'),
        ],

        'xai' => [
            'driver' => 'xai',
            'key' => env('XAI_API_KEY'),
        ],
    ],

];
