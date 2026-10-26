<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Receiving Copy | PARDS</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #000;
            background: #fff;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            line-height: 1.45;
        }

        .page {
            width: 8.5in;
            min-height: 11in;
            margin: 0 auto;
            padding: .55in;
        }

        .print-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-bottom: 18px;
        }

        .print-actions button,
        .print-actions a {
            border: 1px solid #000;
            color: #000;
            background: #fff;
            padding: 8px 12px;
            font: inherit;
            text-decoration: none;
            cursor: pointer;
        }

        .header {
            text-align: center;
            margin-bottom: 18px;
        }

        .header h1,
        .header h2,
        .header p {
            margin: 0;
        }

        .header h1 {
            font-size: 16px;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .header h2 {
            margin-top: 12px;
            font-size: 15px;
            text-transform: uppercase;
        }

        .meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 24px;
            margin-bottom: 18px;
        }

        .field {
            border-bottom: 1px solid #000;
            min-height: 22px;
            padding: 2px 0;
        }

        .label {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 18px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 7px;
            text-align: left;
            vertical-align: top;
        }

        th {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 10px;
        }

        .acknowledgement {
            margin: 20px 0 34px;
            text-align: justify;
        }

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 54px;
            margin-top: 42px;
        }

        .signature-line {
            border-top: 1px solid #000;
            padding-top: 6px;
            text-align: center;
            min-height: 48px;
        }

        .signature-role {
            font-size: 10px;
            text-transform: uppercase;
        }

        .copy-note {
            margin-top: 34px;
            font-size: 10px;
            text-align: center;
        }

        @media print {
            @page {
                size: letter;
                margin: .45in;
            }

            .page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
            }

            .print-actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="print-actions">
            @if (isset($admins) && $admins->count() > 1 && !isset($requestNumber))
                <form method="GET" action="{{ route('assignments.print', $assignment) }}" style="display: inline-flex; align-items: center; gap: 8px; margin-right: 12px;">
                    <label for="verified_by">Admin / Supply Officer:</label>
                    <select name="verified_by" id="verified_by" onchange="this.form.submit()" style="padding: 6px 10px; border: 1px solid #bacfe1; border-radius: 6px;">
                        @foreach ($admins as $admin)
                            <option value="{{ $admin->id }}" @selected(($verifyingAdmin?->id ?? null) === $admin->id)>{{ $admin->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
            <button type="button" onclick="window.print()">Print</button>
            <a href="{{ $backUrl ?? route('assignments.show', $assignment) }}">Back</a>
        </div>

        <header class="header">
            @include('partials.print-logo')
            <h1>Abanon National High School</h1>
            <p>San Carlos City, Pangasinan</p>
            <h2>Property Receiving Copy</h2>
            <p>Property Accountability Records and Disposal System</p>
            @isset ($requestNumber)
                <p>Property Request #{{ $requestNumber }} | Approved by: {{ $approvedBy?->name ?? $verifyingAdmin?->name ?? 'Supply Office Administrator' }}</p>
            @endisset
        </header>

        <section class="meta">
            <div>
                <div class="label">Department</div>
                <div class="field">{{ $assignment->department ?: 'Not yet provided' }}</div>
            </div>
            <div>
                <div class="label">Assignment No.</div>
                <div class="field">#{{ $assignment->id }}</div>
            </div>
            <div>
                <div class="label">Date Printed</div>
                <div class="field">{{ $printedAt->format('F d, Y h:i A') }}</div>
            </div>
            <div>
                <div class="label">Received By</div>
                <div class="field">{{ $assignment->assignee?->name ?: 'N/A' }}</div>
            </div>
            <div>
                <div class="label">Role</div>
                <div class="field">{{ ucfirst($assignment->assignee?->role ?? 'N/A') }}</div>
            </div>
            <div>
                <div class="label">Assigned By</div>
                <div class="field">{{ $assignment->assignedBy?->name ?: 'N/A' }}</div>
            </div>
            <div>
                <div class="label">Date Assigned</div>
                <div class="field">{{ optional($assignment->date_assigned)->format('F d, Y') ?: 'N/A' }}</div>
            </div>
        </section>

        <table>
            <thead>
                <tr>
                    <th>Property Code</th>
                    <th>Property Name</th>
                    <th>Quantity</th>
                    <th>Serial Number/s</th>
                    <th>Office</th>
                    <th>Location</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $assignment->property?->property_code ?: 'N/A' }}</td>
                    <td>{{ $assignment->property?->property_name ?: 'N/A' }}</td>
                    <td>{{ $assignment->quantity_assigned }} {{ $assignment->property?->unit }}</td>
                    <td>{{ $assignment->propertyUnits->pluck('serial_number')->join(', ') ?: ($assignment->property?->serial_number ?: 'N/A') }}</td>
                    <td>{{ $assignment->property?->office ?: 'N/A' }}</td>
                    <td>{{ $assignment->location ?: $assignment->property?->location ?: 'N/A' }}</td>
                </tr>
            </tbody>
        </table>

        <section>
            <div class="label">Remarks</div>
            <div class="field">{{ $assignment->remarks ?: 'None' }}</div>
        </section>

        <p class="acknowledgement">
            I acknowledge that I have received the property item/s listed above in good condition, unless otherwise stated in the remarks.
            I understand that the item/s remain under my accountability and must be returned, reported, or presented upon request by authorized school personnel.
        </p>

        <section class="signatures">
            <div class="signature-line">
                <div>{{ $assignment->assignee?->name ?: '' }}</div>
                <div class="signature-role">Receiver / End-User Signature</div>
            </div>
            <div class="signature-line">
                <div>{{ $verifyingAdmin?->name ?? $assignment->assignedBy?->name ?? 'Supply Office Administrator' }}</div>
                <div class="signature-role">Admin / Supply Officer Signature</div>
            </div>
        </section>

    </main>
</body>
</html>
