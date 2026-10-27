@extends('layouts.app')

@section('title', 'QR Codes | PARDS')
@section('page_title', 'QR Codes')
@section('section_label', 'QR Code Module')

@section('content')
    <div class="dashboard-card p-4">
        <div class="mb-4">
            <h2 class="h4 mb-1">Property Unit QR Codes</h2>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Property Code</th>
                        <th>Unit Serial Number</th>
                        <th>Property Name</th>
                        <th>Assigned To</th>
                        <th>Unit Status</th>
                        <th class="text-end">QR Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($propertyUnits as $propertyUnit)
                        <tr>
                            <td class="fw-semibold">{{ $propertyUnit->property?->property_code }}</td>
                            <td>{{ $propertyUnit->serial_number }}</td>
                            <td>{{ $propertyUnit->property?->property_name }}</td>
                            <td>{{ $propertyUnit->assignment?->assignee?->name ?: 'Unassigned' }}</td>
                            <td>
                                <span class="badge text-bg-light border text-dark">
                                    {{ ucfirst(str_replace('_', ' ', $propertyUnit->status)) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <a href="{{ route('qr.units.show', $propertyUnit) }}" class="btn btn-sm btn-outline-primary">View QR</a>
                                    <a href="{{ route('qr.units.print', $propertyUnit) }}" class="btn btn-sm btn-outline-success" target="_blank">Print Sticker</a>
                                    <a href="{{ route('qr.units.download', $propertyUnit) }}" class="btn btn-sm btn-primary">Download</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No property unit QR codes found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $propertyUnits->links() }}
        </div>
    </div>
@endsection
