<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyHistory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\InventoryUnitService;

class PropertyController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $category = trim((string) $request->string('category'));
        $status = trim((string) $request->string('status'));
        $conditionStatus = trim((string) $request->string('condition_status'));
        $location = trim((string) $request->string('location'));

        $properties = Property::query()->with('units')
            ->withSum([
                'assignments as active_quantity_assigned' => fn ($query) => $query->where('status', \App\Models\Assignment::STATUS_ACTIVE),
            ], 'quantity_assigned')
            ->withSum([
                'disposals as completed_quantity_disposed' => fn ($query) => $query->whereIn('status', \App\Models\Disposal::finalizedStatuses()),
            ], 'quantity_disposed')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('property_name', 'like', "%{$search}%")
                        ->orWhere('property_code', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%")
                        ->orWhereHas('units', fn ($units) => $units->where('serial_number', 'like', "%{$search}%"))
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('office', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            })
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($conditionStatus !== '', fn ($query) => $query->where('condition_status', $conditionStatus))
            ->when($location !== '', fn ($query) => $query->where('location', 'like', "%{$location}%"))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('properties.index', [
            'properties' => $properties,
            'categories' => PropertyCategory::query()->orderBy('category_name')->pluck('category_name'),
            'statuses' => Property::statuses(),
            'conditionStatuses' => Property::conditionStatuses(),
        ]);
    }

    public function create(): View
    {
        return view('properties.create', [
            'property' => new Property(),
            'categories' => Property::standardCategories(),
            'statuses' => Property::statuses(),
            'conditionStatuses' => Property::conditionStatuses(),
        ]);
    }

    public function store(StorePropertyRequest $request): RedirectResponse
    {
        $validated = $this->preparePropertyAttributes($request->validated(), $request, null);
        $validated['created_by'] = $request->user()?->id;
        $validated['qr_token'] = ($validated['qr_token'] ?? null) ?: (string) Str::uuid();
        $validated['qr_reference'] = $validated['qr_reference'] ?? $validated['qr_token'];

        $property = DB::transaction(function () use ($validated, $request) {
            $property = Property::create($validated);
            app(InventoryUnitService::class)->sync($property, $request->validated('units'));
            $this->recordHistory($property, 'created', 'property:'.$property->id, 'Property record created.');
            return $property;
        });

        return redirect()
            ->route('properties.show', $property)
            ->with('success', 'Property added successfully.');
    }

    public function show(Property $property): View
    {
        $property->loadSum([
            'assignments as active_quantity_assigned' => fn ($query) => $query->where('status', \App\Models\Assignment::STATUS_ACTIVE),
        ], 'quantity_assigned');
        $property->loadSum([
            'disposals as completed_quantity_disposed' => fn ($query) => $query->whereIn('status', \App\Models\Disposal::finalizedStatuses()),
        ], 'quantity_disposed');

        return view('properties.show', [
            'property' => $property,
        ]);
    }

    public function edit(Property $property): View
    {
        return view('properties.edit', [
            'property' => $property,
            'categories' => collect(Property::standardCategories())->push($property->category)->filter()->unique()->values(),
            'statuses' => Property::statuses(),
            'conditionStatuses' => Property::conditionStatuses(),
        ]);
    }

    public function update(UpdatePropertyRequest $request, Property $property): RedirectResponse
    {
        $validated = $this->preparePropertyAttributes($request->validated(), $request, $property);
        $validated['qr_token'] = ($validated['qr_token'] ?? null) ?: $property->qr_token ?: (string) Str::uuid();
        $validated['qr_reference'] = $property->qr_reference ?: $validated['qr_token'];

        DB::transaction(function () use ($property, $validated, $request) {
            $property = Property::query()->lockForUpdate()->findOrFail($property->id);
            $property->update($validated);
            app(InventoryUnitService::class)->sync($property, $request->validated('units'));
            $property->syncInventoryStatus();
            $this->recordHistory($property, 'updated', 'property:'.$property->id, 'Property record updated.');
        });

        return redirect()
            ->route('properties.show', $property)
            ->with('success', 'Property updated successfully.');
    }

    public function destroy(Property $property): RedirectResponse
    {
        $property->delete();

        return redirect()
            ->route('properties.index')
            ->with('success', 'Property deleted successfully.');
    }

    private function preparePropertyAttributes(array $validated, Request $request, ?Property $property): array
    {
        unset($validated['units']);
        $validated['category'] = $this->normalizeText($validated['category'] ?? null);
        $validated['brand'] = $this->normalizeText($validated['brand'] ?? null);
        $validated['model'] = $this->normalizeText($validated['model'] ?? null);
        $validated['office'] = $this->normalizeText($validated['office'] ?? null);
        $validated['department'] = $this->normalizeText($validated['department'] ?? null);
        $validated['location'] = $this->normalizeText($validated['location'] ?? null);
        $validated['serial_number'] = $this->normalizeText($validated['serial_number'] ?? null)
            ?: $property?->serial_number ?: Property::generateSerialNumber();
        $validated['property_category_id'] = PropertyCategory::resolveId($validated['category']);
        $validated['location_id'] = Location::resolveId($validated['location']);

        if ($request->user()?->staffProfile) {
            $validated['staff_id'] = $request->user()->staffProfile->id;
        } elseif ($property?->staff_id) {
            $validated['staff_id'] = $property->staff_id;
        }

        return $validated;
    }

    private function normalizeText(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function recordHistory(Property $property, string $actionType, string $reference, string $remarks): void
    {
        PropertyHistory::create([
            'property_id' => $property->id,
            'action_type' => $actionType,
            'reference' => $reference,
            'action_date' => now()->toDateString(),
            'remarks' => $remarks,
        ]);
    }

}
