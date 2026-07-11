@extends($layout ?? 'layouts.main')
@section('page-title'){{ __('Titan AI Settings') }}@endsection
@section('page-breadcrumb'){{ __('TitanCore') }}@endsection

@section('content')
<div class="row">
  <div class="col-lg-8">

    {{-- System checks + quick start --}}
    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between gap-3">
          <div>
            <h5 class="mb-1">Titan AI Settings</h5>
            <div class="text-muted">Configure embeddings, sync agents, manage AI knowledge base, and set your API keys.</div>
          </div>
          <a href="{{ route('titan.core.ai.agents.index') }}" class="btn btn-outline-primary btn-sm text-nowrap">
            🤖 Agent Builder
          </a>
        </div>

        <div class="mt-3">
          <div class="d-flex flex-wrap gap-2">
            @if(!empty($needsMigrations) && $needsMigrations)
              <span class="badge bg-warning text-dark">DB migrations required</span>
            @else
              <span class="badge bg-success">DB OK</span>
            @endif

            @if(!empty($queueWarning))
              <span class="badge bg-warning text-dark">Queue/cron not running</span>
            @else
              <span class="badge bg-success">Background jobs OK</span>
            @endif

            @if(!empty($embeddingsEnabled))
              <span class="badge bg-primary">Embeddings enabled</span>
            @else
              <span class="badge bg-secondary">Embeddings off</span>
            @endif

            @if(!empty($apiConfig['openai_key_set'] ?? false))
              <span class="badge bg-success">OpenAI key ✓</span>
            @else
              <span class="badge bg-warning text-dark">OpenAI key missing</span>
            @endif

            @if(!empty($apiConfig['anthropic_key_set'] ?? false))
              <span class="badge bg-success">Anthropic key ✓</span>
            @else
              <span class="badge bg-secondary">Anthropic key not set</span>
            @endif
          </div>

          @if(!empty($needsMigrations) && $needsMigrations)
            <div class="alert alert-warning mt-3 mb-0">
              <strong>One-time setup:</strong> Titan AI tables are missing. Run migrations once, then refresh this page.
              <div class="mt-2"><code>php artisan migrate</code></div>
            </div>
          @endif

          @if(!empty($queueWarning))
            <div class="alert alert-warning mt-3 mb-0">
              <strong>Background processing:</strong> Actions below run via the queue.
              <div class="mt-2"><code>php artisan queue:work</code></div>
              <div class="text-muted mt-1"><small>{{ $queueWarning }}</small></div>
            </div>
          @endif
        </div>

        <hr class="my-3"/>

        <div class="row g-3">
          <div class="col-md-6">
            <div class="border rounded p-3 h-100">
              <div class="fw-semibold mb-2">Quick start</div>
              <ol class="mb-0 text-muted" style="padding-left: 1.1rem;">
                <li>Set your <strong>OpenAI API key</strong> below.</li>
                <li>Save your settings (embeddings + sync options).</li>
                <li>Click <strong>Apply Agent Configuration</strong> to generate default agents.</li>
                <li>Or use <strong>Agent Builder</strong> to create custom agents.</li>
                <li>Click <strong>Index AI Knowledge Base</strong> to index approved templates.</li>
                <li>Optional: embed missing chunks, publish a snapshot, then run a test.</li>
              </ol>
            </div>
          </div>
          <div class="col-md-6">
            <div class="border rounded p-3 h-100">
              <div class="fw-semibold mb-2">What these buttons do</div>
              <ul class="mb-0 text-muted">
                <li><strong>Agent Builder</strong> lets you create/edit agents with custom prompts in the UI.</li>
                <li><strong>Apply Agent Configuration</strong> seeds default agents from config into DB.</li>
                <li><strong>Index AI Knowledge Base</strong> turns TitanDocs templates into searchable chunks.</li>
                <li><strong>Re-embed missing</strong> creates embeddings for chunks without them.</li>
              </ul>
            </div>
          </div>
        </div>

      </div>
    </div>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- ================================================================ --}}
    {{-- API KEY MANAGEMENT                                               --}}
    {{-- ================================================================ --}}
    <div class="card mb-3">
      <div class="card-header">
        <strong>API Keys &amp; Provider</strong>
        <div class="text-muted"><small>Keys are encrypted per tenant. They override .env values at runtime.</small></div>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('titan.core.ai.settings.save_api_keys') }}">
          @csrf

          <div class="row g-3">

            <div class="col-md-4">
              <label class="form-label">Default Provider</label>
              <select name="ai_provider" class="form-select">
                <option value="openai"    {{ (($apiConfig['default_provider'] ?? 'openai') === 'openai')    ? 'selected' : '' }}>OpenAI</option>
                <option value="anthropic" {{ (($apiConfig['default_provider'] ?? 'openai') === 'anthropic') ? 'selected' : '' }}>Anthropic (Claude)</option>
              </select>
            </div>

            <div class="col-12"><hr class="my-0"/><div class="fw-semibold mt-2">OpenAI</div></div>

            <div class="col-md-8">
              <label class="form-label">
                OpenAI API Key
                @if(!empty($apiConfig['openai_key_set'] ?? false))
                  <span class="badge bg-success ms-1">Saved ✓</span>
                @else
                  <span class="badge bg-warning text-dark ms-1">Not set</span>
                @endif
              </label>
              <input type="password" name="openai_api_key" class="form-control font-monospace"
                     placeholder="{{ !empty($apiConfig['openai_key_set'] ?? false) ? '••••••••• (leave blank to keep)' : 'sk-...' }}"
                     autocomplete="new-password">
              <div class="form-text">Leave blank to keep existing. Get yours at <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">OpenAI Model</label>
              <input type="text" name="openai_model" class="form-control"
                     value="{{ $apiConfig['openai_model'] ?? 'gpt-4o-mini' }}"
                     placeholder="gpt-4o-mini">
              <div class="form-text">e.g. gpt-4o, gpt-4o-mini, gpt-4-turbo</div>
            </div>

            <div class="col-12"><hr class="my-0"/><div class="fw-semibold mt-2">Anthropic (Claude) — optional</div></div>

            <div class="col-md-8">
              <label class="form-label">
                Anthropic API Key
                @if(!empty($apiConfig['anthropic_key_set'] ?? false))
                  <span class="badge bg-success ms-1">Saved ✓</span>
                @else
                  <span class="badge bg-secondary ms-1">Not set</span>
                @endif
              </label>
              <input type="password" name="anthropic_api_key" class="form-control font-monospace"
                     placeholder="{{ !empty($apiConfig['anthropic_key_set'] ?? false) ? '••••••••• (leave blank to keep)' : 'sk-ant-...' }}"
                     autocomplete="new-password">
            </div>
            <div class="col-md-4">
              <label class="form-label">Anthropic Model</label>
              <input type="text" name="anthropic_model" class="form-control"
                     value="{{ $apiConfig['anthropic_model'] ?? 'claude-3-haiku' }}"
                     placeholder="claude-3-haiku">
              <div class="form-text">e.g. claude-3-5-sonnet, claude-3-haiku</div>
            </div>

          </div>

          <div class="mt-3">
            <button class="btn btn-primary" type="submit">Save API Keys</button>
          </div>
        </form>
      </div>
    </div>

    {{-- Settings --}}
    <div class="card mb-3">
      <div class="card-header">
        <strong>Settings</strong>
        <div class="text-muted"><small>These control how knowledge is indexed and retrieved.</small></div>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('titan.core.ai.settings.save') }}">
          @csrf

          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="embeddings_enabled" name="embeddings_enabled" value="1" @if(!empty($embeddingsEnabled)) checked @endif>
            <label class="form-check-label" for="embeddings_enabled">
              Enable embeddings (semantic search)
            </label>
            <div class="form-text">
              Better answers for "meaning-based" questions. Requires OpenAI API key.
            </div>
          </div>

          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="embed_on_sync" name="embed_on_sync" value="1" @if(!empty($embedOnSync)) checked @endif>
            <label class="form-check-label" for="embed_on_sync">
              Generate embeddings during TitanDocs sync
            </label>
            <div class="form-text">
              When OFF, sync still indexes text, but you must run "Re-embed missing chunks" later.
            </div>
          </div>

          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="include_drafts" name="include_drafts" value="1" @if(!empty($includeDrafts)) checked @endif>
            <label class="form-check-label" for="include_drafts">
              Include drafts in AI knowledge indexing
            </label>
            <div class="form-text">
              Keep OFF in production so only approved templates influence live agents.
            </div>
          </div>

          <button class="btn btn-primary" type="submit">Save settings</button>
        </form>
      </div>
    </div>

    {{-- Actions --}}
    <div class="card mb-3">
      <div class="card-header">
        <strong>Actions</strong>
        <div class="text-muted"><small>Runs in the background (queue). Watch progress in "Latest Runs".</small></div>
      </div>
      <div class="card-body">
        <div class="d-flex gap-2 flex-wrap">
          <form method="POST" action="{{ route('titan.core.ai.settings.sync_agents') }}">
            @csrf
            <button class="btn btn-outline-primary" type="submit">
              Apply agent configuration
            </button>
          </form>

          <form method="POST" action="{{ route('titan.core.ai.settings.sync_titandocs') }}">
            @csrf
            <button class="btn btn-outline-success" type="submit">
              Index AI Knowledge Base
            </button>
          </form>

          <a href="{{ route('titan.core.ai.agents.index') }}" class="btn btn-outline-secondary">
            🤖 Agent Builder
          </a>
        </div>

        <div class="mt-3">
          <div class="text-muted"><small>
            Tip: Do <strong>Apply agent configuration</strong> first if you haven't created agents yet.
            Or use <strong>Agent Builder</strong> to create custom agents with your own system prompts.
          </small></div>
        </div>
      </div>
    </div>

    {{-- Knowledge base health --}}
    <div class="card mb-3">
      <div class="card-header">
        <strong>Knowledge base</strong>
        <div class="text-muted"><small>How much content is indexed and embedded.</small></div>
      </div>
      <div class="card-body">
        @if(!empty($kbHealth))
          <div class="row g-2 mb-3">
            <div class="col-md-3"><div class="text-muted"><small>Documents</small></div><div class="fs-5">{{ $kbHealth['documents'] }}</div></div>
            <div class="col-md-3"><div class="text-muted"><small>Chunks</small></div><div class="fs-5">{{ $kbHealth['chunks'] }}</div></div>
            <div class="col-md-3"><div class="text-muted"><small>Embedded</small></div><div class="fs-5">{{ $kbHealth['embedded'] }}</div></div>
            <div class="col-md-3"><div class="text-muted"><small>Embed %</small></div><div class="fs-5">{{ $kbHealth['embed_ratio'] }}%</div></div>
          </div>

          @if(!empty($kbHealth['collections'] ?? []))
            <div class="mb-3">
              <div class="fw-semibold mb-1">By collection</div>
              <div class="text-muted"><small>Docs per collection.</small></div>
              <ul class="mb-0">
                @foreach(($kbHealth['collections'] ?? []) as $k=>$v)
                  <li><code>{{ $k }}</code>: {{ $v }} docs</li>
                @endforeach
              </ul>
            </div>
          @endif

          <div class="border rounded p-3">
            <div class="fw-semibold">Fix missing embeddings</div>
            <div class="text-muted mb-2"><small>Creates embeddings for chunks that don't have them yet (useful after turning embeddings on).</small></div>
            <form method="POST" action="{{ route('titan.core.ai.settings.re_embed_missing') }}" class="d-flex gap-2 flex-wrap align-items-end">
              @csrf
              <div>
                <label class="form-label mb-1"><small>Limit</small></label>
                <input type="number" name="limit" class="form-control form-control-sm" value="500" style="max-width:140px">
              </div>
              <button class="btn btn-outline-primary btn-sm" type="submit">Re-embed missing chunks</button>
            </form>
          </div>
        @else
          <div class="text-muted">KB not initialised yet. Run "Index AI Knowledge Base" first.</div>
        @endif
      </div>
    </div>

    {{-- Publish KB snapshot --}}
    <div class="card mb-3">
      <div class="card-header">
        <strong>Publish KB snapshot</strong>
        <div class="text-muted"><small>Freezes the current knowledge state so live agents don't change unexpectedly.</small></div>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3">
          Publishing creates an <strong>immutable snapshot</strong> for a collection and marks it active. This prevents edits to approved templates
          from silently changing live agent behaviour.
        </p>

        <form method="POST" action="{{ route('titan.core.ai.settings.publish_kb_snapshot') }}" class="row g-2 align-items-end">
          @csrf
          <div class="col-md-5">
            <label class="form-label">Collection</label>
            <select name="collection" class="form-select form-select-sm">
              @foreach(($kbCollections ?? []) as $c)
                <option value="{{ $c['key'] ?? ($c->key ?? 'default') }}">
                  {{ $c['title'] ?? ($c->title ?? ($c['key'] ?? ($c->key ?? 'default'))) }}
                </option>
              @endforeach
              @if(empty($kbCollections ?? []))
                <option value="default">default</option>
              @endif
            </select>
          </div>
          <div class="col-md-5">
            <label class="form-label">Label</label>
            <input type="text" name="label" class="form-control form-control-sm" placeholder="e.g. Stable release 2026-03-02">
          </div>
          <div class="col-md-2">
            <button class="btn btn-outline-primary w-100" type="submit">Publish</button>
          </div>
        </form>

        <div class="text-muted mt-2"><small>Tip: publish after you finish editing agent prompts/tools for audit-grade stability.</small></div>
      </div>
    </div>

    {{-- Test agent --}}
    <div class="card mb-3">
      <div class="card-header">
        <strong>Test agent</strong>
        <div class="text-muted"><small>Runs a safe, read-only test and shows which KB chunks were used.</small></div>
      </div>
      <div class="card-body">

        @if(empty($agents ?? []))
          <div class="alert alert-info mb-3">
            No agents found yet. Run <strong>Apply agent configuration</strong> first, or use <a href="{{ route('titan.core.ai.agents.index') }}">Agent Builder</a>.
          </div>
        @endif

        <form method="POST" action="{{ route('titan.core.ai.settings.test_agent') }}" class="row g-2 align-items-end">
          @csrf
          <div class="col-md-4">
            <label class="form-label">Agent</label>
            <select name="agent" class="form-select form-select-sm">
              @foreach(($agents ?? []) as $a)
                <option value="{{ $a['slug'] }}">{{ $a['title'] ?? $a['slug'] }}</option>
              @endforeach
              @if(empty($agents ?? []))
                <option value="default">default</option>
              @endif
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Input</label>
            <input type="text" name="input" class="form-control form-control-sm" placeholder="e.g. 3 bed, 2 bath bond clean in St Kilda">
          </div>
          <div class="col-md-2">
            <button class="btn btn-outline-secondary w-100" type="submit">Run test</button>
          </div>
        </form>

        @if(!empty($lastTest))
          <hr/>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="fw-semibold mb-1">Output</div>
              <pre class="p-2 bg-light rounded mb-0" style="white-space: pre-wrap;">{{ data_get($lastTest,'result.output','') }}</pre>
            </div>
            <div class="col-md-6">
              <div class="fw-semibold mb-1">Citations (KB chunks used)</div>
              <div class="table-responsive">
                <table class="table table-sm mb-0">
                  <thead><tr><th>Doc</th><th>Chunk</th><th>Score</th></tr></thead>
                  <tbody>
                    @foreach((data_get($lastTest,'result.citations', []) ?? []) as $c)
                      <tr>
                        <td class="text-muted"><small>{{ data_get($c,'doc','') }}</small></td>
                        <td><code>{{ data_get($c,'chunk_id','') }}</code></td>
                        <td>{{ data_get($c,'score','') }}</td>
                      </tr>
                    @endforeach
                    @if(empty(data_get($lastTest,'result.citations', [])))
                      <tr><td colspan="3" class="text-muted">No citations returned (no retrieval hits yet).</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        @endif

      </div>
    </div>

    {{-- Latest runs --}}
    <div class="card mb-3">
      <div class="card-header">
        <strong>Latest runs</strong>
        <div class="text-muted"><small>Background jobs launched from this page.</small></div>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>ID</th>
                <th>Type</th>
                <th>Status</th>
                <th>Embed</th>
                <th>Docs</th>
                <th>Chunks</th>
                <th>Started</th>
                <th>Finished</th>
                <th>Message</th>
              </tr>
            </thead>
            <tbody>
              @forelse(($latestRuns ?? []) as $r)
                <tr>
                  <td>{{ $r->id }}</td>
                  <td><code>{{ $r->run_type }}</code></td>
                  <td>
                    @if($r->status === 'success')
                      <span class="badge bg-success">{{ $r->status }}</span>
                    @elseif($r->status === 'failed')
                      <span class="badge bg-danger">{{ $r->status }}</span>
                    @elseif($r->status === 'running')
                      <span class="badge bg-warning text-dark">{{ $r->status }}</span>
                    @else
                      <span class="badge bg-secondary">{{ $r->status }}</span>
                    @endif
                  </td>
                  <td>@if($r->embed) ✅ @else — @endif</td>
                  <td>{{ $r->documents }}</td>
                  <td>{{ $r->chunks }}</td>
                  <td>{{ $r->started_at }}</td>
                  <td>{{ $r->finished_at }}</td>
                  <td class="text-muted"><small>{{ $r->message }}</small></td>
                </tr>
              @empty
                <tr><td colspan="9" class="text-muted">No runs yet.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Advanced --}}
    <div class="card mb-3">
      <div class="card-header">
        <strong>Advanced / recovery</strong>
        <div class="text-muted"><small>Useful for first-time install, CI, or recovery.</small></div>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="fw-semibold mb-1">Install / initialise</div>
            <pre class="p-2 bg-light rounded mb-0" style="white-space: pre-wrap;">php artisan migrate</pre>
          </div>
          <div class="col-md-6">
            <div class="fw-semibold mb-1">Background worker</div>
            <pre class="p-2 bg-light rounded mb-0" style="white-space: pre-wrap;">php artisan queue:work</pre>
          </div>
          <div class="col-md-6">
            <div class="fw-semibold mb-1">Manual sync</div>
            <pre class="p-2 bg-light rounded mb-0" style="white-space: pre-wrap;">php artisan titan:sync-agents
