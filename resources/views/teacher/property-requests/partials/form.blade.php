@csrf

<div id="requestWizard">
    <div class="d-flex flex-wrap gap-2 mb-4" aria-label="Request steps">
        <span class="badge rounded-pill text-bg-primary px-3 py-2" data-step-indicator="1">1. Request type</span>
        <span class="badge rounded-pill text-bg-light border text-dark px-3 py-2" data-step-indicator="2">2. Select items</span>
        <span class="badge rounded-pill text-bg-light border text-dark px-3 py-2" data-step-indicator="3">3. Purpose and submit</span>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="wizard-step" data-step="1">
        <h3 class="h5">What would you like to request?</h3>
        <label for="request_type" class="form-label">Request Type</label>
        <select id="request_type" name="request_type" class="form-select @error('request_type') is-invalid @enderror" required>
            <option value="new" @selected(old('request_type', $propertyRequest->request_type ?: 'new') === 'new')>Request one or more properties</option>
            <option value="replacement" @selected(old('request_type', $propertyRequest->request_type) === 'replacement')>Replacement for damaged or unusable property</option>
        </select>
        <div class="form-text">Choose replacement only for an item already assigned to you that is broken, unusable, or needs replacement.</div>
    </div>

    <div class="wizard-step" data-step="2" hidden>
        <div id="replacementDetails" hidden>
            <h3 class="h5">Replacement details</h3>
            <div class="border rounded p-3 bg-light-subtle">
                <label for="replacement_assignment_id" class="form-label">Property to Replace</label>
                <select id="replacement_assignment_id" name="replacement_assignment_id" class="form-select @error('replacement_assignment_id') is-invalid @enderror">
                    <option value="">Choose your assigned property</option>
                    @foreach ($assignedProperties as $assignment)
                        <option value="{{ $assignment->id }}" data-name="{{ $assignment->property?->property_name }}" data-quantity="{{ $assignment->quantity_assigned }}" @selected((string) old('replacement_assignment_id', $propertyRequest->replacement_assignment_id) === (string) $assignment->id)>
                            {{ $assignment->property?->property_name }} ({{ $assignment->quantity_assigned }} assigned, {{ $assignment->property?->property_code }})
                        </option>
                    @endforeach
                </select>
                @error('replacement_assignment_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

                <label for="issue_description" class="form-label mt-3">Damage / Issue Description</label>
                <textarea id="issue_description" name="issue_description" rows="3" maxlength="3000" class="form-control @error('issue_description') is-invalid @enderror" placeholder="Describe what is broken, unusable, missing, or needs replacement.">{{ old('issue_description', $propertyRequest->issue_description) }}</textarea>
                @error('issue_description') <div class="invalid-feedback">{{ $message }}</div> @enderror

                <label for="requested_quantity" class="form-label mt-3">Replacement Quantity</label>
                <input type="number" id="requested_quantity" name="requested_quantity" min="1" class="form-control @error('requested_quantity') is-invalid @enderror" value="{{ old('requested_quantity', 1) }}">
                @error('requested_quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div id="newRequestDetails">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="h5 mb-0">Select the items you need</h3>
                <span class="badge text-bg-light border text-dark" id="selectedItemCount">0 items selected</span>
            </div>

            <div class="border rounded p-3 bg-light-subtle mt-3">
                <label class="form-label">Available Inventory Items</label>
                <div class="row g-2 mb-3">
                    <div class="col-md-7"><input id="inventory_filter" class="form-control" placeholder="Search item name, code, or brand"></div>
                    <div class="col-md-5"><select id="inventory_category_filter" class="form-select"><option value="">All categories</option>@foreach ($availableCategories as $category)<option value="{{ $category }}">{{ $category }}</option>@endforeach</select></div>
                </div>
                <div class="table-responsive border rounded bg-white">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Item</th><th>Code</th><th>Category</th><th>Available</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($availableProperties as $property)
                            <tr class="inventory-row" data-search="{{ strtolower($property->property_name.' '.$property->property_code.' '.$property->brand) }}" data-category="{{ $property->category }}">
                                <td class="fw-semibold">{{ $property->property_name }}</td>
                                <td>{{ $property->property_code }}</td>
                                <td>{{ $property->category ?: 'N/A' }}</td>
                                <td>{{ $property->inventory_available_quantity }} {{ $property->unit }}</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary inventory-add"
                                        data-property-id="{{ $property->id }}"
                                        data-name="{{ $property->property_name }}"
                                        data-available="{{ $property->inventory_available_quantity }}"
                                        data-unit="{{ $property->unit }}">Add</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No available inventory items.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="form-text">Add each item once, then adjust its requested quantity. Stock is reserved when you submit the request.</div>
            </div>

            <div class="border rounded p-3 mt-3">
                <label class="form-label">Item not in the inventory list</label>
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <input type="text" id="unavailable_item_name" class="form-control" placeholder="Enter the item you need">
                    </div>
                    <div class="col-md-3">
                        <input type="number" id="unavailable_item_quantity" class="form-control" min="1" value="1" aria-label="Unavailable item quantity">
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-outline-primary w-100" id="addUnavailableItem">Add item</button>
                    </div>
                </div>
                <div class="form-text">Use this only if the item does not appear in the available inventory list.</div>
            </div>

            <div class="mt-3">
                <h4 class="h6">Selected Items</h4>
                <div id="requestItemsEmpty" class="alert alert-light border text-muted">No items selected yet. Add at least one item to continue.</div>
                <div class="table-responsive" id="requestItemsWrapper" hidden>
                    <table class="table align-middle">
                        <thead><tr><th>Property / Item</th><th style="width: 170px;">Quantity</th><th class="text-end">Action</th></tr></thead>
                        <tbody id="requestItemsBody"></tbody>
                    </table>
                </div>
                @error('items') <div class="text-danger small">{{ $message }}</div> @enderror
                @error('items.*') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="row mt-3">
                <div class="col-md-6">
                    <label for="needed_by" class="form-label">Needed By</label>
                    <input type="date" id="needed_by" name="needed_by" class="form-control @error('needed_by') is-invalid @enderror" value="{{ old('needed_by', optional($propertyRequest->needed_by)->format('Y-m-d')) }}">
                    @error('needed_by') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="wizard-step" data-step="3" hidden>
        <h3 class="h5">Why is this needed?</h3>
        <label for="purpose" class="form-label">Purpose</label>
        <textarea id="purpose" name="purpose" rows="4" class="form-control @error('purpose') is-invalid @enderror" required>{{ old('purpose', $propertyRequest->purpose) }}</textarea>
        @error('purpose') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <label for="additional_notes" class="form-label mt-3">Additional Notes</label>
        <textarea id="additional_notes" name="additional_notes" rows="3" class="form-control @error('additional_notes') is-invalid @enderror">{{ old('additional_notes', $propertyRequest->additional_notes) }}</textarea>
        <div id="requestSummary" class="alert alert-info mt-3"></div>
        <div class="alert alert-light border mb-0">Review the details, then submit. You can track staff and admin review in Request Tracking.</div>
    </div>

    <div class="d-flex justify-content-between gap-2 mt-4">
        <a href="{{ route('teacher.property-requests.index') }}" class="btn btn-light border">Cancel</a>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" id="wizardBack" hidden>Back</button>
            <button type="button" class="btn btn-primary" id="wizardNext">Continue</button>
            <button type="submit" class="btn btn-primary" id="wizardSubmit" hidden>{{ $submitLabel }}</button>
        </div>
    </div>
</div>

@php
    $legacyRequestItems = (old('available_property_id') || old('requested_item_name')) ? [[
        'property_id' => old('available_property_id'),
        'requested_item_name' => old('requested_item_name'),
        'requested_quantity' => old('requested_quantity', 1),
        'source' => old('request_source') === 'inventory' || old('available_property_id') ? 'inventory' : 'unavailable',
    ]] : [];
@endphp

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const wizard = document.getElementById('requestWizard');
    const requestForm = wizard.closest('form');
    const requestType = document.getElementById('request_type');
    const replacementDetails = document.getElementById('replacementDetails');
    const newRequestDetails = document.getElementById('newRequestDetails');
    const replacementAssignment = document.getElementById('replacement_assignment_id');
    const issueDescription = document.getElementById('issue_description');
    const inventoryFilter = document.getElementById('inventory_filter');
    const inventoryCategoryFilter = document.getElementById('inventory_category_filter');
    const inventoryRows = Array.from(document.querySelectorAll('.inventory-row'));
    const inventoryButtons = Array.from(document.querySelectorAll('.inventory-add'));
    const unavailableName = document.getElementById('unavailable_item_name');
    const unavailableQuantity = document.getElementById('unavailable_item_quantity');
    const addUnavailableButton = document.getElementById('addUnavailableItem');
    const itemsBody = document.getElementById('requestItemsBody');
    const itemsWrapper = document.getElementById('requestItemsWrapper');
    const itemsEmpty = document.getElementById('requestItemsEmpty');
    const selectedCount = document.getElementById('selectedItemCount');
    const requestSummary = document.getElementById('requestSummary');
    const steps = Array.from(wizard.querySelectorAll('.wizard-step'));
    const backButton = document.getElementById('wizardBack');
    const nextButton = document.getElementById('wizardNext');
    const submitButton = document.getElementById('wizardSubmit');
    let currentStep = 1;

    const oldItems = @json(old('items', []));
    const legacyItem = @json($legacyRequestItems);
    const selectedItems = [];
    const initialItems = Array.isArray(oldItems) && oldItems.length ? oldItems : legacyItem;

    const parseQuantity = (value) => Math.max(1, Number.parseInt(value || 1, 10) || 1);
    const quantityOf = (item) => parseQuantity(item.requested_quantity);
    const totalQuantity = () => selectedItems.reduce((total, item) => total + quantityOf(item), 0);
    const itemLabel = (item) => item.requested_item_name || 'Unnamed item';
    const maxFor = (item) => item.available ? Number(item.available) : null;

    const addItem = (item) => {
        const existingIndex = item.property_id
            ? selectedItems.findIndex((selected) => String(selected.property_id) === String(item.property_id))
            : -1;

        if (existingIndex >= 0) {
            selectedItems[existingIndex].requested_quantity = quantityOf(selectedItems[existingIndex]) + quantityOf(item);
        } else {
            selectedItems.push({
                property_id: item.property_id || '',
                requested_item_name: item.requested_item_name || '',
                requested_quantity: quantityOf(item),
                source: item.property_id ? 'inventory' : 'unavailable',
                available: item.available || null,
                unit: item.unit || '',
            });
        }
        renderItems();
    };

    const renderItems = () => {
        itemsBody.replaceChildren();
        selectedItems.forEach((item, index) => {
            const row = document.createElement('tr');

            const nameCell = document.createElement('td');
            const name = document.createElement('div');
            name.className = 'fw-semibold';
            name.textContent = itemLabel(item);
            const source = document.createElement('small');
            source.className = 'badge text-bg-light border text-dark';
            source.textContent = item.source === 'inventory' ? 'Inventory item' : 'Not in inventory';
            nameCell.append(name, source);
            if (item.available) {
                const available = document.createElement('small');
                available.className = 'd-block text-muted';
                available.textContent = item.available + ' ' + (item.unit || 'available');
                nameCell.append(available);
            }

            const quantityCell = document.createElement('td');
            const quantity = document.createElement('input');
            quantity.type = 'number';
            quantity.min = '1';
            quantity.value = quantityOf(item);
            quantity.className = 'form-control';
            if (maxFor(item)) quantity.max = maxFor(item);
            quantity.setAttribute('aria-label', 'Quantity for ' + itemLabel(item));
            quantity.addEventListener('input', () => {
                item.requested_quantity = parseQuantity(quantity.value);
                updateSummary();
            });
            quantityCell.append(quantity);

            const actionCell = document.createElement('td');
            actionCell.className = 'text-end';
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-sm btn-outline-danger';
            remove.textContent = 'Remove';
            remove.addEventListener('click', () => {
                selectedItems.splice(index, 1);
                renderItems();
            });
            actionCell.append(remove);

            const hiddenProperty = document.createElement('input');
            hiddenProperty.type = 'hidden';
            hiddenProperty.name = 'items[' + index + '][property_id]';
            hiddenProperty.value = item.property_id || '';
            const hiddenName = document.createElement('input');
            hiddenName.type = 'hidden';
            hiddenName.name = 'items[' + index + '][requested_item_name]';
            hiddenName.value = item.requested_item_name || '';
            const hiddenQuantity = document.createElement('input');
            hiddenQuantity.type = 'hidden';
            hiddenQuantity.name = 'items[' + index + '][requested_quantity]';
            hiddenQuantity.value = quantityOf(item);
            const hiddenSource = document.createElement('input');
            hiddenSource.type = 'hidden';
            hiddenSource.name = 'items[' + index + '][source]';
            hiddenSource.value = item.source;
            quantity.addEventListener('input', () => { hiddenQuantity.value = quantityOf(item); });

            row.append(nameCell, quantityCell, actionCell, hiddenProperty, hiddenName, hiddenQuantity, hiddenSource);
            itemsBody.append(row);
        });

        itemsWrapper.hidden = selectedItems.length === 0;
        itemsEmpty.hidden = selectedItems.length > 0;
        selectedCount.textContent = selectedItems.length + (selectedItems.length === 1 ? ' item selected' : ' items selected');
        updateSummary();
    };

    const updateSummary = () => {
        const total = totalQuantity();
        requestSummary.textContent = selectedItems.length
            ? selectedItems.length + ' item(s), ' + total + ' total quantity. Verify the quantities before submitting.'
            : 'No items selected yet.';
    };

    const toggleRequestType = () => {
        const isReplacement = requestType.value === 'replacement';
        replacementDetails.hidden = !isReplacement;
        newRequestDetails.hidden = isReplacement;
        replacementAssignment.required = isReplacement;
        replacementAssignment.disabled = !isReplacement;
        issueDescription.required = isReplacement;
        issueDescription.disabled = !isReplacement;
        const replacementQuantity = document.getElementById('requested_quantity');
        if (replacementQuantity) {
            replacementQuantity.required = isReplacement;
            replacementQuantity.disabled = !isReplacement;
        }
    };

    const filterInventory = () => {
        const search = inventoryFilter.value.toLowerCase().trim();
        const category = inventoryCategoryFilter.value;
        inventoryRows.forEach((row) => row.hidden = !(row.dataset.search.includes(search) && (!category || row.dataset.category === category)));
    };

    const showStep = (step) => {
        currentStep = step;
        steps.forEach((panel) => panel.hidden = Number(panel.dataset.step) !== step);
        wizard.querySelectorAll('[data-step-indicator]').forEach((indicator) => {
            const active = Number(indicator.dataset.stepIndicator) === step;
            indicator.className = `badge rounded-pill px-3 py-2 ${active ? 'text-bg-primary' : 'text-bg-light border text-dark'}`;
        });
        backButton.hidden = step === 1;
        nextButton.hidden = step === steps.length;
        submitButton.hidden = step !== steps.length;
        if (step === 3) updateSummary();
    };

    const validateCurrentStep = () => {
        if (currentStep === 2 && requestType.value === 'new') {
            if (!selectedItems.length) {
                itemsEmpty.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
            const invalidQuantity = selectedItems.find((item) => item.available && quantityOf(item) > Number(item.available));
            if (invalidQuantity) {
                alert('The quantity for ' + itemLabel(invalidQuantity) + ' exceeds the available stock of ' + invalidQuantity.available + '.');
                return false;
            }
        }

        const invalidField = Array.from(steps[currentStep - 1].querySelectorAll('input, select, textarea'))
            .filter((field) => !field.disabled && field.type !== 'hidden')
            .find((field) => !field.checkValidity());
        if (invalidField) {
            invalidField.reportValidity();
            return false;
        }
        return true;
    };

    inventoryButtons.forEach((button) => button.addEventListener('click', () => {
        addItem({
            property_id: button.dataset.propertyId,
            requested_item_name: button.dataset.name,
            requested_quantity: 1,
            available: button.dataset.available,
            unit: button.dataset.unit,
        });
    }));

    addUnavailableButton.addEventListener('click', () => {
        const name = unavailableName.value.trim();
        const quantity = parseQuantity(unavailableQuantity.value);
        if (!name) {
            unavailableName.reportValidity();
            return;
        }
        addItem({ property_id: '', requested_item_name: name, requested_quantity: quantity, source: 'unavailable' });
        unavailableName.value = '';
        unavailableQuantity.value = 1;
    });

    requestType.addEventListener('change', toggleRequestType);
    inventoryFilter.addEventListener('input', filterInventory);
    inventoryCategoryFilter.addEventListener('change', filterInventory);
    nextButton.addEventListener('click', () => validateCurrentStep() && showStep(currentStep + 1));
    backButton.addEventListener('click', () => showStep(currentStep - 1));

    requestForm.addEventListener('submit', (event) => {
        for (let step = 1; step <= steps.length; step++) {
            showStep(step);
            if (!validateCurrentStep()) {
                event.preventDefault();
                return;
            }
        }
        if (requestType.value === 'new' && !selectedItems.length) {
            event.preventDefault();
            showStep(2);
        }
    });

    initialItems.forEach((item) => addItem({
        property_id: item.property_id || '',
        requested_item_name: item.requested_item_name || '',
        requested_quantity: item.requested_quantity || 1,
        available: item.available || null,
        unit: item.unit || '',
    }));

    toggleRequestType();
    renderItems();
    showStep({{ $errors->any() ? 3 : 1 }});
});
</script>
@endpush
