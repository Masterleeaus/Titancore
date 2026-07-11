<?php

namespace Modules\TitanCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\TitanCore\Entities\TitanCoreSetting;
use Modules\TitanCore\Services\KnowledgeSyncService;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = TitanCoreSetting::getSetting();
        $layout = \Illuminate\Support\Facades\View::exists('layouts.main') ? 'layouts.main'
            : (\Illuminate\Support\Facades\View::exists('layouts.app') ? 'layouts.app' : 'titancore::layouts.main');

        return view('titancore::admin.settings.index', compact('settings', 'layout'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'default_provider'   => 'required|string',
            'daily_token_limit'  => 'required|integer|min:0',
            'auto_sync_kb'       => 'sometimes|boolean',
            'kb_collection_slug' => 'required|string',
        ]);

        $settings = TitanCoreSetting::getSetting();
        $settings->update([
            'default_provider'   => $data['default_provider'],
            'daily_token_limit'  => $data['daily_token_limit'],
            'auto_sync_kb'       => $request->boolean('auto_sync_kb'),
            'kb_collection_slug' => $data['kb_collection_slug'],
        ]);

        return redirect()
            ->route('titancore.settings.index')
            ->with('success', 'AI settings updated.');
    }

    public function syncKb(KnowledgeSyncService $syncService)
    {
        $syncService->syncWorksuiteKnowledgeBase();

        return redirect()
            ->route('titancore.settings.index')
            ->with('success', 'Knowledge Base synced to Titan Core.');
    }
}
