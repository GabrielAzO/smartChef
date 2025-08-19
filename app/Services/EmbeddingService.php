<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;

class EmbeddingService
{
    protected string $provider;
    protected string $model;

    public function __construct(
        string $provider = 'OpenAI',
        string $model = 'text-embedding-3-small'
    ) {
        $this->provider = $provider;
        $this->model = $model;
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

        try {
            $response = Prism::embeddings()
                ->using(Provider::OpenAI, $this->model)
                ->fromInput($text)
                ->asEmbeddings();

            return $response->embeddings[0]->embedding ?? null;
        } catch (\Exception $e) {
            Log::error('Failed to generate embedding', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text),
                'provider' => $this->provider,
                'model' => $this->model
            ]);
            
            return null;
        }
    }

    /**
     * Generate embeddings for multiple texts
     */
    public function getBatchEmbeddings(array $texts): array
    {
        $embeddings = [];
        
        foreach ($texts as $index => $text) {
            $embedding = $this->getEmbedding($text);
            $embeddings[$index] = $embedding;
        }
        
        return $embeddings;
    }

    /**
     * Set provider and model
     */
    public function useProvider(string $provider, string $model): static
    {
        $this->provider = $provider;
        $this->model = $model;
        
        return $this;
    }
}