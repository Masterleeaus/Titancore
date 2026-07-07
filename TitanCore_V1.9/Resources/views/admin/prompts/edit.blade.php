@extends('layouts.app')
@section('content')
<div class="container">
  <h1 class="mb-3">Edit Prompt: {{ $namespace }} / {{ $slug }}</h1>
  <div class="row">
    <div class="col-md-6">
      <h4>Versions</h4>
      <ul class="list-group">
        @foreach($versions as $v)
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <span>v{{ $v->version }} ({{ $v->locale }}) — {{ $v->created_at }}</span>
          </li>
        @endforeach
      </ul>
    </div>
    <div class="col-md-6">
      <h4>New Version</h4>
      <form method="post" action="{{ route('titancore.prompts.storeVersion', [$namespace,$slug]) }}">
        @csrf
        <div class="mb-2">
          <label class="form-label">Locale</label>
          <input name="locale" class="form-control" value="en">
        </div>
        <div class="mb-2">
          <label class="form-label">Content</label>
          <textarea name="content" class="form-control" rows="12"></textarea>
        </div>
        <button class="btn btn-primary">Create Version</button>
      </form>
    </div>
  </div>
</div>
@endsection