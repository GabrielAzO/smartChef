<?php

namespace App\Console\Commands;

use App\Services\EmbeddingService;
use Illuminate\Console\Command;

class TestEmbeddingApi extends Command
{
    protected $signature = 'embeddings:test 
                            {--text=Hello world : Text to test embedding generation}
                            {--provider=openai : Provider to use}
                            {--model=text-embedding-3-small : Model to use}';

    protected $description = 'Test the external embedding API integration';

    public function handle(): int
    {
        $text = $this->option('text');
        $provider = $this->option('provider');
        $model = $this->option('model');

        $this->info('Testing embedding API integration...');
        $this->info("Text: {$text}");
        $this->info("Provider: {$provider}");
        $this->info("Model: {$model}");
        $this->info("API URL: " . config('embedding.api_url'));

        $embeddingService = new EmbeddingService($provider, $model);

        // Validate provider/model combination
        if (!$embeddingService->validateProviderModel()) {
            $this->error('Invalid provider/model combination');
            return Command::FAILURE;
        }

        $this->info('Generating embedding...');
        $startTime = microtime(true);

        $embedding = $embeddingService->getEmbedding($text);
        
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds

        if ($embedding === null) {
            $this->error('Failed to generate embedding');
            $this->error('Check the logs for more details');
            return Command::FAILURE;
        }

        $this->info("✅ Embedding generated successfully!");
        $this->info("Duration: {$duration}ms");
        $this->info("Dimensions: " . count($embedding));
        $this->info("First 10 values: " . implode(', ', array_slice($embedding, 0, 10)));

        // Test the configuration
        $config = $embeddingService->getConfig();
        $this->info('Current configuration:');
        $this->table(['Setting', 'Value'], [
            ['Provider', $config['provider']],
            ['Model', $config['model']],
            ['API URL', $config['api_url']],
            ['Timeout', config('embedding.timeout', 30) . 's'],
            ['Retry Attempts', config('embedding.retry_attempts', 3)],
            ['Retry Delay', config('embedding.retry_delay', 1000) . 'ms'],
        ]);

        return Command::SUCCESS;
    }
}