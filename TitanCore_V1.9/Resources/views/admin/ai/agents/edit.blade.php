@extends($layout ?? 'layouts.main')
@section('page-title'){{ __('Edit Agent: ') . $slug }}@endsection
@section('page-breadcrumb'){{ __('TitanCore › AI › Agents › Edit') }}@endsection

@section('content')
<div class="row">
  <div class="col-lg-8">

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-3">
      <div class="card-header">
        <strong>Edit Agent: <code>{{ $slug }}</code></strong>
        <div class="text-muted"><small>{{ $agent['title'] ?? '' }}</small></div>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('titan.core.ai.agents.update', $slug) }}">
          @csrf
          @method('PUT')

          @php
            $meta = $agent['meta'] ?? [];
            $systemPrompt    = $meta['system_prompt'] ?? '';
            $outputType      = $meta['output'] ?? '';
            $requiresConfirm = !empty($meta['requires_confirmation']);
            $mustCite        = !empty($meta['must_cite']);
            $forbiddenTopics = implode(', ', (array)($meta['forbidden_topics'] ?? []));
          @endphp

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Title <span class="text-danger">*</span></label>
              <input type="text" name="title" class="form-control" required value="{{ $agent['title'] ?? '' }}">
            </div>
            <div class="col-md-6">
              <label class="form-label">Slug <small class="text-muted">(read-only)</small></label>
              <input type="text" class="form-control" value="{{ $slug }}" disabled>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <input type="text" name="description" class="form-control" value="{{ $agent['description'] ?? '' }}">
            </div>
            <div class="col-md-6">
              <label class="form-label">KB Collection <span class="text-danger">*</span></label>
              <select name="kb_collection_key" class="form-select" required>
                @foreach($collections as $c)
                  @php($ck = $c->key_slug ?? $c['key_slug'])
                  <option value="{{ $ck }}" {{ ($agent['kb_collection_key'] ?? '') === $ck ? 'selected' : '' }}>
                    {{ $c->title ?? $c['title'] }} ({{ $ck }})
                  </option>
                @endforeach
                @if(empty($collections))
                  <option value="{{ $agent['kb_collection_key'] }}" selected>{{ $agent['kb_collection_key'] }}</option>
                @endif
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Output Type</label>
              <input type="text" name="output_type" class="form-control" value="{{ $outputType }}" placeholder="e.g. quote_proposal">
            </div>
            <div class="col-12">
              <label class="form-label">System Prompt</label>
              <textarea name="system_prompt" class="form-control" rows="8">{{ $systemPrompt }}</textarea>
              <div class="form-text">Core instructions for this agent's behaviour, tone, and output format.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Forbidden Topics <small class="text-muted">(comma-separated)</small></label>
              <input type="text" name="forbidden_topics" class="form-control" value="{{ $forbiddenTopics }}" placeholder="e.g. dispatch, HR">
            </div>
            <div class="col-md-6 d-flex align-items-end gap-4 pb-1">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="requires_confirm" value="1" id="rc" {{ $requiresConfirm ? 'checked' : '' }}>
                <label class="form-check-label" for="rc">Requires confirmation</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="must_cite" value="1" id="mc" {{ $mustCite ? 'checked' : '' }}>
                <label class="form-check-label" for="mc">Must cite sources</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="ia" {{ !empty($agent['is_active']) ? 'checked' : '' }}>
                <label class="form-check-label" for="ia">Active</label>
              </div>
            </div>
          </div>

          <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Save Changes</button>
            <a href="{{ route('titan.core.ai.agents.index') }}" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>
@endsection
