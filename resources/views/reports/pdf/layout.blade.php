<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { margin-bottom: 4px; font-size: 22px; }
        p { margin-top: 0; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #eff6ff; }
    </style>
</head>
<body>
    <div style="text-align: center; margin-bottom: 20px; color: #000;">
        @include('partials.print-logo')
        <div style="font-size: 16px; font-weight: bold;">ABANON NATIONAL HIGH SCHOOL</div>
        <div>San Carlos City, Pangasinan</div>
    </div>
    <h1>{{ $title }}</h1>
    <p>Generated from Property Accountability Records and Disposal System (PARDS)</p>
    @yield('pdf-content')
    <div style="margin-top: 36px; page-break-inside: avoid;">
        <table style="width: 100%; border: none; margin-top: 24px;">
            <tr style="border: none;">
                <td style="width: 50%; border: none; padding: 0 40px 0 0;">
                    <div>Prepared by:</div>
                    <div style="margin-top: 32px; border-top: 1px solid #000; text-align: center; padding-top: 4px; font-weight: bold;">
                        {{ auth()->user()?->name ?? 'Supply Office Personnel' }}
                    </div>
                    <div style="text-align: center; font-size: 10px; color: #555;">{{ auth()->user()?->isAdmin() ? 'Administrator' : 'Supply Office Staff' }}</div>
                </td>
                <td style="width: 50%; border: none; padding: 0 0 0 40px;">
                    <div>Verified / Approved by:</div>
                    <div style="margin-top: 32px; border-top: 1px solid #000; text-align: center; padding-top: 4px; font-weight: bold;">
                        {{ $activeAdmin?->name ?? 'School Administrator' }}
                    </div>
                    <div style="text-align: center; font-size: 10px; color: #555;">School Head / Administrator</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
