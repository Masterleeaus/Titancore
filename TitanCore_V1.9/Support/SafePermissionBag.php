<?php

namespace Modules\TitanCore\Support;

use ArrayAccess;

/**
 * SafePermissionBag
 *
 * Some host Super Admin layouts expect a $sidebarSuperadminPermissions array.
 * When TitanCore pages render inside those layouts, missing variables can throw
 * Blade errors. This bag implements ArrayAccess and returns a safe default for
 * any missing key.
 */
class SafePermissionBag implements ArrayAccess
{
    private array $data;
    private mixed $default;

    public function __construct(array $data = [], mixed $default = 'none')
    {
        $this->data = $data;
        $this->default = $default;
    }

    public function offsetExists(mixed $offset): bool
    {
        return true;
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? $this->default;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            return;
        }
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
