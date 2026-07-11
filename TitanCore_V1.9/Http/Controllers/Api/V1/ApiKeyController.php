<?php

namespace Modules\TitanCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * API Key Management — /api/v1/api-keys/*
 *
 * Manages platform-level API keys for service integrations.
 * Full keys are only revealed once at creation; thereafter only the prefix
 * and hash are stored so the key cannot be recovered if lost.
 *
 * Security notes:
 *  - The plaintext key is returned exactly once (POST /api-keys).
 *  - Stored as SHA-256 hash; the prefix (first 12 chars) is kept for display.
 *  - No secret is ever logged.
 */
class ApiKeyController extends Controller
{
    private const TABLE      = 'titan_api_keys';
    private const KEY_PREFIX = 'tk_';
    private const KEY_LENGTH = 40;

    /**
     * GET /api/v1/api-keys
     *
     * List all API keys (no secret material returned).
     */
    public function index(Request $request): JsonResponse
    {
        if (! Schema::hasTable(self::TABLE)) {
            return $this->tableUnavailable();
        }

        $query = DB::table(self::TABLE)->orderByDesc('created_at');

        if ($request->boolean('active_only')) {
            $query->where('active', true);
        }

        $rows = $query->get([
            'id', 'name', 'key_prefix', 'scopes', 'description',
            'active', 'last_used_at', 'expires_at', 'user_id', 'company_id', 'created_at',
        ]);

        $keys = $rows->map(fn ($row) => $this->formatRow($row))->values()->all();

        return response()->json([
            'data'  => $keys,
            'total' => count($keys),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/api-keys
     *
     * Create a new API key. The full key is returned only in this response.
     */
    public function store(Request $request): JsonResponse
    {
        if (! Schema::hasTable(self::TABLE)) {
            return $this->tableUnavailable();
        }

        $validated = $request->validate([
            'name'        => 'required|string|max:120',
            'scopes'      => 'nullable|array',
            'scopes.*'    => 'string|max:80',
            'description' => 'nullable|string|max:500',
            'expires_at'  => 'nullable|date',
        ]);

        // Generate a cryptographically random key
        $plainKey  = self::KEY_PREFIX . Str::random(self::KEY_LENGTH);
        $prefix    = substr($plainKey, 0, 12);
        $keyHash   = hash('sha256', $plainKey);

        $user      = auth()->user();
        $userId    = $user?->getAuthIdentifier();
        $companyId = $user?->company_id ?? null;

        $id = DB::table(self::TABLE)->insertGetId([
            'name'        => $validated['name'],
            'key_prefix'  => $prefix,
            'key_hash'    => $keyHash,
            'user_id'     => $userId,
            'company_id'  => $companyId,
            'scopes'      => isset($validated['scopes']) ? json_encode($validated['scopes']) : null,
            'description' => $validated['description'] ?? null,
            'active'      => true,
            'expires_at'  => $validated['expires_at'] ?? null,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json([
            'id'          => $id,
            'name'        => $validated['name'],
            'key'         => $plainKey,   // Returned once only
            'key_prefix'  => $prefix,
            'scopes'      => $validated['scopes'] ?? [],
            'description' => $validated['description'] ?? null,
            'active'      => true,
            'expires_at'  => $validated['expires_at'] ?? null,
            'created_at'  => now()->toIso8601String(),
            'ts'          => now()->toIso8601String(),
        ], 201);
    }

    /**
     * GET /api/v1/api-keys/{id}
     *
     * Get metadata for a specific API key (no secret material returned).
     */
    public function show(int $id): JsonResponse
    {
        if (! Schema::hasTable(self::TABLE)) {
            return $this->tableUnavailable();
        }

        $row = DB::table(self::TABLE)->find($id, [
            'id', 'name', 'key_prefix', 'scopes', 'description',
            'active', 'last_used_at', 'expires_at', 'user_id', 'company_id', 'created_at',
        ]);

        if (! $row) {
            return response()->json(['error' => 'API key not found'], 404);
        }

        return response()->json(['data' => $this->formatRow($row)]);
    }

    /**
     * PUT /api/v1/api-keys/{id}
     *
     * Update metadata (name, scopes, description, active, expires_at) for a key.
     * To rotate the secret, use POST /api-keys and revoke the old key.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if (! Schema::hasTable(self::TABLE)) {
            return $this->tableUnavailable();
        }

        $exists = DB::table(self::TABLE)->where('id', $id)->exists();
        if (! $exists) {
            return response()->json(['error' => 'API key not found'], 404);
        }

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:120',
            'scopes'      => 'nullable|array',
            'scopes.*'    => 'string|max:80',
            'description' => 'nullable|string|max:500',
            'active'      => 'sometimes|boolean',
            'expires_at'  => 'nullable|date',
        ]);

        $payload = ['updated_at' => now()];

        if (isset($validated['name']))        $payload['name']        = $validated['name'];
        if (array_key_exists('scopes', $validated))
            $payload['scopes'] = $validated['scopes'] !== null ? json_encode($validated['scopes']) : null;
        if (array_key_exists('description', $validated))
            $payload['description'] = $validated['description'];
        if (isset($validated['active']))      $payload['active']      = $validated['active'];
        if (array_key_exists('expires_at', $validated))
            $payload['expires_at'] = $validated['expires_at'];

        DB::table(self::TABLE)->where('id', $id)->update($payload);

        $row = DB::table(self::TABLE)->find($id, [
            'id', 'name', 'key_prefix', 'scopes', 'description',
            'active', 'last_used_at', 'expires_at', 'user_id', 'company_id', 'created_at',
        ]);

        return response()->json(['data' => $this->formatRow($row)]);
    }

    /**
     * DELETE /api/v1/api-keys/{id}
     *
     * Revoke (soft-delete) an API key by marking it inactive.
     */
    public function revoke(int $id): JsonResponse
    {
        if (! Schema::hasTable(self::TABLE)) {
            return $this->tableUnavailable();
        }

        $affected = DB::table(self::TABLE)
            ->where('id', $id)
            ->update(['active' => false, 'updated_at' => now()]);

        if ($affected === 0) {
            return response()->json(['error' => 'API key not found'], 404);
        }

        return response()->json([
            'ok'  => true,
            'id'  => $id,
            'ts'  => now()->toIso8601String(),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function formatRow(object $row): array
    {
        return [
            'id'          => $row->id,
            'name'        => $row->name,
            'key_prefix'  => $row->key_prefix,
            'scopes'      => json_decode($row->scopes ?? '[]', true) ?: [],
            'description' => $row->description,
            'active'      => (bool) $row->active,
            'last_used_at'=> $row->last_used_at,
            'expires_at'  => $row->expires_at,
            'user_id'     => $row->user_id,
            'company_id'  => $row->company_id,
            'created_at'  => $row->created_at,
        ];
    }

    private function tableUnavailable(): JsonResponse
    {
        return response()->json([
            'data'    => [],
            'total'   => 0,
            'message' => 'API key table not yet migrated',
            'ts'      => now()->toIso8601String(),
        ]);
    }
}