php artisan titan:kb:sync-titandocs --embed=0</pre>
          </div>
          <div class="col-md-6">
            <div class="fw-semibold mb-1">Embed rebuild</div>
            <pre class="p-2 bg-light rounded mb-0" style="white-space: pre-wrap;">php artisan titan:kb:sync-titandocs --embed=1</pre>
          </div>
        </div>
      </div>
    </div>

  </div>

  {{-- Sidebar --}}
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header">
        <strong>AI cost</strong>
        <div class="text-muted"><small>Usage in tokens + USD.</small></div>
      </div>
      <div class="card-body">
        <div class="row g-2 mb-2">
          <div class="col-6">
            <div class="text-muted"><small>Today</small></div>
            <div class="fs-5">${{ number_format(($aiCost['today_usd'] ?? 0), 4) }}</div>
            <div class="text-muted"><small>{{ (int)($aiCost['today_tokens'] ?? 0) }} tokens</small></div>
          </div>
          <div class="col-6">
            <div class="text-muted"><small>Last 7 days</small></div>
            <div class="fs-5">${{ number_format(($aiCost['last7_usd'] ?? 0), 4) }}</div>
            <div class="text-muted"><small>{{ (int)($aiCost['last7_tokens'] ?? 0) }} tokens</small></div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Agent</th><th class="text-end">7d USD</th><th class="text-end">Tokens</th></tr></thead>
            <tbody>
              @foreach(($aiCost['by_agent_7d'] ?? []) as $r)
                <tr>
                  <td><code>{{ $r->agent }}</code></td>
                  <td class="text-end">${{ number_format((float)$r->cost_usd, 4) }}</td>
                  <td class="text-end">{{ (int)$r->tokens }}</td>
                </tr>
              @endforeach
              @if(empty($aiCost['by_agent_7d'] ?? []))
                <tr><td colspan="3" class="text-muted">No usage logged yet.</td></tr>
              @endif
            </tbody>
          </table>
        </div>

        <div class="text-muted mt-2"><small>Pricing map: <code>Modules/TitanCore/Config/ai.php</code></small></div>
      </div>
    </div>

    
    <div class="card mb-3">
      <div class="card-header">
        <strong>Agents</strong>
        <div class="text-muted"><small>Configured agents and their knowledge scope.</small></div>
      </div>
      <div class="card-body">
        @if(empty($agents ?? []))
          <div class="text-muted">No agents yet. Run <strong>Apply agent configuration</strong> or use <a href="{{ route('titan.core.ai.agents.index') }}">Agent Builder</a>.</div>
        @else
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Agent</th>
                  <th>Collection</th>
                  <th class="text-end">Contract</th>
                </tr>
              </thead>
              <tbody>
                @foreach(($agents ?? []) as $a)
                  @php($contract = $agentContracts[$a['slug']] ?? null)
                  <tr>
                    <td>
                      <div class="fw-semibold">{{ $a['title'] ?? $a['slug'] }}</div>
                      <div class="text-muted"><small><code>{{ $a['slug'] }}</code>@if(!empty($a['is_active'])) • active @else • inactive @endif</small></div>
                    </td>
                    <td><code>{{ $a['kb_collection_key'] ?? 'default' }}</code></td>
                    <td class="text-end">
                      @if(!empty($contract) && !empty(data_get($contract,'version')))
                        <span class="badge bg-success">v{{ data_get($contract,'version') }}</span>
                      @else
                        <span class="badge bg-secondary">Not published</span>
                      @endif
                      <div class="mt-1">
                        <form method="POST" action="{{ route('titan.core.ai.settings.publish_agent_contract') }}">
                          @csrf
                          <input type="hidden" name="agent_slug" value="{{ $a['slug'] }}">
                          <button class="btn btn-outline-primary btn-sm" type="submit">Publish</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif

        <div class="text-muted mt-2"><small>
          Publishing creates a stable "contract" for prompts/tools.
          <a href="{{ route('titan.core.ai.agents.index') }}">Edit agents in Agent Builder →</a>
        </small></div>
      </div>
    </div>

  </div>
</div>
@endsection
