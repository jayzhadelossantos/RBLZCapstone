<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PARDS QR Sticker</title>
    <style>
        @page { size: 50mm 50mm; margin: 3mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #17133d; font-family: Arial, sans-serif; background: #f4f6fb; }
        .print-actions { padding: 16px; text-align: center; }
        .print-actions button { padding: 9px 16px; border: 0; border-radius: 7px; color: #fff; font-weight: 700; background: #2d2078; cursor: pointer; }
        .sticker { position: relative; width: 44mm; height: 44mm; overflow: hidden; border: 1.2px solid #2d2078; border-radius: 5px; background: #fff; box-shadow: 0 2px 8px rgba(23, 10, 88, .18); }
        .sticker::before { position: absolute; top: -16mm; right: -17mm; width: 35mm; height: 35mm; border: 1px solid rgba(255,255,255,.18); border-radius: 50%; background: rgba(255,255,255,.07); content: ''; }
        .header { position: relative; display: flex; align-items: center; gap: 2mm; height: 10mm; padding: 1.5mm 2mm; color: #fff; background: linear-gradient(125deg, #170a58, #2d2078 70%, #4b3bb0); }
        .logo { width: 7mm; height: 7mm; object-fit: cover; border: .6mm solid rgba(255,255,255,.92); border-radius: 50%; background: #fff; }
        .brand { font-size: 6pt; font-weight: 800; letter-spacing: .6px; }
        .caption { margin-top: .3mm; color: rgba(255,255,255,.75); font-size: 3.6pt; letter-spacing: .1px; }
        .qr { display: flex; height: 28mm; align-items: center; justify-content: center; padding-top: 1mm; }
        .qr svg { width: 26mm; height: 26mm; }
        .footer { position: absolute; right: 2mm; bottom: 1.6mm; left: 2mm; border-top: .35mm solid #f1c94c; padding-top: 1mm; color: #4a4570; font-size: 4.5pt; font-weight: 700; letter-spacing: .35px; text-align: center; text-transform: uppercase; }
        @media print { body { background: #fff; } .print-actions { display: none; } }
    </style>
</head>
<body>
    <div class="print-actions"><button type="button" onclick="window.print()">Print QR Sticker</button></div>
    <main class="sticker" aria-label="PARDS QR property sticker">
        <header class="header">
            <img src="{{ asset('ANHS LOGO.jpg') }}" class="logo" alt="ANHS logo">
            <div>
                <div class="brand">PARDS</div>
                <div class="caption">Property Accountability System</div>
            </div>
        </header>
        <div class="qr">{!! $qrSvg !!}</div>
        <footer class="footer">Scan to view property details</footer>
    </main>
</body>
</html>
