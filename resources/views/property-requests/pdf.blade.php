<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Property Request {{ $propertyRequest->reference_number }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; line-height: 1.45; }
        .header { text-align: center; border-bottom: 2px solid #1f2937; padding-bottom: 10px; margin-bottom: 16px; }
        .school { font-size: 15px; font-weight: bold; letter-spacing: .3px; }
        .muted { color: #6b7280; }
        h1 { font-size: 18px; margin: 0 0 2px; text-align: center; text-transform: uppercase; letter-spacing: .5px; }
        .subtitle { text-align: center; color: #6b7280; margin-bottom: 16px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .meta td { padding: 4px 6px; vertical-align: top; }
        .meta .label { width: 18%; font-weight: bold; color: #374151; }
        .box { border: 1px solid #d1d5db; border-radius: 4px; padding: 10px; margin-bottom: 14px; }
        .box-title { font-size: 10px; font-weight: bold; text-transform: uppercase; color: #4b5563; margin-bottom: 5px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th, table.items td { border: 1px solid #9ca3af; padding: 6px; vertical-align: top; }
        table.items th { background: #e5edf9; font-size: 10px; text-transform: uppercase; }
        table.items tfoot td { font-weight: bold; background: #f3f4f6; }
        table.items tr { page-break-inside: avoid; }
        .status { display: inline-block; border: 1px solid #6b7280; border-radius: 10px; padding: 2px 8px; font-size: 9px; text-transform: uppercase; }
        .notes { min-height: 42px; white-space: pre-line; }
        .signatures { width: 100%; border-collapse: collapse; margin-top: 32px; page-break-inside: avoid; }
        .signatures td { width: 33%; padding: 0 12px; text-align: center; vertical-align: bottom; }
        .signature-line { border-top: 1px solid #111827; margin-top: 42px; padding-top: 4px; font-weight: bold; }
        .signature-role { font-size: 9px; color: #6b7280; }
        .footer { position: fixed; bottom: -10px; left: 0; right: 0; font-size: 8px; color: #9ca3af; text-align: center; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="header">
        @include('partials.print-logo')
        <div class="school">ABANON NATIONAL HIGH SCHOOL</div>
        <div class="muted">San Carlos City, Pangasinan</div>
        <div class="muted">Property Accountability and Request Distribution System (PARDS)</div>
    </div>

    <h1>Property Request Form</h1>
    <div class="subtitle">Reference No. {{ $propertyRequest->reference_number }}</div>

    <table class="meta">
        <tr>
            <td class="label">Requesting Teacher</td>
            <td>{{ $propertyRequest->requester?->name ?? 'N/A' }}</td>
            <td class="label">Date Submitted</td>
            <td>{{ $propertyRequest->created_at?->format('F d, Y h:i A') ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Email</td>
            <td>{{ $propertyRequest->requester?->email ?? 'N/A' }}</td>
            <td class="label">Needed By</td>
            <td>{{ $propertyRequest->needed_by?->format('F d, Y') ?? 'Not specified' }}</td>
        </tr>
        <tr>
            <td class="label">Request Type</td>
            <td>{{ $propertyRequest->request_type_label }}</td>
            <td class="label">Status</td>
            <td><span class="status">{{ str_replace('_', ' ', $propertyRequest->status) }}</span></td>
        </tr>
    </table>

    <div class="box">
        <div class="box-title">Purpose</div>
        <div>{{ $propertyRequest->purpose }}</div>
        @if ($propertyRequest->additional_notes)
            <div class="box-title" style="margin-top: 8px;">Additional Notes</div>
            <div>{{ $propertyRequest->additional_notes }}</div>
        @endif
        @if ($propertyRequest->request_type === \App\Models\PropertyRequestRecord::TYPE_REPLACEMENT)
            <div class="box-title" style="margin-top: 8px;">Replacement Details</div>
            <div>Property to replace: {{ $propertyRequest->replacementAssignment?->property?->property_name ?? 'N/A' }}</div>
            <div>Issue: {{ $propertyRequest->issue_description ?: 'N/A' }}</div>
        @endif
    </div>

    <div class="box-title">Requested Properties</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 31%;">Requested Item</th>
                <th style="width: 13%;">Item Code</th>
                <th style="width: 9%;">Qty</th>
                <th style="width: 27%;">Approved Property</th>
                <th style="width: 15%;">Assignment</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $item->requested_item_name }}</strong>
                        <div class="muted">{{ $item->property?->category ?: ($item->inventory?->property?->category ?: 'Requested item') }}</div>
                    </td>
                    <td>{{ $item->property?->property_code ?: ($item->inventory?->property?->property_code ?: 'N/A') }}</td>
                    <td>{{ $item->requested_quantity }}</td>
                    <td>{{ $item->approvedProperty?->property_name ?: 'Not yet selected' }}</td>
                    <td>{{ $item->assignment ? 'Assignment #'.$item->assignment->id : 'Not assigned' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Total</td>
                <td>{{ $items->sum('requested_quantity') }}</td>
                <td colspan="2">{{ $items->count() }} item(s)</td>
            </tr>
        </tfoot>
    </table>

    <div class="box" style="margin-top: 14px;">
        <div class="box-title">Approval Details</div>
        <table class="meta" style="margin-bottom: 0;">
            <tr>
                <td class="label">Staff Reviewer</td>
                <td>{{ $propertyRequest->reviewedBy?->name ?? 'Pending review' }}</td>
                <td class="label">Reviewed At</td>
                <td>{{ $propertyRequest->reviewed_at?->format('F d, Y h:i A') ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Admin Decision</td>
                <td>{{ $propertyRequest->processedBy?->name ?? 'Pending decision' }}</td>
                <td class="label">Decided At</td>
                <td>{{ $propertyRequest->processed_at?->format('F d, Y h:i A') ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Staff Notes</td>
                <td colspan="3" class="notes">{{ $propertyRequest->review_notes ?: 'None' }}</td>
            </tr>
            <tr>
                <td class="label">Admin Notes</td>
                <td colspan="3" class="notes">{{ $propertyRequest->response_notes ?: 'None' }}</td>
            </tr>
        </table>
    </div>

    <table class="signatures">
        <tr>
            <td>
                <div class="signature-line">{{ $propertyRequest->requester?->name ?? 'Requesting Teacher' }}</div>
                <div class="signature-role">Requesting Teacher</div>
            </td>
            <td>
                <div class="signature-line">{{ $propertyRequest->reviewedBy?->name ?? 'Supply Office Staff' }}</div>
                <div class="signature-role">Reviewed by - Supply Office Staff</div>
            </td>
            <td>
                <div class="signature-line">{{ $propertyRequest->processedBy?->name ?? ($activeAdmin?->name ?? 'School Administrator') }}</div>
                <div class="signature-role">Approved by - Administrator</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Generated by PARDS on {{ now()->format('F d, Y h:i A') }} · {{ $propertyRequest->reference_number }}
    </div>
</body>
</html>
