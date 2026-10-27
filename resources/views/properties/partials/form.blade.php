@csrf

<div class="mb-4">
    <label for="department" class="form-label">Department</label>
    <input type="text" id="department" name="department" maxlength="255" placeholder="Example: Science Department"
        class="form-control @error('department') is-invalid @enderror" value="{{ old('department', $property->department) }}">
    @error('department')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

@php
    $selectedQuantity = max(0, min(1000, (int) old('quantity', $property->quantity ?? 1)));
    $selectedUnitCost = old('unit_cost', $property->unit_cost);
    $measurementUnits = \App\Models\Property::measurementUnits();
    $selectedMeasurementUnit = old('unit', $property->unit ?: 'piece');
    $initialTotalCost = is_numeric($selectedUnitCost)
        ? number_format((float) $selectedUnitCost * $selectedQuantity, 2, '.', ',')
        : number_format(0, 2, '.', ',');
@endphp

<div class="row g-4">
    <div class="col-md-4">
        <label for="property_name" class="form-label">Property Name</label>
        <input
            type="text"
            id="property_name"
            name="property_name"
            class="form-control @error('property_name') is-invalid @enderror"
            value="{{ old('property_name', $property->property_name) }}"
            required
        >
        @error('property_name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="property_code" class="form-label">Property Code</label>
        <input
            type="text"
            id="property_code"
            name="property_code"
            class="form-control @error('property_code') is-invalid @enderror"
            value="{{ old('property_code', $property->property_code) }}"
            required
        >
        @error('property_code')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="category" class="form-label">Category</label>
        <input
            type="text"
            id="category"
            name="category"
            class="form-control @error('category') is-invalid @enderror"
            value="{{ old('category', $property->category) }}"
        >
        @error('category')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="quantity" class="form-label">{{ $property->exists ? 'Available Quantity' : 'Total Quantity' }}</label>
        <input
            type="number"
            id="quantity"
            name="quantity"
            min="{{ $property->exists ? 0 : 1 }}" max="1000"
            class="form-control @error('quantity') is-invalid @enderror"
            value="{{ old('quantity', $property->quantity ?? 1) }}"
            required
        >
        @error('quantity')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="unit" class="form-label">Unit</label>
        <select id="unit" name="unit" class="form-select @error('unit') is-invalid @enderror" data-infer-unit="{{ $property->exists ? 'false' : 'true' }}" required>
            @foreach ($measurementUnits as $measurementUnit)
                <option value="{{ $measurementUnit }}" @selected($selectedMeasurementUnit === $measurementUnit)>{{ ucfirst($measurementUnit) }}</option>
            @endforeach
        </select>
        <div class="form-text">Automatically set from the property name; choose a different preset only when needed.</div>
        @error('unit')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        @include('properties.partials.unit-serials')
    </div>

    <div class="col-md-6">
        <label for="unit_cost" class="form-label">Unit Cost</label>
        <div class="input-group">
            <span class="input-group-text">PHP</span>
            <input
                type="number"
                id="unit_cost"
                name="unit_cost"
                min="0"
                step="0.01"
                inputmode="decimal"
                class="form-control @error('unit_cost') is-invalid @enderror"
                value="{{ $selectedUnitCost }}"
                placeholder="0.00"
                data-unit-cost-input
            >
        </div>
        @error('unit_cost')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="total_cost_display" class="form-label">Total Cost</label>
        <div class="input-group">
            <span class="input-group-text">PHP</span>
            <input
                type="text"
                id="total_cost_display"
                class="form-control"
                value="{{ $initialTotalCost }}"
                readonly
                data-total-cost-output
            >
        </div>
    </div>

    <div class="col-md-6">
        <label for="date_acquired" class="form-label">Date Acquired</label>
        <input
            type="date"
            id="date_acquired"
            name="date_acquired"
            class="form-control @error('date_acquired') is-invalid @enderror"
            value="{{ old('date_acquired', optional($property->date_acquired)->format('Y-m-d')) }}"
        >
        @error('date_acquired')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="condition_status" class="form-label">Condition Status</label>
        <select
            id="condition_status"
            name="condition_status"
            class="form-select @error('condition_status') is-invalid @enderror"
            required
        >
            @foreach ($conditionStatuses as $condition)
                <option value="{{ $condition }}" @selected(old('condition_status', $property->condition_status ?: 'good') === $condition)>
                    {{ ucfirst(str_replace('_', ' ', $condition)) }}
                </option>
            @endforeach
        </select>
        @error('condition_status')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="status" class="form-label">Status</label>
        <select
            id="status"
            name="status"
            class="form-select @error('status') is-invalid @enderror"
            required
        >
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $property->status ?: 'available') === $status)>
                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                </option>
            @endforeach
        </select>
        @error('status')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <div class="border-top pt-4 mt-2">
            <h3 class="h6 mb-1">Specifications</h3>
        </div>
    </div>

    <div class="col-md-6">
        <label for="brand" class="form-label">Brand</label>
        <input
            type="text"
            id="brand"
            name="brand"
            class="form-control @error('brand') is-invalid @enderror"
            value="{{ old('brand', $property->brand) }}"
            placeholder="Optional brand name"
        >
        @error('brand')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="model" class="form-label">Model</label>
        <input
            type="text"
            id="model"
            name="model"
            class="form-control @error('model') is-invalid @enderror"
            value="{{ old('model', $property->model) }}"
            placeholder="Optional model or specification"
        >
        @error('model')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="office" class="form-label">Office</label>
        <input
            type="text"
            id="office"
            name="office"
            class="form-control @error('office') is-invalid @enderror"
            value="{{ old('office', $property->office) }}"
            placeholder="Example: Registrar Office"
        >
        @error('office')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="location" class="form-label">Location</label>
        <input
            type="text"
            id="location"
            name="location"
            class="form-control @error('location') is-invalid @enderror"
            value="{{ old('location', $property->location) }}"
            placeholder="Example: Building A, Room 101"
        >
        @error('location')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="qr_token" class="form-label">QR Token</label>
        <input
            type="text"
            id="qr_token"
            name="qr_token"
            class="form-control @error('qr_token') is-invalid @enderror"
            value="{{ old('qr_token', $property->qr_token) }}"
        >
        @error('qr_token')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="qr_code_path" class="form-label">QR Code Path</label>
        <input
            type="text"
            id="qr_code_path"
            name="qr_code_path"
            class="form-control @error('qr_code_path') is-invalid @enderror"
            value="{{ old('qr_code_path', $property->qr_code_path) }}"
        >
        @error('qr_code_path')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Description</label>
        <textarea
            id="description"
            name="description"
            rows="4"
            class="form-control @error('description') is-invalid @enderror"
        >{{ old('description', $property->description) }}</textarea>
        @error('description')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="d-flex flex-column-reverse flex-sm-row justify-content-end gap-2 mt-4">
    <a href="{{ route('properties.index') }}" class="btn btn-light border">Cancel</a>
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const quantityInput = document.querySelector('#quantity');
                const propertyNameInput = document.querySelector('#property_name');
                const categoryInput = document.querySelector('#category');
                const unitSelect = document.querySelector('#unit');
                const unitCostInput = document.querySelector('[data-unit-cost-input]');
                const totalCostOutput = document.querySelector('[data-total-cost-output]');

                if (!quantityInput || !unitCostInput || !totalCostOutput) {
                    return;
                }

                const formatter = new Intl.NumberFormat('en-PH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });

                const updateTotalCost = () => {
                    const quantity = Number.parseFloat(quantityInput.value || '0');
                    const unitCost = Number.parseFloat(unitCostInput.value || '0');
                    const totalCost = Number.isFinite(quantity) && Number.isFinite(unitCost)
                        ? quantity * unitCost
                        : 0;

                    totalCostOutput.value = formatter.format(totalCost);
                };

                quantityInput.addEventListener('input', updateTotalCost);
                unitCostInput.addEventListener('input', updateTotalCost);

                const inferUnit = () => {
                    if (unitSelect.dataset.inferUnit !== 'true' || unitSelect.dataset.userSelected === 'true') return;

                    const description = `${propertyNameInput?.value || ''} ${categoryInput?.value || ''}`.toLowerCase();
                    const inferredUnit = /paper|bond/.test(description) ? 'ream'
                        : /toner|ink/.test(description) ? 'cartridge'
                            : /cable|wire/.test(description) ? 'meter'
                                : /paint|alcohol|cleaner|liquid/.test(description) ? 'liter'
                                    : /chair|table|cabinet|laptop|computer|projector|keyboard|mouse|printer|furniture/.test(description) ? 'piece'
                                        : 'piece';

                    unitSelect.value = inferredUnit;
                };

                unitSelect.addEventListener('change', () => unitSelect.dataset.userSelected = 'true');
                propertyNameInput?.addEventListener('input', inferUnit);
                categoryInput?.addEventListener('input', inferUnit);

                updateTotalCost();
                inferUnit();
            });
        </script>
    @endpush
@endonce
