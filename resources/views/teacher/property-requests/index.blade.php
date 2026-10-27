@extends('layouts.app')

@section('title', 'My Property Requests | PARDS')
@section('page_title', 'My Property Requests')
@section('section_label', 'End-User Request Workspace')

@section('content')
    <div class="dashboard-card p-4">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="h4 mb-1">Property Request History</h2>
            </div>
            <a href="{{ route('teacher.property-requests.create') }}" class="btn btn-primary">
                <i class="bi bi-clipboard-plus me-2"></i>New Request
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Requested Item</th>
                        <th>Quantity</th>
                        <th>Needed By</th>
                        <th>Status</th>
                        <th>Reviewed By</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($propertyRequests as $propertyRequest)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $propertyRequest->requested_item_name }}</div>
                                <small class="badge text-bg-secondary">{{ $propertyRequest->request_type_label }}</small>
                                <small class="text-muted">{{ \Illuminate\Support\Str::limit($propertyRequest->purpose, 80) }}</small>
                            </td>
                            <td>{{ $propertyRequest->requested_quantity }}</td>
                            <td>{{ optional($propertyRequest->needed_by)->format('F d, Y') ?: 'N/A' }}</td>
                            <td>
                                <span class="badge text-bg-light border text-dark">
                                    {{ $propertyRequest->status_label }}
                                </span>
                                @if ($propertyRequest->assignment_id)
                                    <a class="btn btn-sm btn-outline-primary mt-2" href="{{ route('property-requests.receipt', $propertyRequest) }}">Print Receipt</a>
                                @endif
                            </td>
                            <td>{{ $propertyRequest->processedBy?->name ?? $propertyRequest->reviewedBy?->name ?? 'Pending review' }}</td>
                            <td>{{ optional($propertyRequest->created_at)->format('F d, Y h:i A') ?: 'N/A' }}</td>
                        </tr>
                        @if ($propertyRequest->response_notes)
                            <tr>
                                <td colspan="6" class="bg-light-subtle">
                                    <div class="small text-muted">Supply Office Notes</div>
                                    <div>{{ $propertyRequest->response_notes }}</div>
                                </td>
                            </tr>
                        @endif
                        @if ($propertyRequest->request_type === \App\Models\PropertyRequestRecord::TYPE_REPLACEMENT)
                            <tr>
                                <td colspan="6" class="bg-warning-subtle">
                                    <div class="small text-muted">Replacement issue</div>
                                    <div>{{ $propertyRequest->issue_description }}</div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No property requests submitted yet.</td>
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
