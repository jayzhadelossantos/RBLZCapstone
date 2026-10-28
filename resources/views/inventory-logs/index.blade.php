@extends('layouts.app')

@section('title', 'Inventory History | PARDS')
@section('page_title', 'Inventory History')
@section('section_label', 'Supply Office Inventory Audit')

@section('content')
    <div class="dashboard-card p-4">
        <div class="mb-4"><h2 class="h4 mb-1">Inventory History</h2><div class="text-muted">Search every stock reservation, release, and fulfilled request.</div></div>
        <form method="GET" class="row g-2 mb-4">
            <div class="col-md-7"><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Search item name or property code"></div>
            <div class="col-md-3"><select name="action" class="form-select"><option value="">All actions</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ ucfirst(str_replace('_', ' ', $action)) }}</option>@endforeach</select></div>
            <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-primary">Filter</button><a href="{{ route('inventory-logs.index') }}" class="btn btn-outline-secondary">Reset</a></div>
        </form>
        <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Date</th><th>Item</th><th>Action</th><th>Change</th><th>Available After</th><th>Request / User</th></tr></thead><tbody>
        @forelse($logs as $log)
            <tr><td>{{ $log->created_at->format('M d, Y h:i A') }}</td><td><div class="fw-semibold">{{ $log->property->property_name }}</div><small class="text-muted">{{ $log->property->property_code }}</small></td><td><span class="badge text-bg-light border text-dark">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</span></td><td class="{{ $log->quantity_change < 0 ? 'text-danger' : 'text-success' }} fw-semibold">{{ $log->quantity_change > 0 ? '+' : '' }}{{ $log->quantity_change }}</td><td>{{ $log->available_after }} {{ $log->property->unit }}</td><td>@if($log->property_request_id) Request #{{ $log->property_request_id }}<br>@endif<small class="text-muted">{{ $log->user?->name ?: $log->propertyRequest?->requester?->name ?: 'System' }}</small></td></tr>
        @empty <tr><td colspan="6" class="text-center py-5 text-muted">No inventory history found.</td></tr>@endforelse
        </tbody></table></div>
        <div class="mt-3">{{ $logs->links() }}</div>
    </div>
@endsection
