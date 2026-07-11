@extends('layouts.app')

@section('content')
<div class="container">
    <h3 class="mb-4">Titan Core – Usage Summary</h3>

    <div class="row mb-4 g-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5>Last 7 days</h5>
                    @if ($s7)
                        <p class="mb-1"><strong>Requests:</strong> {{ number_format($s7->requests) }}</p>
                        <p class="mb-1"><strong>Tokens in:</strong> {{ number_format($s7->tokens_in) }}</p>
                        <p class="mb-1"><strong>Tokens out:</strong> {{ number_format($s7->tokens_out) }}</p>
                        <p class="mb-1"><strong>Cost:</strong> ${{ number_format($s7->cost, 4) }}</p>
                        <p class="mb-0"><strong>Errors:</strong> {{ number_format($s7->errors) }}</p>
                    @else
                        <p class="text-muted mb-0">No data.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5>Last 30 days</h5>
                    @if ($s30)
                        <p class="mb-1"><strong>Requests:</strong> {{ number_format($s30->requests) }}</p>
                        <p class="mb-1"><strong>Tokens in:</strong> {{ number_format($s30->tokens_in) }}</p>
                        <p class="mb-1"><strong>Tokens out:</strong> {{ number_format($s30->tokens_out) }}</p>
                        <p class="mb-1"><strong>Cost:</strong> ${{ number_format($s30->cost, 4) }}</p>
                        <p class="mb-0"><strong>Errors:</strong> {{ number_format($s30->errors) }}</p>
                    @else
                        <p class="text-muted mb-0">No data.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="mb-3">Recent Requests</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Module</th>
                        <th>Operation</th>
                        <th>Model</th>
                        <th>Tokens</th>
                        <th>Cost</th>
                        <th>Status</th>
                        <th>When</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($recent as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>{{ $row->module }}</td>
                            <td>{{ $row->operation }}</td>
                            <td>{{ $row->model }}</td>
                            <td>{{ $row->tokens_in + $row->tokens_out }}</td>
                            <td>${{ number_format($row->cost, 4) }}</td>
                            <td>{{ $row->status }}</td>
                            <td>{{ $row->created_at }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-muted">No recent requests.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="mb-3">Usage by Module / Operation</h5>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                    <tr>
                        <th>Module</th>
                        <th>Operation</th>
                        <th>Requests</th>
                        <th>Tokens</th>
                        <th>Cost</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($byOp as $row)
                        <tr>
                            <td>{{ $row->module }}</td>
                            <td>{{ $row->operation }}</td>
                            <td>{{ $row->cnt }}</td>
                            <td>{{ $row->tokens }}</td>
                            <td>${{ number_format($row->cost, 4) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-muted">No usage data.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
