<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReturnAssignmentRequest;
use App\Http\Requests\StoreAssignmentRequest;
use App\Http\Requests\UpdateAssignmentRequest;
use App\Models\Assignment;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyHistory;
use App\Models\PropertyUnit;
use App\Models\ReturnRecord;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->string('type')->toString();
        $activeFilter = in_array($filter, ['teachers', 'staff'], true) ? $filter : 'all';
        $search = trim($request->string('search')->toString());

        $assignees = function (string $role, string $page) use ($search) {
            return User::query()->where('role', $role)
                ->whereHas('teacherAssignments')
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($nested) use ($search) {
                        $nested->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhereHas('teacherAssignments', function ($assignments) use ($search) {
                                $assignments->where('department', 'like', "%{$search}%")
                                    ->orWhereHas('property', function ($properties) use ($search) {
                                        $properties->where('property_name', 'like', "%{$search}%")
                                            ->orWhere('property_code', 'like', "%{$search}%")
                                            ->orWhere('serial_number', 'like', "%{$search}%");
                                    })
                                    ->orWhereHas('propertyUnits', fn ($units) => $units->where('serial_number', 'like', "%{$search}%"));
                            });
                    });
                })
                ->with(['teacherProfile', 'staffProfile', 'adminProfile', 'teacherAssignments' => function ($query) {
                    $query->with(['property', 'propertyUnits', 'assignedBy'])->latest('date_assigned')->latest('id');
                }])
                ->orderBy('name')->orderBy('id')->paginate(8, ['*'], $page)->withQueryString();
        };

        return view('assignments.index', [
            'teacherAssignees' => $assignees(User::ROLE_TEACHER, 'teacher_page'),
            'staffAssignees' => $assignees(User::ROLE_STAFF, 'staff_page'),
            'activeFilter' => $activeFilter,
            'search' => $search,
        ]);
    }
    public function create(Request $request): View
    {
        [$selectedTeacherId, $selectedStaffId, $selectedAssignee] = $this->prefilledAssignee($request);

        return view('assignments.create', $this->assignmentFormData(
            new Assignment(),
            $selectedTeacherId,
            $selectedStaffId,
            $selectedAssignee,
        ));
    }

    public function store(StoreAssignmentRequest $request): RedirectResponse
    {
        $assignment = DB::transaction(function () use ($request) {
            $property = Property::query()
                ->lockForUpdate()
                ->findOrFail($request->integer('property_id'));

            $requestedQuantity = $request->integer('quantity_assigned');
            $availableQuantity = (int) $property->quantity;

            if ($requestedQuantity > $availableQuantity) {
                throw ValidationException::withMessages([
                    'quantity_assigned' => 'Only '.$availableQuantity.' item'.($availableQuantity === 1 ? ' is' : 's are').' available.',
                ]);
            }

            $assignment = Assignment::create([
                'property_id' => $property->id,
                'teacher_id' => $request->assigneeId(),
                'teacher_profile_id' => $this->resolveTeacherProfileId($request),
                'assigned_by' => $request->user()->id,
                'staff_id' => $request->user()?->staffProfile?->id,
                'quantity_assigned' => $requestedQuantity,
                'date_assigned' => $request->date('date_assigned'),
                'location' => $request->input('location'),
                'department' => $request->input('department') ?: $property->department,
                'location_id' => Location::resolveId($request->input('location')),
                'assigned_at' => $request->date('date_assigned'),
                'remarks' => $request->input('remarks'),
                'status' => Assignment::STATUS_ACTIVE,
            ]);

            app(\App\Services\InventoryUnitService::class)->assign($property, $assignment, $requestedQuantity,
                $request->boolean('select_units') || $request->has('unit_ids') ? $request->input('unit_ids', []) : null);
            $property->decrement('quantity', $requestedQuantity);
            $property->refresh();
            $property->syncInventoryStatus();
            PropertyHistory::create([
                'property_id' => $property->id,
                'action_type' => 'assigned',
                'reference' => 'assignment:'.$assignment->id,
                'action_date' => $request->date('date_assigned')?->toDateString() ?? now()->toDateString(),
                'remarks' => $request->input('remarks'),
            ]);

            return $assignment;
        });

        return redirect()
            ->route('assignments.show', $assignment)
            ->with('success', 'Property assigned successfully.');
    }

    public function show(Assignment $assignment): View
    {
        $assignment->load(['property.activeAssignments', 'propertyUnits', 'assignee', 'assignedBy']);

        return view('assignments.show', [
            'assignment' => $assignment,
        ]);
    }

    public function print(Request $request, Assignment $assignment): View
    {
        $assignment->load(['property', 'propertyUnits', 'assignee', 'assignedBy']);

        $admins = User::query()->where('role', User::ROLE_ADMIN)->where('is_active', true)->orderBy('name')->get();
        $verifyingAdmin = $request->filled('verified_by')
            ? $admins->firstWhere('id', $request->integer('verified_by'))
            : ($admins->firstWhere('id', auth()->id()) ?? User::defaultAdmin() ?? $assignment->assignedBy);

        return view('assignments.print', [
            'assignment' => $assignment,
            'printedAt' => now(),
            'admins' => $admins,
            'verifyingAdmin' => $verifyingAdmin,
        ]);
    }

    public function edit(Assignment $assignment): View
    {
        abort_if($assignment->status !== Assignment::STATUS_ACTIVE, 404);

        $assignment->load(['property', 'assignee', 'assignedBy']);

        $selectedTeacherId = $assignment->assignee?->role === User::ROLE_TEACHER ? $assignment->teacher_id : null;
        $selectedStaffId = $assignment->assignee?->role === User::ROLE_STAFF ? $assignment->teacher_id : null;

        return view('assignments.edit', $this->assignmentFormData(
            $assignment,
            $selectedTeacherId,
            $selectedStaffId,
            $assignment->assignee,
        ));
    }

    public function update(UpdateAssignmentRequest $request, Assignment $assignment): RedirectResponse
    {
        DB::transaction(function () use ($request, $assignment) {
            $assignment = Assignment::query()
                ->with('property')
                ->lockForUpdate()
                ->findOrFail($assignment->id);

            if ($assignment->status !== Assignment::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'property_id' => 'Only active assignments can be edited.',
                ]);
            }

            $currentProperty = Property::query()
                ->lockForUpdate()
                ->findOrFail($assignment->property_id);

            $requestedPropertyId = $request->integer('property_id');
            $requestedQuantity = $request->integer('quantity_assigned');

            $targetProperty = $currentProperty->id === $requestedPropertyId
                ? $currentProperty
                : Property::query()->lockForUpdate()->findOrFail($requestedPropertyId);

            $maxAssignableQuantity = (int) $targetProperty->quantity;

            if ($targetProperty->id === $currentProperty->id) {
                $maxAssignableQuantity += (int) $assignment->quantity_assigned;
            }

            if ($requestedQuantity > $maxAssignableQuantity) {
                throw ValidationException::withMessages([
                    'quantity_assigned' => 'Only '.$maxAssignableQuantity.' item'.($maxAssignableQuantity === 1 ? ' is' : 's are').' available for this update.',
                ]);
            }

            if ($request->boolean('select_units') || $request->has('unit_ids')) {
                $this->releasePropertyUnits($assignment);
                $currentProperty->increment('quantity', (int) $assignment->quantity_assigned);
                $targetProperty->refresh();
                app(\App\Services\InventoryUnitService::class)->assign($targetProperty, $assignment, $requestedQuantity, $request->input('unit_ids', []));
                $targetProperty->decrement('quantity', $requestedQuantity);
            } elseif ($currentProperty->id === $targetProperty->id) {
                $quantityDifference = $requestedQuantity - (int) $assignment->quantity_assigned;

                if ($quantityDifference > 0) {
                    $this->assignPropertyUnits($targetProperty, $assignment, $quantityDifference);
                    $targetProperty->decrement('quantity', $quantityDifference);
                } elseif ($quantityDifference < 0) {
                    $this->releasePropertyUnits($assignment, abs($quantityDifference));
                    $targetProperty->increment('quantity', abs($quantityDifference));
                }
            } else {
                $this->releasePropertyUnits($assignment);
                $currentProperty->increment('quantity', (int) $assignment->quantity_assigned);
                $this->assignPropertyUnits($targetProperty, $assignment, $requestedQuantity);
                $targetProperty->decrement('quantity', $requestedQuantity);
            }

            $assignment->update([
                'property_id' => $targetProperty->id,
                'teacher_id' => $request->assigneeId(),
                'teacher_profile_id' => $this->resolveTeacherProfileId($request),
                'quantity_assigned' => $requestedQuantity,
                'date_assigned' => $request->date('date_assigned'),
                'location' => $request->input('location'),
                'department' => $request->input('department') ?: $targetProperty->department,
                'location_id' => Location::resolveId($request->input('location')),
                'assigned_at' => $request->date('date_assigned'),
                'remarks' => $request->input('remarks'),
            ]);

            $currentProperty->refresh();
            $currentProperty->syncInventoryStatus();

            if ($targetProperty->id !== $currentProperty->id) {
                PropertyHistory::create([
                    'property_id' => $currentProperty->id,
                    'action_type' => 'assignment_updated',
                    'reference' => 'assignment:'.$assignment->id,
                    'action_date' => $request->date('date_assigned')?->toDateString() ?? now()->toDateString(),
                    'remarks' => 'Assignment moved to another property record.',
                ]);
            }

            $targetProperty->refresh();
            $targetProperty->syncInventoryStatus();
            PropertyHistory::create([
                'property_id' => $targetProperty->id,
                'action_type' => 'assignment_updated',
                'reference' => 'assignment:'.$assignment->id,
                'action_date' => $request->date('date_assigned')?->toDateString() ?? now()->toDateString(),
                'remarks' => $request->input('remarks') ?: 'Assignment details updated.',
            ]);
        });

        return redirect()
            ->route('assignments.show', $assignment)
            ->with('success', 'Assignment updated successfully.');
    }

    public function markReturned(ReturnAssignmentRequest $request, Assignment $assignment): RedirectResponse
    {
        DB::transaction(function () use ($request, $assignment) {
            $assignment = Assignment::query()
                ->with('property')
                ->lockForUpdate()
                ->findOrFail($assignment->id);

            if ($assignment->status !== Assignment::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'returned_at' => 'Only active assignments can be returned.',
                ]);
            }

            $assignment->update([
                'returned_at' => $request->date('returned_at'),
                'status' => Assignment::STATUS_RETURNED,
            ]);

            $returnedSerialNumbers = $assignment->propertyUnits()
                ->pluck('serial_number')
                ->join(', ');

            ReturnRecord::create([
                'assignment_id' => $assignment->id,
                'returned_by' => $request->user()?->id,
                'return_date' => $request->date('returned_at')?->toDateString() ?? now()->toDateString(),
                'status' => Assignment::STATUS_RETURNED,
                'returned_serial_numbers' => $returnedSerialNumbers !== '' ? $returnedSerialNumbers : null,
                'remarks' => $assignment->remarks,
            ]);
            $this->releasePropertyUnits($assignment);
            $assignment->property?->increment('quantity', (int) $assignment->quantity_assigned);
            $assignment->property?->refresh();
            $assignment->property?->syncInventoryStatus();
            if ($assignment->property) {
                PropertyHistory::create([
                    'property_id' => $assignment->property->id,
                    'action_type' => 'returned',
                    'reference' => 'assignment:'.$assignment->id,
                    'action_date' => $request->date('returned_at')?->toDateString() ?? now()->toDateString(),
                    'remarks' => 'Property return recorded.',
                ]);
            }
        });

        return redirect()
            ->route('assignments.show', $assignment)
            ->with('success', 'Property return recorded successfully.');
    }

    private function assignmentFormData(
        Assignment $assignment,
        ?int $selectedTeacherId = null,
        ?int $selectedStaffId = null,
        ?User $selectedAssignee = null,
    ): array
    {
        $fixedAssignee = !$assignment->exists && $selectedAssignee !== null;
        return [
            'assignment' => $assignment,
            'properties' => $this->assignmentProperties($assignment),
            'selectedTeacherId' => $selectedTeacherId,
            'selectedStaffId' => $selectedStaffId,
            'selectedAssignee' => $selectedAssignee,
            'teachers' => $fixedAssignee ? collect() : User::query()
                ->where('role', User::ROLE_TEACHER)
                ->orderBy('name')
                ->get(),
            'staffMembers' => $fixedAssignee ? collect() : User::query()
                ->where('role', User::ROLE_STAFF)
                ->orderBy('name')
                ->get(),
        ];
    }

    private function assignmentProperties(Assignment $assignment)
    {
        return Property::query()
            ->with(['units' => fn ($query) => $query->where(function ($units) use ($assignment) {
                $units->where('status', PropertyUnit::STATUS_AVAILABLE);
                if ($assignment->exists) $units->orWhere('assignment_id', $assignment->id);
            })->orderBy('id')])
            ->where(function ($query) use ($assignment) {
                $query->where('quantity', '>', 0);
                if ($assignment->exists) $query->orWhere('id', $assignment->property_id);
            })
            ->where(function ($query) use ($assignment) {
                $query->whereIn('status', [Property::STATUS_AVAILABLE, Property::STATUS_ASSIGNED]);

                if ($assignment->property_id) {
                    $query->orWhere('id', $assignment->property_id);
                }
            })
            ->orderBy('property_name')
            ->get()
            ->filter(function (Property $property) use ($assignment) {
                return $property->available_quantity > 0 || $property->id === $assignment->property_id;
            })
            ->map(function (Property $property) use ($assignment) {
                $property->setAttribute(
                    'assignable_quantity',
                    $property->id === $assignment->property_id
                        ? $property->available_quantity + (int) $assignment->quantity_assigned
                        : $property->available_quantity
                );

                return $property;
            })
            ->values();
    }

    private function prefilledAssignee(Request $request): array
    {
        $selectedTeacher = null;
        $selectedStaff = null;

        if ($request->filled('teacher_id')) {
            $selectedTeacher = User::query()
                ->where('role', User::ROLE_TEACHER)
                ->where('id', $request->integer('teacher_id'))
                ->first();
        }

        if (! $selectedTeacher && $request->filled('staff_id')) {
            $selectedStaff = User::query()
                ->where('role', User::ROLE_STAFF)
                ->where('id', $request->integer('staff_id'))
                ->first();
        }

        return [
            $selectedTeacher?->id,
            $selectedStaff?->id,
            $selectedTeacher ?: $selectedStaff,
        ];
    }

    private function resolveTeacherProfileId(Request $request): ?int
    {
        $teacherId = $request->input('teacher_id');

        if (blank($teacherId)) {
            return null;
        }

        return User::query()->with('teacherProfile')->find($teacherId)?->teacherProfile?->id;
    }

    private function assignPropertyUnits(Property $property, Assignment $assignment, int $quantity): void
    {
        app(\App\Services\InventoryUnitService::class)->assign($property, $assignment, $quantity);
    }

    private function releasePropertyUnits(Assignment $assignment, ?int $quantity = null): void
    {
        $query = $assignment->propertyUnits()
            ->where('status', PropertyUnit::STATUS_ASSIGNED)
            ->orderByDesc('id');

        if ($quantity !== null) {
            $query->limit($quantity);
        }

        $unitIds = $query
            ->lockForUpdate()
            ->pluck('id');

        if ($unitIds->isEmpty()) {
            return;
        }

        PropertyUnit::query()
            ->whereIn('id', $unitIds)
            ->update([
                'assignment_id' => null,
                'status' => PropertyUnit::STATUS_AVAILABLE,
                'updated_at' => now(),
            ]);
    }

}
