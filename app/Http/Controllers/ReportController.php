<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Disposal;
use App\Models\Property;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index', [
            'propertyCount' => Property::count(),
            'assignmentCount' => Assignment::count(),
            'disposalCount' => Disposal::count(),
        ]);
    }

    public function properties(): View
    {
        return view('reports.properties', [
            'properties' => $this->propertyQuery()->get(),
            'activeAdmin' => User::defaultAdmin(),
        ]);
    }

    public function assignments(): View
    {
        return view('reports.assignments', [
            'assignments' => $this->assignmentQuery()->get(),
            'activeAdmin' => User::defaultAdmin(),
        ]);
    }

    public function disposals(): View
    {
        return view('reports.disposals', [
            'disposals' => $this->disposalQuery()->get(),
            'activeAdmin' => User::defaultAdmin(),
        ]);
    }

    public function propertiesPdf(): Response
    {
        $pdf = Pdf::loadView('reports.pdf.properties', [
            'title' => 'Property List Report',
            'properties' => $this->propertyQuery()->get(),
            'activeAdmin' => User::defaultAdmin(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('property-list-report.pdf');
    }

    public function assignmentsPdf(): Response
    {
        $pdf = Pdf::loadView('reports.pdf.assignments', [
            'title' => 'Assigned Items Report',
            'assignments' => $this->assignmentQuery()->get(),
            'activeAdmin' => User::defaultAdmin(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('assigned-items-report.pdf');
    }

    public function disposalsPdf(): Response
    {
        $pdf = Pdf::loadView('reports.pdf.disposals', [
            'title' => 'Disposal Report',
            'disposals' => $this->disposalQuery()->get(),
            'activeAdmin' => User::defaultAdmin(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('disposal-report.pdf');
    }

    public function propertiesCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Property Code', 'Serial Number', 'Property Name', 'Category', 'Brand', 'Model', 'Quantity', 'Available Quantity', 'Assigned Quantity', 'Unit', 'Office', 'Location', 'Condition', 'Status']);

            foreach ($this->propertyQuery()->get() as $property) {
                fputcsv($handle, [
                    $property->property_code,
                    $property->unit_serial_numbers,
                    $property->property_name,
                    $property->category,
                    $property->brand,
                    $property->model,
                    $property->tracked_quantity,
                    $property->available_quantity,
                    $property->active_assigned_quantity,
                    $property->unit,
                    $property->office,
                    $property->location,
                    $property->condition_status,
                    $property->status,
                ]);
            }

            fclose($handle);
        }, 'property-list-report.csv', ['Content-Type' => 'text/csv']);
    }

    public function assignmentsCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Property Code', 'Serial Number', 'Property Name', 'Teacher', 'Quantity Assigned', 'Date Assigned', 'Assigned By', 'Status']);

            foreach ($this->assignmentQuery()->get() as $assignment) {
                fputcsv($handle, [
                    $assignment->property?->property_code,
                    $assignment->unit_serial_numbers,
                    $assignment->property?->property_name,
                    $assignment->teacher?->name,
                    $assignment->quantity_assigned,
                    optional($assignment->date_assigned)->format('Y-m-d'),
                    $assignment->assignedBy?->name,
                    $assignment->status,
                ]);
            }

            fclose($handle);
        }, 'assigned-items-report.csv', ['Content-Type' => 'text/csv']);
    }

    public function disposalsCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Property Code', 'Property Name', 'Quantity Disposed', 'Disposal Date', 'Method', 'Disposed By', 'Status']);

            foreach ($this->disposalQuery()->get() as $disposal) {
                fputcsv($handle, [
                    $disposal->property?->property_code,
                    $disposal->property?->property_name,
                    $disposal->quantity_disposed,
                    optional($disposal->disposal_date)->format('Y-m-d'),
                    $disposal->disposal_method,
                    $disposal->disposedBy?->name,
                    $disposal->status,
                ]);
            }

            fclose($handle);
        }, 'disposal-report.csv', ['Content-Type' => 'text/csv']);
    }

    protected function propertyQuery()
    {
        return Property::query()
            ->with('units')
            ->withSum([
                'assignments as active_quantity_assigned' => fn ($query) => $query->where('status', Assignment::STATUS_ACTIVE),
            ], 'quantity_assigned')
            ->latest();
    }

    protected function assignmentQuery()
    {
        return Assignment::query()
            ->with(['property.activeAssignments', 'propertyUnits', 'teacher', 'assignedBy'])
            ->latest('date_assigned');
    }

    protected function disposalQuery()
    {
        return Disposal::query()
            ->with(['property', 'disposedBy'])
            ->latest('disposal_date');
    }
}
