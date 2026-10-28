@extends('layouts.app')

@section('title', 'New Property Request | PARDS')
@section('page_title', 'New Property Request')
@section('section_label', 'End-User Request Workspace')

@section('content')
    <div class="dashboard-card p-4">
        <div class="mb-4">
            <h2 class="h4 mb-1">Submit Property Request</h2>
        </div>

        <form method="POST" action="{{ route('teacher.property-requests.store') }}">
            @include('teacher.property-requests.partials.form', ['submitLabel' => 'Submit Request'])
        </form>
    </div>
@endsection
