@extends('layouts.app')

@section('content')
<div class="container">
    <h3 class="mb-4">Titan Core – AI Dashboard</h3>

    <div class="row mb-4 g-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5>Tokens (last 7 days)</h5>
                    <p class="h3 mb-0">{{ number_format($usageLast7) }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5>Tokens (last 30 days)</h5>
                    <p class="h3 mb-0">{{ number_format($usageLast30) }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5>Top Modules (last 30 days)</h5>
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Module</th>
                            <th class="text-end">Tokens</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($usageByModule as $row)
                            <tr>
                                <td>{{ $row->module ?? 'Unknown' }}</td>
                                <td class="text-end">{{ number_format($row->tokens) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-muted">No data yet.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5>Top Models (last 30 days)</h5>
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Model</th>
                            <th class="text-end">Tokens</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($usageByModel as $row)
                            <tr>
                                <td>{{ $row->model ?? 'Unknown' }}</td>
                                <td class="text-end">{{ number_format($row->tokens) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-muted">No data yet.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Optional: add a chart for $timeseries using Chart.js later --}}
</div>
@endsection
