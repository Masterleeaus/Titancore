@extends('layouts.app')
@section('content')
<div class="container">
  <h1 class="mb-3">AI Prompts</h1>
  <table class="table table-sm">
    <thead><tr><th>Namespace</th><th>Slug</th><th>Version</th><th>Locale</th><th></th></tr></thead>
    <tbody>
      @foreach($prompts as $p)
        <tr>
          <td>{{ $p->namespace }}</td>
          <td>{{ $p->slug }}</td>
          <td>{{ $p->version }}</td>
          <td>{{ $p->locale }}</td>
          <td><a href="{{ route('titancore.prompts.edit', [$p->namespace,$p->slug]) }}" class="btn btn-sm btn-primary">Edit</a></td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
@include('titancore::components.create-with-ai')
