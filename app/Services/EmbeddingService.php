<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class EmbeddingService
{
    protected string $provider;
    protected string $model;
    protected string $apiUrl;

    public function __construct(
        ?string $provider = null,
        ?string $model = null
    ) {
        $this->provider = strtolower($provider ?? config('embedding.default_provider', 'openai'));
        $this->model = $model ?? config('embedding.default_model', 'text-embedding-3-small');
        $this->apiUrl = config('embedding.api_url', 'https://openapi.test/api/ai/embeddings');
    }

    /**
     * Generate embedding for given text
     */
    public function getEmbedding(string $text): ?array
    {
        if (empty(trim($text))) {
            Log::warning('Empty text provided to embedding service');
            return null;
        }

        $retryAttempts = config('embedding.retry_attempts', 3);
        $retryDelay = config('embedding.retry_delay', 1000);
        $timeout = config('embedding.timeout', 30);

        for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
            try {
                $response = Http::timeout($timeout)
                    ->post($this->apiUrl, [
                        'provider' => $this->provider,
                        'model' => $this->model,
                        'text' => $text
                    ]);

                if (!$response->successful()) {
                    Log::warning("Embedding API request failed (attempt {$attempt}/{$retryAttempts})", [
                        'status' => $response->status(),
                        'response' => $response->body(),
                        'text_length' => strlen($text),
                        'provider' => $this->provider,
                        'model' => $this->model
                    ]);

                    if ($attempt < $retryAttempts) {
                        usleep($retryDelay * 1000); // Convert ms to microseconds
                        continue;
                    }
                    
                    return null;
                }

                $embeddingData = $response->json();
                
                // The API returns the embedding array directly
                if (is_array($embeddingData) && !empty($embeddingData)) {
                    Log::debug('Embedding generated successfully', [
                        'text_length' => strlen($text),
                        'embedding_dimensions' => count($embeddingData),
                        'attempt' => $attempt
                    ]);
                    return $embeddingData;
                }

                Log::error('Invalid embedding response format', [
                    'response' => $embeddingData,
                    'text_length' => strlen($text),
                    'attempt' => $attempt
                ]);
                
                return null;
            } catch (\Exception $e) {
                Log::warning("Embedding generation attempt {$attempt} failed", [
                    'error' => $e->getMessage(),
                    'text_length' => strlen($text),
                    'provider' => $this->provider,
                    'model' => $this->model,
                    'api_url' => $this->apiUrl
                ]);

                if ($attempt < $retryAttempts) {
                    usleep($retryDelay * 1000);
                    continue;
                }

                Log::error('All embedding generation attempts failed', [
                    'error' => $e->getMessage(),
                    'attempts' => $retryAttempts,
                    'text_length' => strlen($text)
                ]);
                
                return null;
            }
        }

        return null;
    }

    /**
     * Generate embeddings for multiple texts
     */
    public function getBatchEmbeddings(array $texts): array
    {
        $embeddings = [];
        $batchDelay = config('embedding.batch_delay', 100);
        $rateLimitDelay = config('embedding.rate_limit_delay', 100000);
        
        foreach ($texts as $index => $text) {
            $embedding = $this->getEmbedding($text);
            $embeddings[$index] = $embedding;
            
            // Rate limiting delays based on batch size
            if (count($texts) > 20) {
                usleep($rateLimitDelay); // Longer delay for very large batches
            } elseif (count($texts) > 5) {
                usleep($batchDelay * 1000); // Convert ms to microseconds for medium batches
            }
        }
        
        return $embeddings;
    }

    /**
     * Validate provider and model combination
     */
    public function validateProviderModel(): bool
    {
        $supportedProviders = config('embedding.supported_providers', []);
        
        if (!isset($supportedProviders[$this->provider])) {
            Log::warning("Unsupported provider: {$this->provider}");
            return false;
        }

        if (!in_array($this->model, $supportedProviders[$this->provider])) {
            Log::warning("Unsupported model '{$this->model}' for provider '{$this->provider}'");
            return false;
        }

        return true;
    }

    /**
     * Set provider and model
     */
    public function useProvider(string $provider, string $model): static
    {
        $this->provider = strtolower($provider);
        $this->model = $model;
        
        return $this;
    }

    /**
     * Set custom API URL
     */
    public function useApiUrl(string $url): static
    {
        $this->apiUrl = $url;
        
        return $this;
    }

    /**
     * Get current configuration
     */
    public function getConfig(): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'api_url' => $this->apiUrl
        ];
    }
}