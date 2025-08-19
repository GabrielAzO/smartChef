<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Recipe;
use App\Services\EntityEmbeddingService;
use Pgvector\Laravel\Vector;

class GenerateRecipeEmbeddings extends Command
{
    protected $signature = 'recipes:generate-embeddings';
    protected $description = 'Generate and store embeddings for all recipes without one';

    public function handle(EntityEmbeddingService $embeddingService): int
    {
        $recipes = Recipe::query()->get();

        if ($recipes->isEmpty()) {
            $this->info('No recipes found that need embeddings.');
            return Command::SUCCESS;
        }

        foreach ($recipes as $recipe) {
            $this->info("Generating embedding for recipe: {$recipe->title}");

            if ($embeddingService->generateAndSaveEmbedding($recipe)) {
                $this->info("Saved embedding for: {$recipe->title}");
            } else {
                $this->error("Failed for {$recipe->title}");
            }
        }

        $this->info('Embeddings generated successfully.');
        return Command::SUCCESS;
    }

}