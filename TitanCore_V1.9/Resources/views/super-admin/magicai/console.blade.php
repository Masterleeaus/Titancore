@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col">
            <h3 class="mb-1">Titan Core – MagicAI Console</h3>
            <div class="text-muted">Proxy any MagicAI API route through TitanCore (permission gated).</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form id="magicaiConsoleForm" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Method</label>
                    <select class="form-select" name="method" id="method">
                        <option>GET</option>
                        <option selected>POST</option>
                        <option>PUT</option>
                        <option>PATCH</option>
                        <option>DELETE</option>
                    </select>
                </div>
                <div class="col-md-10">
                    <label class="form-label">MagicAI Path</label>
                    <input class="form-control" name="path" id="path" placeholder="/api/chat/send-message" value="/api/health" />
                    <div class="form-text">Example: /api/chat/send-message, /api/aiwriter/generate, /api/aiimage/generate</div>
                </div>

                <div class="col-12">
                    <label class="form-label">JSON Payload</label>
                    <textarea class="form-control" rows="6" id="payload" placeholder='{"message":"Hello"}'>{}</textarea>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="button" class="btn btn-primary" onclick="runProxy()">Run</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="pingMagicAI()">Ping</button>
                </div>

                <div class="col-12">
                    <label class="form-label">Response</label>
                    <pre class="bg-light p-3 rounded" id="response" style="min-height:220px; white-space:pre-wrap;"></pre>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
async function pingMagicAI() {
    const resBox = document.getElementById('response');
    resBox.textContent = 'Pinging...';
    try {
        const r = await fetch("{{ route('titancore.api.magicai.ping') }}", { headers: { 'Accept': 'application/json' } });
        const j = await r.json();
        resBox.textContent = JSON.stringify(j, null, 2);
    } catch (e) {
        resBox.textContent = String(e);
    }
}

async function runProxy() {
    const method = document.getElementById('method').value;
    const path = document.getElementById('path').value;
    const payloadRaw = document.getElementById('payload').value || '{}';

    const resBox = document.getElementById('response');
    resBox.textContent = 'Running...';

    let payload = {};
    try { payload = JSON.parse(payloadRaw); } catch (e) {
        resBox.textContent = 'Invalid JSON payload: ' + e;
        return;
    }

    const url = "{{ route('titancore.api.magicai.proxy') }}" + "?path=" + encodeURIComponent(path);

    try {
        const r = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: (method === 'GET' || method === 'DELETE') ? null : JSON.stringify(payload),
        });
        const j = await r.json();
        resBox.textContent = JSON.stringify(j, null, 2);
    } catch (e) {
        resBox.textContent = String(e);
    }
}
</script>
@endsection
