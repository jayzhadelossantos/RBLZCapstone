<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Property;
use App\Models\PropertyUnit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeController extends Controller
{
    public function index(): View
    {
        $propertyUnits = PropertyUnit::query()
            ->with(['property', 'assignment.assignee'])
            ->latest()
            ->paginate(10);

        return view('qr.index', [
            'propertyUnits' => $propertyUnits,
        ]);
    }

    public function show(Property $property): View
    {
        $property = $this->ensureQrToken($property);
        $property->load(['assignments.teacher']);

        $activeAssignment = $this->activeAssignment($property);
        $scanUrl = route('qr.scan', $property->qr_token);
        $qrPayload = $this->buildQrPayload($property, $activeAssignment, $scanUrl);
        $qrSvg = QrCode::format('svg')->size(260)->generate($qrPayload);

        return view('qr.show', [
            'property' => $property,
            'qrSvg' => $qrSvg,
            'scanUrl' => $scanUrl,
            'qrPayload' => $qrPayload,
            'activeAssignment' => $activeAssignment,
        ]);
    }

    public function download(Property $property): Response
    {
        $property = $this->ensureQrToken($property);
        $property->load(['assignments.teacher']);

        $activeAssignment = $this->activeAssignment($property);
        $scanUrl = route('qr.scan', $property->qr_token);
        $qrPayload = $this->buildQrPayload($property, $activeAssignment, $scanUrl);
        $qrSvg = QrCode::format('svg')->size(320)->generate($qrPayload);

        return response($qrSvg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="property-'.$property->property_code.'-qr.svg"',
        ]);
    }

    public function print(Property $property): View
    {
        $property = $this->ensureQrToken($property);
        $property->load(['assignments.teacher']);
        $activeAssignment = $this->activeAssignment($property);
        $scanUrl = route('qr.scan', $property->qr_token);

        return view('qr.print-sticker', [
            'property' => $property,
            'propertyUnit' => null,
            'activeAssignment' => $activeAssignment,
            'qrSvg' => QrCode::format('svg')->size(220)->generate($this->buildQrPayload($property, $activeAssignment, $scanUrl)),
        ]);
    }

    public function showUnit(PropertyUnit $propertyUnit): View
    {
        $propertyUnit = $this->ensureUnitQrToken($propertyUnit);
        $propertyUnit->load(['property', 'assignment.assignee']);

        $scanUrl = route('qr.scan', $propertyUnit->qr_token);
        $qrPayload = $this->buildUnitQrPayload($propertyUnit, $scanUrl);
        $qrSvg = QrCode::format('svg')->size(260)->generate($qrPayload);

        return view('qr.show-unit', [
            'propertyUnit' => $propertyUnit,
            'property' => $propertyUnit->property,
            'qrSvg' => $qrSvg,
            'scanUrl' => $scanUrl,
            'qrPayload' => $qrPayload,
            'activeAssignment' => $propertyUnit->assignment,
        ]);
    }

    public function downloadUnit(PropertyUnit $propertyUnit): Response
    {
        $propertyUnit = $this->ensureUnitQrToken($propertyUnit);
        $propertyUnit->load(['property', 'assignment.assignee']);

        $scanUrl = route('qr.scan', $propertyUnit->qr_token);
        $qrPayload = $this->buildUnitQrPayload($propertyUnit, $scanUrl);
        $qrSvg = QrCode::format('svg')->size(320)->generate($qrPayload);

        return response($qrSvg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="unit-'.$propertyUnit->serial_number.'-qr.svg"',
        ]);
    }

    public function printUnit(PropertyUnit $propertyUnit): View
    {
        $propertyUnit = $this->ensureUnitQrToken($propertyUnit);
        $propertyUnit->load(['property', 'assignment.assignee']);
        $scanUrl = route('qr.scan', $propertyUnit->qr_token);

        return view('qr.print-sticker', [
            'property' => $propertyUnit->property,
            'propertyUnit' => $propertyUnit,
            'activeAssignment' => $propertyUnit->assignment,
            'qrSvg' => QrCode::format('svg')->size(220)->generate($this->buildUnitQrPayload($propertyUnit, $scanUrl)),
        ]);
    }

    public function scan(string $token): View
    {
        $propertyUnit = PropertyUnit::query()
            ->where('qr_token', $token)
            ->with(['property', 'assignment.assignee'])
            ->first();

        if ($propertyUnit) {
            return view('qr.scan-unit', [
                'propertyUnit' => $propertyUnit,
                'property' => $propertyUnit->property,
                'activeAssignment' => $propertyUnit->assignment,
            ]);
        }

        $property = Property::query()
            ->where('qr_token', $token)
            ->with(['assignments.teacher'])
            ->firstOrFail();

        return view('qr.scan', [
            'property' => $property,
            'activeAssignment' => $this->activeAssignment($property),
        ]);
    }

    protected function ensureQrToken(Property $property): Property
    {
        if (! $property->qr_token) {
            $property->forceFill([
                'qr_token' => (string) Str::uuid(),
            ])->save();
        }

        return $property;
    }

    protected function ensureUnitQrToken(PropertyUnit $propertyUnit): PropertyUnit
    {
        if (! $propertyUnit->qr_token) {
            $propertyUnit->forceFill([
                'qr_token' => PropertyUnit::generateQrToken(),
            ])->save();
        }

        return $propertyUnit;
    }

    protected function activeAssignment(Property $property): ?Assignment
    {
        return $property->assignments
            ->where('status', Assignment::STATUS_ACTIVE)
            ->sortByDesc('date_assigned')
            ->first();
    }

    protected function buildQrPayload(Property $property, ?Assignment $activeAssignment, string $scanUrl): string
    {
        return implode("\n", [
            'PARDS Property Information',
            'Property Name: '.$property->property_name,
            'Property Code: '.$property->property_code,
            'Serial Number: '.($property->serial_number ?: 'N/A'),
            'Assigned To: '.($activeAssignment?->teacher?->name ?? 'Unassigned'),
            'Status: '.ucfirst(str_replace('_', ' ', $property->status)),
            'Condition: '.ucfirst(str_replace('_', ' ', $property->condition_status)),
            'Location: '.($activeAssignment?->location ?: 'N/A'),
            'Open Details: '.$scanUrl,
        ]);
    }

    protected function buildUnitQrPayload(PropertyUnit $propertyUnit, string $scanUrl): string
    {
        $property = $propertyUnit->property;
        $assignment = $propertyUnit->assignment;

        return implode("\n", [
            'PARDS Property Unit Information',
            'Property Name: '.$property?->property_name,
            'Property Code: '.$property?->property_code,
            'Brand: '.($property?->brand ?: 'N/A'),
            'Model: '.($property?->model ?: 'N/A'),
            'Unit Serial Number: '.$propertyUnit->serial_number,
            'Assigned To: '.($assignment?->assignee?->name ?? 'Unassigned'),
            'Unit Status: '.ucfirst(str_replace('_', ' ', $propertyUnit->status)),
            'Property Status: '.ucfirst(str_replace('_', ' ', $property?->status ?? 'N/A')),
            'Condition: '.ucfirst(str_replace('_', ' ', $property?->condition_status ?? 'N/A')),
            'Office: '.($property?->office ?: 'N/A'),
            'Location: '.($assignment?->location ?: $property?->location ?: 'N/A'),
            'Open Details: '.$scanUrl,
        ]);
    }
}
