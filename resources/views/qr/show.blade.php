@extends('layouts.app')

@section('title', 'QR Code | PARDS')
@section('page_title', 'Property QR Code')
@section('section_label', 'QR Code Module')

@section('content')
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="dashboard-card p-4 h-100 text-center">
                <h2 class="h4 mb-3">{{ $property->property_name }}</h2>
                <div class="border rounded-4 bg-white p-4 mb-3">
                    {!! $qrSvg !!}
                </div>

                <div class="d-grid gap-2">
                    <a href="{{ route('qr.print', $property) }}" class="btn btn-success" target="_blank">
                        <i class="bi bi-printer me-2"></i>Print Property Sticker
                    </a>
                    <a href="{{ route('qr.download', $property) }}" class="btn btn-primary">
                        <i class="bi bi-download me-2"></i>Download QR Code
                    </a>
                    <a href="{{ $scanUrl }}" class="btn btn-outline-secondary" target="_blank">
                        <i class="bi bi-box-arrow-up-right me-2"></i>Open Scan Page
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="dashboard-card p-4 h-100">
                <h3 class="h5 mb-4">QR Code Details</h3>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded-4 p-3">
                            <div class="text-muted small">Property Name</div>
                            <div class="fw-semibold">{{ $property->property_name }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-4 p-3">
                            <div class="text-muted small">Property Code</div>
                            <div class="fw-semibold">{{ $property->property_code }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-4 p-3">
                            <div class="text-muted small">Serial Number</div>
                            <div class="fw-semibold">{{ $property->serial_number ?: 'N/A' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-4 p-3">
                            <div class="text-muted small">Assigned Teacher/Person</div>
                            <div class="fw-semibold">{{ $activeAssignment?->teacher?->name ?? 'Unassigned' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-4 p-3">
                            <div class="text-muted small">Status</div>
                            <div class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $property->status)) }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-4 p-3">
                            <div class="text-muted small">Condition</div>
                            <div class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $property->condition_status)) }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-4 p-3">
                            <div class="text-muted small">Location</div>
                            <div class="fw-semibold">{{ $activeAssignment?->location ?: 'N/A' }}</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded-4 p-3">
                            <div class="text-muted small">QR Encoded Content</div>
                            <pre class="fw-semibold mb-0 text-wrap" style="white-space: pre-wrap;">{{ $qrPayload }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
