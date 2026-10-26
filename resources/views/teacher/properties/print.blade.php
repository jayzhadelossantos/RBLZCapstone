<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accountability Record</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #000; background: #fff; font: 12px Arial, sans-serif; line-height: 1.4; }
        .page { max-width: 1100px; margin: auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header p { margin: 2px 0; }
        h1 { font-size: 19px; margin: 12px 0 4px; }
        .meta { margin-bottom: 20px; }
        .meta p { margin: 5px 0; }
        .records { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 11px; }
        .records th, .records td { border: 1px solid #000; padding: 7px 5px; text-align: left; vertical-align: top; overflow-wrap: anywhere; word-wrap: break-word; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; page-break-inside: avoid; }
        .empty { text-align: center !important; padding: 28px !important; }
        .acknowledgment { margin-top: 24px; break-inside: avoid; page-break-inside: avoid; }
        .signatures { width: 100%; margin-top: 24px; }
        .signatures td { width: 50%; vertical-align: top; padding-right: 40px; }
        .signature-line { margin-top: 48px; border-top: 1px solid #000; padding-top: 5px; text-align: center; }
        .signature-caption { text-align: center; font-size: 11px; }
        .date-line { margin-top: 18px; }
        .print-actions { max-width: 1060px; margin: 20px auto; padding: 20px; font-size: 14px; background: #eef3f8; border: 1px solid #d9e3ee; border-radius: 16px; box-shadow: 0 8px 24px rgba(24, 49, 83, 0.08); }
        .action-buttons { display: flex; flex-wrap: wrap; gap: 10px; }
        .print-actions a, .print-actions button { display: inline-flex; align-items: center; justify-content: center; gap: 10px; min-height: 48px; border: 1px solid transparent; border-radius: 10px; padding: 12px 18px; color: #fff; background: #183153; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; transition: transform 160ms ease, box-shadow 160ms ease, filter 160ms ease; touch-action: manipulation; }
        .print-actions .action-print { color: #10253f; background: #d9a441; }
        .print-actions .action-back { color: #183153; background: #d9e8f5; border-color: #bacfe1; }
        .action-buttons > * { flex: 1 1 auto; }
        .action-buttons svg { width: 20px; height: 20px; flex-shrink: 0; }
        .print-actions form { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        .print-actions label { color: #183153; font-weight: 600; }
        .print-actions select { padding: 10px; border: 1px solid #bacfe1; border-radius: 8px; font-size: 16px; }

        .print-actions a:focus-visible, .print-actions button:focus-visible, .print-actions select:focus-visible { outline: 3px solid #183153; outline-offset: 3px; }
        .print-actions a:active, .print-actions button:active { transform: translateY(1px); filter: brightness(0.92); box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.15); }
        @media (prefers-reduced-motion: reduce) {
            .print-actions a, .print-actions button { transition: none; transform: none !important; }
        }
        @media screen and (max-width: 767px) {
            .print-actions { margin: 12px; padding: 14px; }
            .page { padding: 16px; }
            .records-scroll { overflow-x: auto; }
            .records { min-width: 950px; }
            .action-buttons > * { flex: 1 1 100%; }
            .signatures td { padding-right: 16px; }
        }
        @media print {
            .print-actions { display: none !important; }
            .page { max-width: none; padding: 0; margin: 0; }
            .records-scroll { overflow: visible; }
            .records { min-width: 0; }
        }
    </style>
</head>
<body>
    @unless ($isPdf)
        <div class="print-actions">
            @if ($admins->count() > 1)
                <form method="GET" action="{{ route('teacher.properties.print') }}" style="margin-bottom: 16px;">
                    <label for="verified_by">Verifying administrator:</label>
                    <select name="verified_by" id="verified_by" onchange="this.form.submit()" required style="min-height: 44px; max-width: 100%;">
                        @foreach ($admins as $admin)
                            <option value="{{ $admin->id }}" @selected($verifyingAdmin?->id === $admin->id)>{{ $admin->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit">Apply</button>
                </form>
            @endif
            <div class="action-buttons">
                <a href="{{ route('teacher.properties.print', ['pdf' => 1, 'verified_by' => $verifyingAdmin?->id]) }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/></svg>
                    Download Printable PDF
                </a>
                <button type="button" class="action-print" onclick="window.print()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8V3h12v5M6 17H3V8h18v9h-3M6 14h12v7H6zM17 11h1"/></svg>
                    Print Form
                </button>
                <a class="action-back" href="{{ route('teacher.properties.index') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m10 5-7 7 7 7M3 12h18"/></svg>
                    Back to My Accountabilities
                </a>
            </div>
        </div>
    @endunless
    <main class="page">
        <div class="header">
            @include('partials.print-logo')
            <p><strong>ABANON NATIONAL HIGH SCHOOL</strong></p>
            <p>San Carlos City, Pangasinan</p>
            <h1>PROPERTY ACCOUNTABILITY RECORD</h1>
            <p>List of Currently Assigned Properties</p>
        </div>
        <div class="meta">
            <p><strong>Accountable End-User:</strong> {{ $teacher->name }}</p>
            <p><strong>Email:</strong> {{ $teacher->email }}</p>
            <p><strong>Employee Number:</strong> {{ $teacher->employee_number ?: 'Not yet provided' }}</p>
            <p><strong>Date Prepared:</strong> {{ $printedAt->format('F d, Y') }}</p>
        </div>
        <div class="records-scroll">
            <table class="records">
                <thead>
                    <tr>
                        <th>Property Code</th><th>Serial Number</th><th>Property Name</th>
                        <th>Category</th><th>Quantity</th>
                        <th>Condition</th><th>Date Assigned</th><th>Assigned By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($properties as $property)
                        @foreach ($property->assignments as $assignment)
                            <tr>
                                <td>{{ $property->property_code }}</td>
                                <td>{{ $assignment?->unit_serial_numbers ?: 'N/A' }}</td>
                                <td>{{ $property->property_name }}</td>
                                <td>{{ $property->category ?: 'N/A' }}</td>
                                <td>{{ $assignment->quantity_assigned }} {{ $property->unit }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $property->condition_status)) }}</td>
                                <td>{{ optional($assignment->date_assigned)->format('M d, Y') ?: 'N/A' }}</td>
                                <td>{{ $assignment->assignedBy?->name ?: 'N/A' }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="8" class="empty">No assigned properties found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="acknowledgment">
            <p>I acknowledge that the properties listed above are recorded under my accountability and undertake to exercise proper care and safekeeping of these items.</p>
            <table class="signatures">
                <tr>
                    <td>
                        <strong>Acknowledged by:</strong>
                        <div class="signature-line">{{ $teacher->name }}</div>
                        <div class="signature-caption">Signature over Printed Name of Accountable End-User</div>
                        <div class="date-line">Date: ____________________</div>
                    </td>
                    <td>
                        <strong>Verified by:</strong>
                        <div class="signature-line">{{ $verifyingAdmin?->name ?? 'School Administrator' }}</div>
                        <div class="signature-caption">Signature over Printed Name of Administrator</div>
                        <div class="date-line">Date: ____________________</div>
                    </td>
                </tr>
            </table>
        </div>
    </main>
</body>
</html>
