<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Sticker - {{ $property->property_code }}</title>
    <style>
        @page { size: 90mm 55mm; margin: 4mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; color: #111827; }
        .actions { margin: 16px; }
        .sticker { width: 82mm; min-height: 47mm; border: 1.5px solid #111827; border-radius: 4px; padding: 3.5mm; display: grid; grid-template-columns: 31mm 1fr; gap: 3mm; }
        .qr { display: flex; align-items: center; justify-content: center; }
        .qr svg { width: 29mm; height: 29mm; }
        .system { font-size: 7pt; font-weight: 700; letter-spacing: .3px; }
        .name { font-size: 11pt; font-weight: 700; margin: 1.5mm 0; line-height: 1.15; }
        .line { font-size: 8pt; margin: 1mm 0; overflow-wrap: anywhere; }
        .label { color: #4b5563; font-weight: 700; }
        .scan { grid-column: 1 / -1; border-top: 1px solid #9ca3af; padding-top: 1.5mm; font-size: 7pt; text-align: center; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">Print Sticker</button></div>
    <main class="sticker">
        <div class="qr">{!! $qrSvg !!}</div>
        <div>
            <div class="system">PARDS PROPERTY STICKER</div>
            <div class="name">{{ $property->property_name }}</div>
            <div class="line"><span class="label">Property Code:</span> {{ $property->property_code }}</div>
            <div class="line"><span class="label">Serial No.:</span> {{ $propertyUnit?->serial_number ?? $property->serial_number ?? 'N/A' }}</div>
            <div class="line"><span class="label">Assigned To:</span> {{ $activeAssignment?->assignee?->name ?? $activeAssignment?->teacher?->name ?? 'Unassigned' }}</div>
            <div class="line"><span class="label">Location:</span> {{ $activeAssignment?->location ?: $property->location ?: 'N/A' }}</div>
        </div>
        <div class="scan">Scan this QR code to view the current property accountability details.</div>
    </main>
</body>
</html>
