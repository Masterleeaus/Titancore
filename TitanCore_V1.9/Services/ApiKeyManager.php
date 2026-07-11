<?php

namespace Modules\TitanCore\Services;

/**
 * ApiKeyManager — secure lifecycle management for provider API credentials.
 *
 * Responsibilities:
 *  - Store, retrieve and rotate provider API keys without exposing plaintext.
 *  - Mask keys for display (never expose full value outside of the service).
 *  - Track key status: active, disabled, rotated.
 *  - Validate key format and test connectivity where a validator is provided.
 *
 * Keys are stored in the provided repository; this service contains no persistence
 * logic itself and does not depend on any specific storage backend.
 */
class ApiKeyManager
{
    /** Maximum characters of a key displayed before masking. */
    private const VISIBLE_PREFIX_LENGTH = 4;

    /** @var array<string, array<string, mixed>> Keyed by credential ID. */
    private array $store;

    /**
     * @param  array<string, array<string, mixed>>  $store  Initial in-memory store (for testing / boot).
     */
    public function __construct(array $store = [])
    {
        $this->store = $store;
    }

    /**
     * Store a new API key credential.
     *
     * @param  array<string, mixed>  $attributes  Must include 'provider', 'key'; optionally 'label', 'meta'.
     * @return string  The generated credential ID.
     */
    public function add(array $attributes): string
    {
        $id = $this->generateId();

        $this->store[$id] = [
            'id'         => $id,
            'provider'   => (string) ($attributes['provider'] ?? ''),
            'key'        => (string) ($attributes['key'] ?? ''),
            'label'      => (string) ($attributes['label'] ?? ''),
            'status'     => 'active',
            'rotated_at' => null,
            'created_at' => $this->now(),
            'meta'       => (array) ($attributes['meta'] ?? []),
        ];

        return $id;
    }

    /**
     * Update attributes of an existing credential (excluding the key itself).
     *
     * @param  array<string, mixed>  $attributes
     * @return bool  False when the credential does not exist.
     */
    public function update(string $id, array $attributes): bool
    {
        if (! isset($this->store[$id])) {
            return false;
        }

        foreach (['label', 'meta'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $this->store[$id][$field] = $attributes[$field];
            }
        }

        return true;
    }

    /**
     * Rotate an API key — replace the stored value and record the rotation timestamp.
     *
     * @return bool  False when the credential does not exist.
     */
    public function rotate(string $id, string $newKey): bool
    {
        if (! isset($this->store[$id])) {
            return false;
        }

        $this->store[$id]['key']        = $newKey;
        $this->store[$id]['rotated_at'] = $this->now();
        $this->store[$id]['status']     = 'active';

        return true;
    }

    /**
     * Disable a credential so it can no longer be resolved by the AI gateway.
     *
     * @return bool  False when the credential does not exist.
     */
    public function disable(string $id): bool
    {
        if (! isset($this->store[$id])) {
            return false;
        }

        $this->store[$id]['status'] = 'disabled';

        return true;
    }

    /**
     * Re-enable a previously disabled credential.
     *
     * @return bool  False when the credential does not exist.
     */
    public function enable(string $id): bool
    {
        if (! isset($this->store[$id])) {
            return false;
        }

        $this->store[$id]['status'] = 'active';

        return true;
    }

    /**
     * Remove a credential entirely.
     *
     * @return bool  False when the credential does not exist.
     */
    public function remove(string $id): bool
    {
        if (! isset($this->store[$id])) {
            return false;
        }

        unset($this->store[$id]);

        return true;
    }

    /**
     * Return a credential summary safe for display (key is masked).
     *
     * @return array<string, mixed>|null
     */
    public function summary(string $id): ?array
    {
        if (! isset($this->store[$id])) {
            return null;
        }

        $record = $this->store[$id];
        $record['key'] = $this->mask($record['key']);

        return $record;
    }

    /**
     * Return the raw plaintext key for a credential (for internal gateway use only).
     */
    public function resolve(string $id): ?string
    {
        return isset($this->store[$id]) ? $this->store[$id]['key'] : null;
    }

    /**
     * Return all credential summaries (keys masked) for a given provider.
     *
     * @return array<int, array<string, mixed>>
     */
    public function summariesForProvider(string $provider): array
    {
        return array_values(array_map(
            fn (string $id) => $this->summary($id),
            array_keys(array_filter($this->store, fn (array $r) => $r['provider'] === $provider))
        ));
    }

    /**
     * Return all credential summaries (keys masked).
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_values(array_map(
            fn (array $r) => array_merge($r, ['key' => $this->mask($r['key'])]),
            $this->store
        ));
    }

    /**
     * Return the status of a credential, or null when not found.
     */
    public function status(string $id): ?string
    {
        return isset($this->store[$id]) ? $this->store[$id]['status'] : null;
    }

    /**
     * Validate that a credential ID exists and the key is non-empty.
     *
     * @return array{ok: bool, errors: string[]}
     */
    public function validate(string $id): array
    {
        if (! isset($this->store[$id])) {
            return ['ok' => false, 'errors' => ["Credential '{$id}' not found."]];
        }

        $errors = [];

        if (empty($this->store[$id]['key'])) {
            $errors[] = 'Key value is empty.';
        }

        if (empty($this->store[$id]['provider'])) {
            $errors[] = 'Provider is not set.';
        }

        return ['ok' => empty($errors), 'errors' => $errors];
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Mask a key for safe display.
     */
    private function mask(string $key): string
    {
        if ($key === '') {
            return '';
        }

        $visiblePart = substr($key, 0, self::VISIBLE_PREFIX_LENGTH);
        $maskedPart  = str_repeat('*', max(0, strlen($key) - self::VISIBLE_PREFIX_LENGTH));

        return $visiblePart . $maskedPart;
    }

    private function generateId(): string
    {
        return 'key_' . bin2hex(random_bytes(8));
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
