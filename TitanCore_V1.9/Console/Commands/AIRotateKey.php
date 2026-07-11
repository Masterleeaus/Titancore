<?php

namespace Modules\TitanCore\Console\Commands;

use Illuminate\Console\Command;

class AIRotateKey extends Command
{
    protected $signature = 'ai:rotate-key {--provider=} {--show : Display new key in output (unsafe)}';
    protected $description = 'Stub key rotation (app-level secret store should handle actual rotation).';

    public function handle(): int
    {
        $provider = $this->option('provider') ?: config('titancore.default_provider', 'openai');
        $new = bin2hex(random_bytes(16));
        // In a real deployment, write to secret store / env and reload config.
        $envVar = match ($provider) {
            'anthropic' => 'ANTHROPIC_API_KEY',
            default => 'OPENAI_API_KEY',
        };
        $this->info("Rotated key for {$provider} (env: {$envVar}).");
        if ($this->option('show')) {
            $this->warn("New key: {$new}");
        } else {
            $this->line('New key generated (not shown).');
        }
        return self::SUCCESS;
    }
}
