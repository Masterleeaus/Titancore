@extends($layout ?? 'layouts.main')
@section('page-title'){{ __('Agent Builder') }}@endsection
@section('page-breadcrumb'){{ __('TitanCore › AI › Agents') }}@endsection

@section('content')
<div class="row">
  <div class="col-lg-8">

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Create New Agent --}}
    <div class="card mb-3">
      <div class="card-header">
        <strong>Create Agent</strong>
        <div class="text-muted"><small>Define a new AI specialist agent. Each agent is scoped to one KB collection.</small></div>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('titan.core.ai.agents.store') }}">
          @csrf

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Title <span class="text-danger">*</span></label>
              <input type="text" name="title" class="form-control" required placeholder="e.g. Quote Assistant">
            </div>
            <div class="col-md-6">
              <label class="form-label">Slug <small class="text-muted">(auto-generated if blank)</small></label>
              <input type="text" name="slug" class="form-control" placeholder="e.g. quote_agent" pattern="[a-z0-9_]+">
              <div class="form-text">Lowercase letters, numbers, underscores only.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <input type="text" name="description" class="form-control" placeholder="What this agent does...">
            </div>
            <div class="col-md-6">
              <label class="form-label">KB Collection <span class="text-danger">*</span></label>
              <select name="kb_collection_key" class="form-select" required>
                @foreach($collections as $c)
                  <option value="{{ $c->key_slug ?? $c['key_slug'] }}">{{ $c->title ?? $c['title'] }} ({{ $c->key_slug ?? $c['key_slug'] }})</option>
                @endforeach
                @if(empty($collections))
                  <option value="kb_general_cleaning">kb_general_cleaning (default)</option>
                @endif
              </select>
              <div class="form-text">The knowledge base this agent draws from.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Output Type</label>
              <input type="text" name="output_type" class="form-control" placeholder="e.g. quote_proposal">
            </div>
            <div class="col-12">
              <label class="form-label">System Prompt</label>
              <textarea name="system_prompt" class="form-control" rows="5" placeholder="You are a specialist agent for... Only answer questions about... Always cite your sources..."></textarea>
              <div class="form-text">The core instruction set for this agent's behaviour. Be specific.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Forbidden Topics <small class="text-muted">(comma-separated)</small></label>
              <input type="text" name="forbidden_topics" class="form-control" placeholder="e.g. dispatch, HR, pricing">
            </div>
            <div class="col-md-6 d-flex align-items-end gap-4 pb-1">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="requires_confirm" value="1" id="rc_new">
                <label class="form-check-label" for="rc_new">Requires confirmation</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="must_cite" value="1" id="mc_new">
                <label class="form-check-label" for="mc_new">Must cite sources</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="ia_new" checked>
                <label class="form-check-label" for="ia_new">Active</label>
              </div>
            </div>
          </div>

          <div class="mt-3">
            <button class="btn btn-primary" type="submit">Create Agent</button>
          </div>
        </form>
      </div>
    </div>

    {{-- Existing Agents --}}
    <div class="card mb-3">
      <div class="card-header">
        <strong>All Agents</strong>
        <div class="text-muted"><small>{{ count($agents) }} agent(s) configured.</small></div>
      </div>
      <div class="card-body p-0">
        @if(empty($agents))
          <div class="p-3 text-muted">No agents yet. Create one above, or run <strong>Apply Agent Configuration</strong> on the AI Settings page to seed defaults.</div>
        @else
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Agent</th>
                  <th>KB Collection</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                @foreach($agents as $a)
                  @php($meta = is_array($a['meta'] ?? null) ? $a['meta'] : (json_decode($a['meta'] ?? '{}', true) ?: []))
                  <tr>
                    <td>
                      <div class="fw-semibold">{{ $a['title'] }}</div>
                      <div class="text-muted"><small><code>{{ $a['slug'] }}</code></small></div>
                      @if(!empty($a['description']))
                        <div class="text-muted"><small>{{ $a['description'] }}</small></div>
                      @endif
                    </td>
                    <td><code>{{ $a['kb_collection_key'] }}</code></td>
                    <td>
                      @if(!empty($a['is_active']))
                        <span class="badge bg-success">Active</span>
                      @else
                        <span class="badge bg-secondary">Inactive</span>
                      @endif
                    </td>
                    <td class="text-end">
                      <a href="{{ route('titan.core.ai.agents.edit', $a['slug']) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                      <form method="POST" action="{{ route('titan.core.ai.agents.destroy', $a['slug']) }}" class="d-inline" onsubmit="return confirm('Delete agent {{ $a['slug'] }}?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                      </form>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>

  </div>

  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header"><strong>Tips</strong></div>
      <div class="card-body text-muted">
        <ul class="mb-0">
          <li>Each agent is scoped to one KB collection — pick the most specific one.</li>
          <li>Use the <strong>system prompt</strong> to define behaviour, tone, and output format.</li>
          <li>Set <strong>forbidden topics</strong> to prevent agents from straying outside their domain.</li>
          <li>After creating/editing, go to <strong>AI Settings</strong> and <em>Publish Agent Contract</em> for production stability.</li>
          <li>You can also seed defaults using <strong>Apply Agent Configuration</strong> on the AI Settings page.</li>
        </ul>
        <div class="mt-3">
          <a href="{{ route('titan.core.ai.settings') }}" class="btn btn-outline-secondary btn-sm">← AI Settings</a>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
