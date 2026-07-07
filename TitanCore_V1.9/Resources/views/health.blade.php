<div class="titancore-health">
  <h1>Titan Core — Health</h1>
  <p>Active provider: <strong>{{ config('titancore.default_provider') }}</strong></p>
  <p>Status: <span>OK</span></p>

  <div id="titancore-usage-mini" style="margin-top:1rem;">
    <h3>Usage (last 14 days)</h3>
    <pre id="titancore-usage-json">loading…</pre>
  </div>
  <script>
  (async function(){
    try {
      const since = new Date(Date.now() - 13*24*3600*1000).toISOString().slice(0,10);
      const today = new Date().toISOString().slice(0,10);
      const r = await fetch(`/api/titancore/usage?start=${since}&end=${today}`, {credentials: 'same-origin'});
      const j = await r.json();
      document.getElementById('titancore-usage-json').textContent = JSON.stringify(j.data, null, 2);
    } catch(e) {
      document.getElementById('titancore-usage-json').textContent = 'error';
    }
  })();
  </script>
</div>

