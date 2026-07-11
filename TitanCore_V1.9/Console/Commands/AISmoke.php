<?php

namespace Modules\TitanCore\Console\Commands;

use Illuminate\Console\Command;
use Modules\TitanCore\Services\TitanCoreModelGateway;

class AISmoke extends Command
{
    protected $signature = 'ai:smoke {--provider=}';
    protected $description = 'Run a smoke test against the configured AI provider (no external API call here).';

    public function __construct(private TitanCoreModelGateway $gateway)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $provider = $this->option('provider') ?: config('titancore.default_provider', 'openai');

        $health = $this->gateway->health($provider);
        $this->info('Provider: ' . ($health['provider'] ?? '?'));
        $this->info('Healthy: ' . ($health['ok'] ? 'yes' : 'no'));
        if (!$health['ok']) {
            $this->warn('Reason: ' . ($health['reason'] ?? 'unknown'));
        }

        $ping = $this->gateway->chat(
            [['role' => 'user', 'content' => 'ping?']],
            [],
            ['provider' => $provider, 'model' => 'stub'],
        );
        $this->line('Chat stub ok: ' . ($ping['ok'] ? 'yes' : 'no'));

        return $health['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
