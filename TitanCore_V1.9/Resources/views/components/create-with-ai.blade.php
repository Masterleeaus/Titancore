<div class="card" id="titancore-create-with-ai">
  <div class="card-header"><strong>Create with AI</strong></div>
  <div class="card-body">
    <form id="titancore-create-form">
      <div class="mb-3">
        <label class="form-label">Title</label>
        <input type="text" class="form-control" name="title" placeholder="Title">
      </div>
      <div class="mb-3">
        <label class="form-label">Slug</label>
        <input type="text" class="form-control" name="slug" placeholder="unique-slug">
      </div>
      <div class="mb-3">
        <label class="form-label">Prompt Content</label>
        <textarea class="form-control" name="content" rows="5" placeholder="Write the system or user prompt here..."></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Create Prompt</button>
      <span id="titancore-create-status" class="ms-2"></span>
    </form>
  </div>
  <div class="card-footer">
    <small>Provider: <strong>{{ config('titancore.default_provider') }}</strong>. Daily cap: {{ config('titancore.quotas.per_tenant_daily_tokens') }}.</small>
  </div>
</div>

<script>
(function(){
  const form = document.getElementById('titancore-create-form');
  const status = document.getElementById('titancore-create-status');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    status.textContent = 'Saving...';
    const data = Object.fromEntries(new FormData(form).entries());
    try {
      const resp = await fetch('/api/titancore/prompts', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','Accept':'application/json'},
        body: JSON.stringify(data),
        credentials: 'same-origin'
      });
      if (resp.status === 201) {
        status.textContent = 'Created ✓';
        form.reset();
      } else {
        const j = await resp.json().catch(()=>({}));
        status.textContent = 'Error: ' + (j.error || JSON.stringify(j));
      }
    } catch (err) {
      status.textContent = 'Network error';
    }
  });
})();
</script>
