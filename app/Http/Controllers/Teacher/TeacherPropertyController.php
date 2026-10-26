<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Property;
use App\Models\PropertyRequestRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use App\Models\User;
use Illuminate\Validation\Rule;

class TeacherPropertyController extends Controller
{
    public function dashboard(Request $request): View
    {
        $teacher = $request->user();

        $assignedPropertiesCount = Property::query()
            ->whereHas('assignments', function ($query) use ($teacher) {
                $query
                    ->where('teacher_id', $teacher->id)
                    ->where('status', Assignment::STATUS_ACTIVE);
            })
            ->count();

        $recentAssignments = Assignment::query()
            ->with('property')
            ->where('teacher_id', $teacher->id)
            ->where('status', Assignment::STATUS_ACTIVE)
            ->latest('date_assigned')
            ->take(5)
            ->get();

        $propertyRequestCount = PropertyRequestRecord::query()
            ->where('requested_by', $teacher->id)
            ->count();

        $pendingPropertyRequestCount = PropertyRequestRecord::query()
            ->where('requested_by', $teacher->id)
            ->whereIn('status', [PropertyRequestRecord::STATUS_PENDING, PropertyRequestRecord::STATUS_REVIEWED, PropertyRequestRecord::STATUS_AWAITING_STOCK, PropertyRequestRecord::STATUS_APPROVED])
            ->count();

        $recentPropertyRequests = PropertyRequestRecord::query()
            ->where('requested_by', $teacher->id)
            ->latest()
            ->take(5)
            ->get();

        return view('teacher.dashboard', [
            'assignedPropertiesCount' => $assignedPropertiesCount,
            'recentAssignments' => $recentAssignments,
            'propertyRequestCount' => $propertyRequestCount,
            'pendingPropertyRequestCount' => $pendingPropertyRequestCount,
            'recentPropertyRequests' => $recentPropertyRequests,
        ]);
    }

    public function index(Request $request): View
    {
        $teacher = $request->user();

        $properties = Property::query()
            ->with([
                'assignments' => function ($query) use ($teacher) {
                    $query
                        ->with('propertyUnits')
                        ->where('teacher_id', $teacher->id)
                        ->where('status', Assignment::STATUS_ACTIVE)
                        ->latest('date_assigned');
                },
                'activeAssignments',
            ])
            ->whereHas('assignments', function ($query) use ($teacher) {
                $query
                    ->where('teacher_id', $teacher->id)
                    ->where('status', Assignment::STATUS_ACTIVE);
            })
            ->latest()
            ->paginate(10);

        return view('teacher.properties.index', [
            'properties' => $properties,
        ]);
    }

    public function print(Request $request): View|Response
    {
        $teacher = $request->user();
        $request->validate([
            'verified_by' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', User::ROLE_ADMIN)->where('is_active', true)],
        ]);
        $admins = User::query()->where('role', User::ROLE_ADMIN)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $verifyingAdmin = $request->filled('verified_by')
            ? $admins->firstWhere('id', $request->integer('verified_by'))
            : (User::defaultAdmin() ?? $admins->first());

        $properties = Property::query()
            ->with([
                'assignments' => function ($query) use ($teacher) {
                    $query
                        ->with(['assignedBy', 'propertyUnits'])
                        ->where('teacher_id', $teacher->id)
                        ->where('status', Assignment::STATUS_ACTIVE)
                        ->latest('date_assigned');
                },
                'activeAssignments',
            ])
            ->whereHas('assignments', function ($query) use ($teacher) {
                $query
                    ->where('teacher_id', $teacher->id)
                    ->where('status', Assignment::STATUS_ACTIVE);
            })
            ->orderBy('property_name')
            ->get();

        $data = [
            'properties' => $properties,
            'teacher' => $teacher,
            'printedAt' => now(),
            'isPdf' => $request->boolean('pdf'),
            'admins' => $admins,
            'verifyingAdmin' => $verifyingAdmin,
        ];

        if ($data['isPdf']) {
            return Pdf::loadView('teacher.properties.print', $data)
                ->setPaper('a4', 'landscape')
                ->download('my-accountabilities.pdf');
        }

        return view('teacher.properties.print', $data);
    }

    public function show(Request $request, Property $property): View
    {
        $teacher = $request->user();

        $property->load([
            'assignments' => function ($query) use ($teacher) {
                $query
                    ->with(['assignedBy', 'propertyUnits'])
                    ->where('teacher_id', $teacher->id)
                    ->where('status', Assignment::STATUS_ACTIVE)
                    ->latest('date_assigned');
            },
            'activeAssignments',
        ]);

        abort_unless($property->assignments->isNotEmpty(), 403);
        $assignment = $request->filled('assignment')
            ? $property->assignments->firstWhere('id', $request->integer('assignment'))
            : $property->assignments->first();
        abort_unless($assignment, 404);

        return view('teacher.properties.show', [
            'property' => $property,
            'assignment' => $assignment,
        ]);
    }
}
