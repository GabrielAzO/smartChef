<?php

namespace App\Console\Commands;

use App\Models\Content;
use App\Services\EntityEmbeddingService;
use Illuminate\Console\Command;

class GenerateContentEmbeddings extends Command
{
    protected $signature = 'content:generate-embeddings 
                            {--missing-only : Only generate for content without embeddings}';

    protected $description = 'Generate and store embeddings for content';

    public function handle(EntityEmbeddingService $embeddingService): int
    {
        $query = Content::query();
        
        if ($this->option('missing-only')) {
            $query->whereNull('embedding');
        }

        $contents = $query->get();

        if ($contents->isEmpty()) {
            $this->info('No content found that needs embeddings.');
            return Command::SUCCESS;
        }

        $this->info("Processing {$contents->count()} content items...");

        $bar = $this->output->createProgressBar($contents->count());
        $bar->start();

        $successful = 0;
        $failed = 0;

        foreach ($contents as $content) {
            if ($embeddingService->generateAndSaveEmbedding($content)) {
                $successful++;
            } else {
                $failed++;
                $this->error("Failed to generate embedding for content: {$content->title}");
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Content embeddings generated successfully!");
        $this->info("Successful: {$successful}");
        $this->info("Failed: {$failed}");

        return Command::SUCCESS;
    }
}