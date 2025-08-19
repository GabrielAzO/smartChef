<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\Product;
use App\Models\Content;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Pgvector\Laravel\Vector;

class EntityEmbeddingService
{
    public function __construct(
        protected EmbeddingService $embeddingService
    ) {}

    /**
     * Generate embedding for a Recipe
     */
    public function generateRecipeEmbedding(Recipe $recipe): ?array
    {
        $searchableText = collect([
            $recipe->title,
            implode(', ', $recipe->tags ?? []),
            $recipe->raw_text,
        ])->filter()->implode("\n");

        return $this->embeddingService->getEmbedding($searchableText);
    }

    /**
     * Generate embedding for a Product
     */
    public function generateProductEmbedding(Product $product): ?array
    {
        $searchableText = $product->getSearchableText();
        return $this->embeddingService->getEmbedding($searchableText);
    }

    /**
     * Generate embedding for Content
     */
    public function generateContentEmbedding(Content $content): ?array
    {
        $searchableText = $content->getSearchableText();
        return $this->embeddingService->getEmbedding($searchableText);
    }

    /**
     * Generate and save embedding for any supported model
     */
    public function generateAndSaveEmbedding(Model $model): bool
    {
        try {
            $embedding = match (get_class($model)) {
                Recipe::class => $this->generateRecipeEmbedding($model),
                Product::class => $this->generateProductEmbedding($model),
                Content::class => $this->generateContentEmbedding($model),
                default => throw new \InvalidArgumentException('Unsupported model type: ' . get_class($model))
            };

            if ($embedding === null) {
                Log::warning('Failed to generate embedding', [
                    'model_type' => get_class($model),
                    'model_id' => $model->id
                ]);
                return false;
            }

            $model->embedding = new Vector($embedding);
            $model->save();

            Log::info('Embedding saved successfully', [
                'model_type' => get_class($model),
                'model_id' => $model->id
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to generate and save embedding', [
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Batch generate embeddings for a collection of models
     */
    public function batchGenerateEmbeddings($models): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($models as $model) {
            if ($this->generateAndSaveEmbedding($model)) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'model_type' => get_class($model),
                    'model_id' => $model->id
                ];
            }
        }

        return $results;
    }
}