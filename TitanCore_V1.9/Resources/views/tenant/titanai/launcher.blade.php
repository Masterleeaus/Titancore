@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col">
            <h3 class="mb-1">Titan Tools</h3>
            <div class="text-muted">Use Titan AI features inside Worksuite via TitanCore gateway.</div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Chat</h5>
                    <p class="text-muted">Start a conversation or send a message.</p>
                    <button class="btn btn-primary w-100" onclick="preset('POST','/api/chat/send-message', {message: 'Hello Titan'})">Open</button>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">AI Writer</h5>
                    <p class="text-muted">Generate text outputs (emails, quotes, notes).</p>
                    <button class="btn btn-primary w-100" onclick="preset('POST','/api/aiwriter/generate', {prompt: 'Write a quote follow up email'})">Open</button>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">AI Image</h5>
                    <p class="text-muted">Generate images (logos, marketing, job photos edits).</p>
                    <button class="btn btn-primary w-100" onclick="preset('POST','/api/aiimage/generate', {prompt: 'Create a tradie logo'})">Open</button>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Documents</h5>
                    <p class="text-muted">Summarise or generate documents.</p>
                    <button class="btn btn-primary w-100" onclick="preset('POST','/api/documents/summarize', {text: 'Paste text here'})">Open</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Method</label>
                    <select class="form-select" id="method">
                        <option>GET</option>
                        <option selected>POST</option>
                        <option>PUT</option>
                        <option>PATCH</option>
                        <option>DELETE</option>
                    </select>
                </div>
                <div class="col-md-10">
                    <label class="form-label">Titan AI Path</label>
                    <input class="form-control" id="path" value="/api/health" placeholder="/api/..." />
                    <div class="form-text">This path is forwarded to Titan AI. Example: /api/chat/send-message</div>
                </div>
                <div class="col-12">
                    <label class="form-label">JSON Payload</label>
                    <textarea class="form-control" rows="7" id="payload">{}</textarea>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-success" type="button" onclick="run()">Run</button>
                    <button class="btn btn-outline-secondary" type="button" onclick="ping()">Ping</button>
                </div>
                <div class="col-12">
                    <label class="form-label">Response</label>
                    <pre class="bg-light p-3 rounded" id="response" style="min-height:260px; white-space:pre-wrap;"></pre>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function preset(method, path, payload){
    document.getElementById('method').value = method;
    document.getElementById('path').value = path;
    document.getElementById('payload').value = JSON.stringify(payload || {}, null, 2);
    document.getElementById('response').textContent = '';
}

async function ping(){
    const box = document.getElementById('response');
    box.textContent = 'Pinging...';
    try{
        const r = await fetch("{{ route('titancore.api.titanai.ping') }}", { headers: {'Accept':'application/json'} });
        const j = await r.json();
        box.textContent = JSON.stringify(j, null, 2);
    } catch(e){ box.textContent = String(e); }
}

async function run(){
    const method = document.getElementById('method').value;
    const path = document.getElementById('path').value;
    const payloadRaw = document.getElementById('payload').value || '{}';
    const box = document.getElementById('response');
    box.textContent = 'Running...';

    let payload = {};
    try { payload = JSON.parse(payloadRaw); } catch(e){
        box.textContent = 'Invalid JSON: ' + e;
        return;
    }

    const url = "{{ route('titancore.api.titanai.proxy') }}" + "?path=" + encodeURIComponent(path);

    try{
        const r = await fetch(url, {
            method: method,
            headers: {'Content-Type':'application/json', 'Accept':'application/json'},
            body: (method === 'GET' || method === 'DELETE') ? null : JSON.stringify(payload)
        });
        const j = await r.json();
        box.textContent = JSON.stringify(j, null, 2);
    } catch(e){
        box.textContent = String(e);
    }
}
</script>
@endsection
