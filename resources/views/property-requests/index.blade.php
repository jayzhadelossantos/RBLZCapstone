@extends('layouts.app')

@section('title', 'Confirm Property Requests | PARDS')
@section('page_title', 'Property Requests')
@section('section_label', 'Supply Office Request Confirmation')

@section('content')
    <div class="dashboard-card p-4">
        <div class="mb-4">
            <h2 class="h4 mb-1">Teacher Property Requests</h2>
            <p class="text-muted mb-0">Open a request to review all items submitted under one reference number.</p>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="{{ route('property-requests.index') }}" class="btn btn-sm btn-outline-primary">All Requests</a>
            @foreach (['pending' => 'Staff Review', 'awaiting_admin' => 'Admin Approval', 'awaiting_stock' => 'Awaiting Stock', 'approved' => 'Ready for Assignment', 'fulfilled' => 'Assigned'] as $value => $label)
                @if ($value !== 'pending' || auth()->user()->isStaff())
                    <a href="{{ route('property-requests.index', ['status' => $value]) }}" class="btn btn-sm {{ request('status') === $value ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
                @endif
            @endforeach
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Requester</th>
                        <th>Requested Items</th>
                        <th>Total Qty</th>
                        <th>Needed By</th>
                        <th>Status</th>
                        <th>Processed By</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($propertyRequests as $propertyRequest)
                        <tr>
                            <td class="fw-semibold text-nowrap">{{ $propertyRequest->reference_number }}</td>
                            <td>
                                <a href="{{ route('property-requests.show', $propertyRequest) }}" class="fw-semibold">{{ $propertyRequest->requester?->name ?: 'N/A' }}</a>
                                <small class="text-muted d-block">{{ $propertyRequest->requester?->email ?: 'N/A' }}</small>
                            </td>
                            <td>
                                <ul class="list-unstyled mb-0">
                                    @foreach ($propertyRequest->items as $item)
                                        <li>
                                            <span class="fw-semibold">{{ $item->requested_item_name }}</span>
                                            <span class="text-muted">× {{ $item->requested_quantity }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                            <td>{{ $propertyRequest->requested_quantity }}</td>
                            <td>{{ optional($propertyRequest->needed_by)->format('F d, Y') ?: 'N/A' }}</td>
                            <td>
                                <span class="badge text-bg-light border text-dark">
                                    {{ $propertyRequest->status_label }}
                                </span>
                            </td>
                            <td>{{ $propertyRequest->processedBy?->name ?: 'Pending review' }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('property-requests.show', $propertyRequest) }}" class="btn btn-sm btn-outline-primary">{{ auth()->user()->isStaff() && $propertyRequest->status === 'approved' ? 'Assign Property' : 'Open Request' }}</a>
                                <a href="{{ route('property-requests.pdf', $propertyRequest) }}" class="btn btn-sm btn-outline-secondary">PDF</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">No property requests found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $propertyRequests->links() }}
        </div>
    </div>
@endsection
