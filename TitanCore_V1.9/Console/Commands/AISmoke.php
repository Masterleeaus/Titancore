<?php

namespace Modules\TitanCore\Console\Commands;

use Illuminate\Console\Command;

class AISmoke extends Command
{
    protected $signature = 'ai:smoke {--provider=}';
    protected $description = 'Run a smoke test against the configured AI provider (no external API call here).';

    public function handle(): int
    {
        $provider = $this->option('provider') ?: config('titancore.default_provider', 'openai');
        app()->forgetInstance('titancore.client'); // ensure fresh binding
        config(['titancore.default_provider' => $provider]);
        $client = app('titancore.client');

        $health = $client->health();
        $this->info('Provider: ' . ($health['provider'] ?? '?'));
        $this->info('Healthy: ' . ($health['ok'] ? 'yes' : 'no'));
        if (!$health['ok']) $this->warn('Reason: ' . ($health['reason'] ?? 'unknown'));

        $ping = $client->chat([['role' => 'user', 'content' => 'ping?']], ['model' => 'stub']);
        $this->line('Chat stub ok: ' . ($ping['ok'] ? 'yes' : 'no'));

        return $health['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
