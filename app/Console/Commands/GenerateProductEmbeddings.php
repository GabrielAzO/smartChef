<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\EntityEmbeddingService;
use Illuminate\Console\Command;

class GenerateProductEmbeddings extends Command
{
    protected $signature = 'products:generate-embeddings 
                            {--missing-only : Only generate for products without embeddings}';

    protected $description = 'Generate and store embeddings for products';

    public function handle(EntityEmbeddingService $embeddingService): int
    {
        $query = Product::query();
        
        if ($this->option('missing-only')) {
            $query->whereNull('embedding');
        }

        $products = $query->get();

        if ($products->isEmpty()) {
            $this->info('No products found that need embeddings.');
            return Command::SUCCESS;
        }

        $this->info("Processing {$products->count()} products...");

        $bar = $this->output->createProgressBar($products->count());
        $bar->start();

        $successful = 0;
        $failed = 0;

        foreach ($products as $product) {
            if ($embeddingService->generateAndSaveEmbedding($product)) {
                $successful++;
            } else {
                $failed++;
                $this->error("Failed to generate embedding for product: {$product->name}");
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Product embeddings generated successfully!");
        $this->info("Successful: {$successful}");
        $this->info("Failed: {$failed}");

        return Command::SUCCESS;
    }
}