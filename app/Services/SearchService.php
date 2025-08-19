<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\Product;
use App\Models\Content;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Pgvector\Laravel\Distance;

class SearchService
{
    public function __construct(
        protected EmbeddingService $embeddingService
    ) {}

    /**
     * Search across all entity types using semantic similarity
     */
    public function searchAll(
        string $query, 
        int $limit = 10, 
        array $entityTypes = ['recipes', 'products', 'contents'],
        float $threshold = null
    ): array {
        $queryEmbedding = $this->embeddingService->getEmbedding($query);
        
        if (!$queryEmbedding) {
            Log::error('Failed to generate query embedding for search', ['query' => $query]);
            return ['recipes' => [], 'products' => [], 'contents' => []];
        }

        $results = [];

        foreach ($entityTypes as $entityType) {
            $results[$entityType] = match($entityType) {
                'recipes' => $this->searchRecipes($queryEmbedding, $limit, $threshold),
                'products' => $this->searchProducts($queryEmbedding, $limit, $threshold),
                'contents' => $this->searchContents($queryEmbedding, $limit, $threshold),
                default => []
            };
        }

        return $results;
    }

    /**
     * Search recipes using semantic similarity
     */
    public function searchRecipes(
        array|string $query, 
        int $limit = 10, 
        float $threshold = null
    ): Collection {
        $queryEmbedding = is_string($query) 
            ? $this->embeddingService->getEmbedding($query)
            : $query;

        if (!$queryEmbedding) {
            return collect();
        }

        $queryBuilder = Recipe::query()
            ->whereNotNull('embedding')
            ->nearestNeighbors('embedding', $queryEmbedding, Distance::Cosine)
            ->take($limit);

        if ($threshold !== null) {
            // Note: Implementing threshold filtering would require a custom query
            // This is a placeholder for potential future enhancement
        }

        return $queryBuilder->get();
    }

    /**
     * Search products using semantic similarity
     */
    public function searchProducts(
        array|string $query, 
        int $limit = 10, 
        float $threshold = null
    ): Collection {
        $queryEmbedding = is_string($query) 
            ? $this->embeddingService->getEmbedding($query)
            : $query;

        if (!$queryEmbedding) {
            return collect();
        }

        $queryBuilder = Product::query()
            ->whereNotNull('embedding')
            ->nearestNeighbors('embedding', $queryEmbedding, Distance::Cosine)
            ->take($limit);

        return $queryBuilder->get();
    }

    /**
     * Search content using semantic similarity
     */
    public function searchContents(
        array|string $query, 
        int $limit = 10, 
        float $threshold = null
    ): Collection {
        $queryEmbedding = is_string($query) 
            ? $this->embeddingService->getEmbedding($query)
            : $query;

        if (!$queryEmbedding) {
            return collect();
        }

        $queryBuilder = Content::query()
            ->whereNotNull('embedding')
            ->nearestNeighbors('embedding', $queryEmbedding, Distance::Cosine)
            ->take($limit);

        return $queryBuilder->get();
    }

    /**
     * Get mixed results from all entity types with relevance scores
     */
    public function searchMixed(
        string $query, 
        int $totalLimit = 10,
        array $entityWeights = ['recipes' => 0.4, 'products' => 0.3, 'contents' => 0.3]
    ): array {
        $queryEmbedding = $this->embeddingService->getEmbedding($query);
        
        if (!$queryEmbedding) {
            return [];
        }

        $results = [];

        // Calculate limits for each entity type based on weights
        foreach ($entityWeights as $entityType => $weight) {
            $entityLimit = max(1, (int) ($totalLimit * $weight));
            
            $entityResults = match($entityType) {
                'recipes' => $this->searchRecipes($queryEmbedding, $entityLimit)->map(function ($item) {
                    return [
                        'type' => 'recipe',
                        'id' => $item->id,
                        'title' => $item->title,
                        'data' => $item,
                        'relevance_score' => null // Would need custom calculation
                    ];
                }),
                'products' => $this->searchProducts($queryEmbedding, $entityLimit)->map(function ($item) {
                    return [
                        'type' => 'product',
                        'id' => $item->id,
                        'title' => $item->name,
                        'data' => $item,
                        'relevance_score' => null
                    ];
                }),
                'contents' => $this->searchContents($queryEmbedding, $entityLimit)->map(function ($item) {
                    return [
                        'type' => 'content',
                        'id' => $item->id,
                        'title' => $item->title,
                        'data' => $item,
                        'relevance_score' => null
                    ];
                }),
                default => collect()
            };

            $results = array_merge($results, $entityResults->toArray());
        }

        // Sort by relevance score if available, otherwise keep order
        // usort($results, fn($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        return array_slice($results, 0, $totalLimit);
    }

    /**
     * Find similar entities to a given entity
     */
    public function findSimilar($entity, int $limit = 5): Collection
    {
        if (!$entity->embedding) {
            return collect();
        }

        $modelClass = get_class($entity);
        
        return $modelClass::query()
            ->where('id', '!=', $entity->id)
            ->whereNotNull('embedding')
            ->nearestNeighbors('embedding', $entity->embedding, Distance::Cosine)
            ->take($limit)
            ->get();
    }
}