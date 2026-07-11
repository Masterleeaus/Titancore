<?php

namespace Modules\TitanCore\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

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
        return response()->json(['prompt' => $row]);
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

        return response()->json(['prompt' => $row]);
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

    /**
     * Placeholder endpoint so routes can be wired safely without breaking.
     * Replace with your real binding logic in the host app.
     */
    public function bind(Request $request)
    {
        return response()->json(['status' => 'ok']);
    }

    // Optional CRUD placeholders (kept simple and valid).
    public function store(Request $request)
    {
        return response()->json(['todo' => 'store'], 201);
    }

    public function update(Request $request, int $id)
    {
        return response()->json(['todo' => 'update', 'id' => $id]);
    }

    public function destroy(int $id)
    {
        return response()->json(['todo' => 'destroy', 'id' => $id]);
    }
}
