@extends('layouts.app')

@section('title', 'Assigned Items Report | PARDS')
@section('page_title', 'Assigned Items Report')
@section('section_label', 'Report Center')

@section('content')
    <div class="dashboard-card p-4">
        @include('reports.partials.print-header')
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
            <div>
                <h2 class="h4 mb-1">Assigned Items Report</h2>
            </div>
            <div class="d-flex flex-wrap gap-2 report-actions">
                <button type="button" onclick="window.print()" class="btn btn-outline-secondary">Print</button>
                <a href="{{ route('reports.assignments.csv') }}" class="btn btn-outline-success">CSV Export</a>
                <a href="{{ route('reports.assignments.pdf') }}" class="btn btn-primary">Download PDF</a>
            </div>
        </div>

        <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Property Code</th>
                            <th>Serial Number</th>
                            <th>Property Name</th>
                            <th>Teacher</th>
                        <th>Quantity</th>
                        <th>Date Assigned</th>
                        <th>Assigned By</th>
                        <th>Status</th>
                    </tr>
                </thead>
                    <tbody>
                        @forelse ($assignments as $assignment)
                            <tr>
                            <td>{{ $assignment->property?->property_code }}</td>
                            <td>{{ $assignment->unit_serial_numbers ?: 'N/A' }}</td>
                            <td>{{ $assignment->property?->property_name }}</td>
                            <td>{{ $assignment->teacher?->name ?: 'N/A' }}</td>
                            <td>{{ $assignment->quantity_assigned }}</td>
                            <td>{{ optional($assignment->date_assigned)->format('F d, Y') }}</td>
                            <td>{{ $assignment->assignedBy?->name ?: 'N/A' }}</td>
                            <td>{{ ucfirst($assignment->status) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">No assignment records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('reports.partials.print-signatories')
    </div>
@endsection
