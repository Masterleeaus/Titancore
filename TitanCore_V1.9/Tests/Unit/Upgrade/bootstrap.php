<?php

namespace TitanTestStubs;

/**
 * Minimal stubs for Illuminate facades used by the Upgrade service classes.
 * Allows the services to be tested without a full Laravel bootstrap.
 */

class SchemaStub
{
    public static function hasTable(string $table): bool { return false; }
}

class DBStub
{
    public static function table(string $t): static { return new static(); }
    public static function transaction(callable $cb): void { $cb(); }
    public function get(): static { return $this; }
    public function toArray(): array { return []; }
    public function delete(): int { return 0; }
    public function insert(array $rows): bool { return true; }
}

class FileStub
{
    public static function ensureDirectoryExists(string $path, int $mode = 0755): void
    {
        if (! is_dir($path)) { mkdir($path, $mode, true); }
    }
}

class CarbonStub
{
    public function format(string $fmt): string { return date($fmt); }
    public function toIso8601String(): string { return date('c'); }
}
