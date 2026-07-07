@extends($layout ?? 'layouts.app')

@section('content')
<div class="container">
    <h3 class="mb-4">Titan Core – AI Settings</h3>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('titancore.settings.update') }}" method="POST">
        @csrf

        <div class="mb-3">
            <label class="form-label">Default Provider</label>
            <input type="text" name="default_provider" class="form-control"
                   value="{{ old('default_provider', $settings->default_provider) }}">
        </div>

        <div class="mb-3">
            <label class="form-label">Daily Token Limit (per tenant)</label>
            <input type="number" name="daily_token_limit" class="form-control"
                   value="{{ old('daily_token_limit', $settings->daily_token_limit) }}">
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" name="auto_sync_kb" id="auto_sync_kb" class="form-check-input"
                   {{ $settings->auto_sync_kb ? 'checked' : '' }}>
            <label for="auto_sync_kb" class="form-check-label">
                Auto-sync Worksuite Knowledge Base into Titan Core
            </label>
        </div>

        <div class="mb-3">
            <label class="form-label">KB Collection Slug</label>
            <input type="text" name="kb_collection_slug" class="form-control"
                   value="{{ old('kb_collection_slug', $settings->kb_collection_slug) }}">
            <small class="text-muted">
                Titan Core collection name for core Knowledge Base articles.
            </small>
        </div>

        <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>

    <hr class="my-4">

    <form action="{{ route('titancore.settings.sync_kb') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-outline-secondary">
            Sync Worksuite Knowledge Base Now
        </button>
    </form>
</div>
@endsection
