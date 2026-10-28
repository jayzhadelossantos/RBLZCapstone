@extends('layouts.app')

@section('title', 'Available Inventory | PARDS')
@section('page_title', 'Available Inventory')
@section('section_label', 'End-User Request Workspace')

@section('content')
    <div class="dashboard-card p-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
            <div><h2 class="h4 mb-1">Available Items</h2><div class="text-muted">Choose an item to start a property request.</div></div>
            <a href="{{ route('teacher.property-requests.index') }}" class="btn btn-outline-secondary align-self-lg-start">My Requests</a>
        </div>
        <form method="GET" class="row g-2 mb-4">
            <div class="col-md-6"><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Search item name, code, or brand"></div>
            <div class="col-md-3"><select name="category" class="form-select"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>@endforeach</select></div>
            <div class="col-md-3 d-flex gap-2"><button class="btn btn-outline-primary"><i class="bi bi-funnel me-1"></i>Filter</button><a href="{{ route('teacher.inventory.index') }}" class="btn btn-outline-secondary">Reset</a></div>
        </form>
        <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Item</th><th>Code</th><th>Category</th><th>Available</th><th></th></tr></thead><tbody>
        @forelse($inventory as $item)
            <tr><td class="fw-semibold">{{ $item->property->property_name }}</td><td>{{ $item->property->property_code }}</td><td>{{ $item->property->category ?: 'N/A' }}</td><td>{{ $item->available_quantity }} {{ $item->property->unit }}</td><td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('teacher.property-requests.create', ['inventory_id' => $item->id]) }}">Request item</a></td></tr>
        @empty <tr><td colspan="5" class="text-center py-5 text-muted">No available items match this filter.</td></tr>@endforelse
        </tbody></table></div>
        <div class="mt-3">{{ $inventory->links() }}</div>
    </div>
@endsection
