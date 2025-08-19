<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use App\Models\Product;
use App\Models\Content;
use App\Services\EntityEmbeddingService;
use Illuminate\Console\Command;

class GenerateAllEmbeddings extends Command
{
    protected $signature = 'embeddings:generate-all 
                            {--type=* : Specific entity types to process (recipe, product, content)}
                            {--missing-only : Only generate for entities without embeddings}
                            {--batch-size=50 : Number of entities to process in each batch}';

    protected $description = 'Generate embeddings for all entities (recipes, products, content)';

    public function handle(EntityEmbeddingService $embeddingService): int
    {
        $types = $this->option('type') ?: ['recipe', 'product', 'content'];
        $missingOnly = $this->option('missing-only');
        $batchSize = (int) $this->option('batch-size');

        $this->info('Starting embedding generation...');
        $this->info('Entity types: ' . implode(', ', $types));
        $this->info('Missing only: ' . ($missingOnly ? 'Yes' : 'No'));
        $this->info('Batch size: ' . $batchSize);

        $totalSuccess = 0;
        $totalFailed = 0;

        foreach ($types as $type) {
            switch (strtolower($type)) {
                case 'recipe':
                    $results = $this->processRecipes($embeddingService, $missingOnly, $batchSize);
                    break;
                case 'product':
                    $results = $this->processProducts($embeddingService, $missingOnly, $batchSize);
                    break;
                case 'content':
                    $results = $this->processContents($embeddingService, $missingOnly, $batchSize);
                    break;
                default:
                    $this->error("Unknown entity type: {$type}");
                    continue 2;
            }

            $totalSuccess += $results['success'];
            $totalFailed += $results['failed'];
        }

        $this->info("Embedding generation complete!");
        $this->info("Total successful: {$totalSuccess}");
        $this->info("Total failed: {$totalFailed}");

        return Command::SUCCESS;
    }

    protected function processRecipes(EntityEmbeddingService $embeddingService, bool $missingOnly, int $batchSize): array
    {
        $query = Recipe::query();
        
        if ($missingOnly) {
            $query->whereNull('embedding');
        }

        return $this->processBatch('Recipes', $query, $embeddingService, $batchSize);
    }

    protected function processProducts(EntityEmbeddingService $embeddingService, bool $missingOnly, int $batchSize): array
    {
        $query = Product::query();
        
        if ($missingOnly) {
            $query->whereNull('embedding');
        }

        return $this->processBatch('Products', $query, $embeddingService, $batchSize);
    }

    protected function processContents(EntityEmbeddingService $embeddingService, bool $missingOnly, int $batchSize): array
    {
        $query = Content::query();
        
        if ($missingOnly) {
            $query->whereNull('embedding');
        }

        return $this->processBatch('Contents', $query, $embeddingService, $batchSize);
    }

    protected function processBatch(string $entityName, $query, EntityEmbeddingService $embeddingService, int $batchSize): array
    {
        $total = $query->count();
        
        if ($total === 0) {
            $this->info("{$entityName}: No entities found to process.");
            return ['success' => 0, 'failed' => 0];
        }

        $this->info("{$entityName}: Processing {$total} entities...");

        $totalSuccess = 0;
        $totalFailed = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk($batchSize, function ($entities) use ($embeddingService, &$totalSuccess, &$totalFailed, $bar) {
            $results = $embeddingService->batchGenerateEmbeddings($entities);
            
            $totalSuccess += $results['success'];
            $totalFailed += $results['failed'];
            
            $bar->advance($entities->count());
        });

        $bar->finish();
        $this->newLine();

        $this->info("{$entityName}: {$totalSuccess} successful, {$totalFailed} failed");

        return ['success' => $totalSuccess, 'failed' => $totalFailed];
    }
}