@extends('layouts.app')

@section('title', 'Disposal Report | PARDS')
@section('page_title', 'Disposal Report')
@section('section_label', 'Report Center')

@section('content')
    <div class="dashboard-card p-4">
        @include('reports.partials.print-header')
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
            <div>
                <h2 class="h4 mb-1">Disposal Report</h2>
            </div>
            <div class="d-flex flex-wrap gap-2 report-actions">
                <button type="button" onclick="window.print()" class="btn btn-outline-secondary">Print</button>
                <a href="{{ route('reports.disposals.csv') }}" class="btn btn-outline-success">CSV Export</a>
                <a href="{{ route('reports.disposals.pdf') }}" class="btn btn-primary">Download PDF</a>
            </div>
        </div>

        <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Property Code</th>
                            <th>Property Name</th>
                            <th>Quantity Disposed</th>
                        <th>Disposal Date</th>
                        <th>Method</th>
                        <th>Disposed By</th>
                        <th>Status</th>
                    </tr>
                </thead>
                    <tbody>
                        @forelse ($disposals as $disposal)
                            <tr>
                            <td>{{ $disposal->property?->property_code }}</td>
                            <td>{{ $disposal->property?->property_name }}</td>
                            <td>{{ $disposal->quantity_disposed }}</td>
                            <td>{{ optional($disposal->disposal_date)->format('F d, Y') }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $disposal->disposal_method)) }}</td>
                            <td>{{ $disposal->disposedBy?->name ?: 'N/A' }}</td>
                            <td>{{ ucfirst($disposal->status) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No disposal records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('reports.partials.print-signatories')
    </div>
@endsection
