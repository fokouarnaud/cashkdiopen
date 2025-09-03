<?php

namespace Cashkdiopen\Payments\Console\Commands;

use Cashkdiopen\Payments\Models\ApiKey;
use Illuminate\Console\Command;

class GenerateApiKeyCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cashkdiopen:generate-api-key 
                            {name : The name of the API key}
                            {--permissions=* : Permissions for the API key}
                            {--rate-limit=1000 : Rate limit per hour}';

    /**
     * The console command description.
     */
    protected $description = 'Generate a new Cashkdiopen API key';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $permissions = $this->option('permissions');
        $rateLimit = (int) $this->option('rate-limit');

        $result = ApiKey::generate($name, $permissions, $rateLimit);

        $this->info('API Key generated successfully!');
        $this->line('');
        $this->line('Name: ' . $result['model']->name);
        $this->line('Key: ' . $result['key']);
        $this->line('Rate Limit: ' . $result['model']->rate_limit . ' requests/hour');
        $this->line('Permissions: ' . implode(', ', $result['model']->permissions ?: ['All']));
        $this->line('');
        $this->warn('Store this key safely. It will not be shown again.');

        return self::SUCCESS;
    }
}