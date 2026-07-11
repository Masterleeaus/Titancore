<?php

namespace Modules\TitanCore\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PromptApiController extends Controller
{
    public function index(Request $request)
    {
        $namespace = $request->query('namespace');
        $locale = $request->query('locale');

        $q = DB::table('ai_prompts')->orderBy('updated_at', 'desc');
        if ($namespace) {
            $q->where('namespace', $namespace);
        }
        if ($locale) {
            $q->where('locale', $locale);
        }

        $rows = $q->limit(200)->get();
        return response()->json(['data' => $rows]);
    }

    public function show(Request $request, int $id)
    {
        $row = DB::table('ai_prompts')->where('id', $id)->first();

        return $row
            ? response()->json(['prompt' => $this->decodePromptMetadata($row)])
            : response()->json(['error' => 'Prompt not found'], 404);
    }

    public function resolve(Request $request, string $namespace, string $slug)
    {
        $locale = $request->query('locale', 'en');

        $ver = DB::table('ai_prompts')
            ->where('namespace', $namespace)
            ->where('slug', $slug)
            ->where('locale', $locale)
            ->max('version');

        $row = DB::table('ai_prompts')
            ->where('namespace', $namespace)
            ->where('slug', $slug)
            ->where('locale', $locale)
            ->where('version', $ver)
            ->first();

        return $row
            ? response()->json(['prompt' => $this->decodePromptMetadata($row)])
            : response()->json(['error' => 'Prompt not found'], 404);
    }

    public function createVersion(Request $request)
    {
        $namespace = $request->input('namespace');
        $slug = $request->input('slug');
        $content = $request->input('content');
        $locale = $request->input('locale', 'en');

        $latest = DB::table('ai_prompts')
            ->where('namespace', $namespace)
            ->where('slug', $slug)
            ->where('locale', $locale)
            ->max('version');

        $ver = (int) $latest + 1;

        $id = DB::table('ai_prompts')->insertGetId([
            'namespace' => $namespace,
            'slug' => $slug,
            'version' => $ver,
            'locale' => $locale,
            'content' => $content,
            'metadata' => json_encode([]),
            'source' => 'core',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $id, 'version' => $ver], 201);
    }

    public function bind(Request $request)
    {
        return response()->json(['status' => 'ok']);
    }

    public function store(Request $request)
    {
        $data = $this->validatePromptPayload($request, true);
        $namespace = $data['namespace'] ?? $data['title'];
        $slug = $data['slug'] ?? Str::slug($namespace);
        $locale = $data['locale'] ?? 'en';
        $metadata = $this->normalizeMetadata($data);
        $version = $this->nextVersion($namespace, $slug, $locale);

        $payload = [
            'namespace' => $namespace,
            'slug' => $slug,
            'version' => $version,
            'locale' => $locale,
            'content' => $data['content'],
            'metadata' => json_encode($metadata),
            'source' => $data['source'] ?? 'core',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (array_key_exists('company_id', $data)) {
            $payload['company_id'] = $data['company_id'];
        }

        $id = DB::table('ai_prompts')->insertGetId($payload);

        return response()->json([
            'id' => $id,
            'version' => $version,
            'prompt' => $this->decodePromptMetadata((object) array_merge(['id' => $id], $payload)),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $row = DB::table('ai_prompts')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['error' => 'Prompt not found'], 404);
        }

        $data = $this->validatePromptPayload($request, false);
        $namespace = $data['namespace'] ?? $data['title'] ?? $row->namespace;
        $slug = $data['slug'] ?? (! empty($data['title']) ? Str::slug($namespace) : $row->slug);
        $locale = $data['locale'] ?? $row->locale;
        $metadata = $this->normalizeMetadata($data, $row);

        $payload = [
            'namespace' => $namespace,
            'slug' => $slug,
            'locale' => $locale,
            'content' => $data['content'] ?? $row->content,
            'metadata' => json_encode($metadata),
            'source' => $data['source'] ?? $row->source,
            'updated_at' => now(),
        ];

        if (array_key_exists('company_id', $data)) {
            $payload['company_id'] = $data['company_id'];
        }

        DB::table('ai_prompts')->where('id', $id)->update($payload);

        return response()->json([
            'id' => $id,
            'prompt' => $this->decodePromptMetadata((object) array_merge((array) $row, $payload, ['id' => $id])),
        ]);
    }

    public function destroy(int $id)
    {
        $deleted = DB::table('ai_prompts')->where('id', $id)->delete();

        if ($deleted === 0) {
            return response()->json(['error' => 'Prompt not found'], 404);
        }

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    private function validatePromptPayload(Request $request, bool $isCreate): array
    {
        $rules = [
            'namespace' => $isCreate ? 'required_without:title|string|max:128' : 'sometimes|string|max:128',
            'title' => $isCreate ? 'required_without:namespace|string|max:255' : 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:128',
            'locale' => 'sometimes|string|max:16',
            'content' => $isCreate ? 'required|string|min:1' : 'sometimes|string|min:1',
            'metadata' => 'sometimes|array',
            'source' => 'sometimes|string|in:core,module,agent,tenant',
            'company_id' => 'sometimes|nullable|integer',
        ];

        return $request->validate($rules);
    }

    private function nextVersion(string $namespace, string $slug, string $locale): int
    {
        $latest = DB::table('ai_prompts')
            ->where('namespace', $namespace)
            ->where('slug', $slug)
            ->where('locale', $locale)
            ->max('version');

        return ((int) $latest) + 1;
    }

    private function normalizeMetadata(array $data, ?object $existing = null): array
    {
        $metadata = [];

        if ($existing && isset($existing->metadata)) {
            $decoded = json_decode((string) $existing->metadata, true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $metadata = array_merge($metadata, $data['metadata']);
        }

        if (isset($data['title'])) {
            $metadata['title'] = $data['title'];
        } elseif (! isset($metadata['title']) && isset($data['namespace'])) {
            $metadata['title'] = $data['namespace'];
        }

        return $metadata;
    }

    private function decodePromptMetadata(object $row): object
    {
        if (! isset($row->metadata) || ! is_string($row->metadata)) {
            return $row;
        }

        $decoded = json_decode($row->metadata, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return $row;
        }

        $row->metadata = $decoded;

        return $row;
    }
}
