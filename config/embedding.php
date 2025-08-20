<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Embedding API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the external embedding API service.
    | This service is used to generate vector embeddings for semantic search.
    |
    */

    'api_url' => env('EMBEDDING_API_URL', 'https://openapi.test/api/ai/embeddings'),

    /*
    |--------------------------------------------------------------------------
    | Default Provider and Model
    |--------------------------------------------------------------------------
    |
    | The default AI provider and model to use for embedding generation.
    | These can be overridden per request if needed.
    |
    */

    'default_provider' => env('EMBEDDING_PROVIDER', 'openai'),
    'default_model' => env('EMBEDDING_MODEL', 'text-embedding-3-small'),

    /*
    |--------------------------------------------------------------------------
    | Request Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for HTTP requests to the embedding API.
    |
    */

    'timeout' => env('EMBEDDING_TIMEOUT', 30), // seconds
    'retry_attempts' => env('EMBEDDING_RETRY_ATTEMPTS', 3),
    'retry_delay' => env('EMBEDDING_RETRY_DELAY', 1000), // milliseconds

    /*
    |--------------------------------------------------------------------------
    | Batch Processing
    |--------------------------------------------------------------------------
    |
    | Configuration for batch processing of embeddings.
    |
    */

    'batch_size' => env('EMBEDDING_BATCH_SIZE', 50),
    'batch_delay' => env('EMBEDDING_BATCH_DELAY', 100), // milliseconds between requests
    'rate_limit_delay' => env('EMBEDDING_RATE_LIMIT_DELAY', 100000), // microseconds for large batches

    /*
    |--------------------------------------------------------------------------
    | Vector Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for vector embeddings.
    |
    */

    'vector_dimensions' => env('EMBEDDING_VECTOR_DIMENSIONS', 1536), // text-embedding-3-small dimensions
    
    /*
    |--------------------------------------------------------------------------
    | Supported Providers and Models
    |--------------------------------------------------------------------------
    |
    | List of supported providers and their models for validation.
    |
    */

    'supported_providers' => [
        'openai' => [
            'text-embedding-3-small',
            'text-embedding-3-large',
            'text-embedding-ada-002'
        ],
        'anthropic' => [
            // Add anthropic models if supported by your API
        ],
        'mistral' => [
            // Add mistral models if supported by your API
        ]
    ],
];