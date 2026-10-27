<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Assignment;
use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_create_has_per_unit_serial_fields(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'email' => 'admin.property.create.serial@gmail.com',
        ]);

        $response = $this->actingAs($admin)->get('/properties/create');

        $response->assertOk();
        $response->assertSee('name="units[0][serial_number]"', false);
        $response->assertDontSee('name="serial_number"', false);
        $response->assertSee('name="unit"', false);
        $response->assertSee('data-infer-unit="true"', false);
    }

    public function test_property_store_requires_a_property_code(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'email' => 'admin.property@gmail.com',
        ]);

        $response = $this->actingAs($admin)->post('/properties', [
            'property_name' => 'Office Chair',
            'property_code' => '',
            'category' => 'Furniture',
            'description' => 'Chair without a property code',
            'quantity' => 2,
            'unit' => 'units',
            'date_acquired' => '2026-04-21',
            'condition_status' => Property::CONDITION_GOOD,
            'status' => Property::STATUS_AVAILABLE,
            'location' => 'Supply Office',
        ]);

        $response->assertSessionHasErrors('property_code');
    }

    public function test_property_store_auto_generates_serial_number_when_blank(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'email' => 'admin.property.auto.serial@gmail.com',
        ]);

        $response = $this->actingAs($admin)->post('/properties', [
            'property_name' => 'Tablet',
            'property_code' => 'PAR-PROP-001',
            'serial_number' => '',
            'category' => 'ICT',
            'description' => 'Tablet with generated serial number',
            'quantity' => 1,
            'unit' => 'unit',
            'date_acquired' => '2026-04-21',
            'condition_status' => Property::CONDITION_GOOD,
            'status' => Property::STATUS_AVAILABLE,
            'location' => 'Supply Office',
        ]);

        $response->assertRedirect();

        $property = Property::query()->where('property_code', 'PAR-PROP-001')->firstOrFail();

        $this->assertStringStartsWith('SN-', $property->serial_number);
    }

    public function test_property_store_creates_qr_tokens_for_each_piece(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'email' => 'admin.property.unit.qr@gmail.com',
        ]);

        $response = $this->actingAs($admin)->post('/properties', [
            'property_name' => 'Keyboard',
            'property_code' => 'PAR-PROP-QR-001',
            'serial_number' => '',
            'category' => 'ICT',
            'description' => 'Keyboard with per-piece QR codes',
            'quantity' => 2,
            'unit' => 'units',
            'date_acquired' => '2026-04-21',
            'condition_status' => Property::CONDITION_GOOD,
            'status' => Property::STATUS_AVAILABLE,
            'location' => 'Supply Office',
        ]);

        $response->assertRedirect();

        $property = Property::query()->where('property_code', 'PAR-PROP-QR-001')->firstOrFail();
        $units = PropertyUnit::query()->where('property_id', $property->id)->get();

        $this->assertCount(2, $units);
        $this->assertTrue($units->every(fn (PropertyUnit $unit) => filled($unit->qr_token)));

        $indexResponse = $this->actingAs($admin)->get('/qr-codes');

        $indexResponse->assertOk();
        $units->each(function (PropertyUnit $unit) use ($indexResponse): void {
            $indexResponse->assertSee($unit->serial_number);
        });

        $scanResponse = $this->get('/qr/'.$units->first()->qr_token);

        $scanResponse->assertOk();
        $scanResponse->assertSee('PARDS QR Property Unit Details');
        $scanResponse->assertSee($units->first()->serial_number);
    }

    public function test_property_store_keeps_custom_serial_number(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'email' => 'admin.property.custom.serial@gmail.com',
        ]);

        $response = $this->actingAs($admin)->post('/properties', [
            'property_name' => 'Projector',
            'property_code' => 'PAR-PROP-001-CUSTOM',
            'serial_number' => 'PROJECTOR-SN-777',
            'category' => 'ICT',
            'description' => 'Projector with custom serial number',
            'quantity' => 1,
            'unit' => 'unit',
            'date_acquired' => '2026-04-21',
            'condition_status' => Property::CONDITION_GOOD,
            'status' => Property::STATUS_AVAILABLE,
            'location' => 'Supply Office',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('properties', [
            'property_code' => 'PAR-PROP-001-CUSTOM',
            'serial_number' => 'PROJECTOR-SN-777',
        ]);
    }

    public function test_property_index_keeps_total_quantity_and_shows_available_after_assignment(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'email' => 'admin.property.quantity.display@gmail.com',
        ]);

        $teacher = User::factory()->create([
            'role' => User::ROLE_TEACHER,
            'email' => 'teacher.property.quantity.display@gmail.com',
        ]);

        $property = Property::query()->create([
            'property_name' => 'Science Chair',
            'property_code' => 'PAR-PROP-QTY-001',
            'serial_number' => 'SN-QTY-001',
            'category' => 'Furniture',
            'description' => 'Quantity display test',
            'quantity' => 3,
            'unit' => 'units',
            'date_acquired' => '2026-04-21',
            'condition_status' => Property::CONDITION_GOOD,
            'status' => Property::STATUS_ASSIGNED,
            'location' => 'Science Lab',
            'qr_reference' => 'qr-prop-qty-001',
            'qr_token' => 'qr-prop-qty-001',
        ]);

        Assignment::query()->create([
            'property_id' => $property->id,
            'teacher_id' => $teacher->id,
            'assigned_by' => $admin->id,
            'quantity_assigned' => 2,
            'date_assigned' => '2026-04-21',
            'assigned_at' => '2026-04-21',
            'status' => Assignment::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($admin)->get('/properties');

        $response->assertOk();
        $response->assertSeeInOrder([
            'PAR-PROP-QTY-001',
            'SN-QTY-001',
            'Science Chair',
            '5 units',
            '3 units',
            '2 units',
        ]);
    }

    public function test_property_show_displays_property_code_details(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'email' => 'admin.property.links@gmail.com',
        ]);

        $property = Property::query()->create([
            'property_name' => 'Desktop Set',
            'property_code' => 'PAR-PROP-002',
            'serial_number' => 'SN-DSP-002',
            'category' => 'ICT',
            'brand' => 'Dell',
            'model' => 'OptiPlex 7090',
            'description' => 'Desktop computer set',
            'quantity' => 3,
            'unit' => 'units',
            'date_acquired' => '2026-04-21',
            'condition_status' => Property::CONDITION_GOOD,
            'status' => Property::STATUS_AVAILABLE,
            'office' => 'Registrar Office',
            'location' => 'Supply Office',
            'qr_reference' => 'qr-prop-002',
            'qr_token' => 'qr-prop-002',
        ]);

        $response = $this->actingAs($admin)->get('/properties/'.$property->id.'?unit_number=DSP-002');

        $response->assertOk();
        $response->assertSee('Property Code: PAR-PROP-002');
        $response->assertSee('Serial Number: SN-DSP-002');
        $response->assertSee('Dell');
        $response->assertSee('OptiPlex 7090');
        $response->assertSee('Registrar Office');
        $response->assertSee('Desktop Set');
        $response->assertDontSee('Selected Item Number');
    }

    public function test_property_store_rejects_duplicate_property_code(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'email' => 'admin.property.series@gmail.com',
        ]);

        Property::query()->create([
            'property_name' => 'Monitor',
            'property_code' => 'PAR-PROP-003',
            'category' => 'ICT',
            'description' => 'Existing monitor record',
            'quantity' => 3,
            'unit' => 'units',
            'date_acquired' => '2026-04-21',
            'condition_status' => Property::CONDITION_GOOD,
            'status' => Property::STATUS_AVAILABLE,
            'location' => 'Lab 1',
            'qr_reference' => 'qr-prop-003',
            'qr_token' => 'qr-prop-003',
        ]);

        $response = $this->actingAs($admin)->post('/properties', [
            'property_name' => 'Second Monitor Batch',
            'property_code' => 'PAR-PROP-003',
            'category' => 'ICT',
            'description' => 'Conflicting monitor record',
            'quantity' => 2,
            'unit' => 'units',
            'date_acquired' => '2026-04-21',
            'condition_status' => Property::CONDITION_GOOD,
            'status' => Property::STATUS_AVAILABLE,
            'location' => 'Lab 2',
        ]);

        $response->assertSessionHasErrors('property_code');
    }

    public function test_property_store_rejects_duplicate_serial_number(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'email' => 'admin.property.serials@gmail.com',
        ]);

        Property::query()->create([
            'property_name' => 'Laptop',
            'property_code' => 'PAR-PROP-004',
            'serial_number' => 'SN-LAP-004',
            'category' => 'ICT',
            'description' => 'Existing laptop record',
            'quantity' => 1,
            'unit' => 'unit',
            'date_acquired' => '2026-04-21',
            'condition_status' => Property::CONDITION_GOOD,
            'status' => Property::STATUS_AVAILABLE,
            'location' => 'Lab 1',
            'qr_reference' => 'qr-prop-004',
            'qr_token' => 'qr-prop-004',
        ]);

        $response = $this->actingAs($admin)->post('/properties', [
            'property_name' => 'Second Laptop',
            'property_code' => 'PAR-PROP-005',
            'serial_number' => 'SN-LAP-004',
            'category' => 'ICT',
            'description' => 'Conflicting serial number',
            'quantity' => 1,
            'unit' => 'unit',
            'date_acquired' => '2026-04-21',
            'condition_status' => Property::CONDITION_GOOD,
            'status' => Property::STATUS_AVAILABLE,
            'location' => 'Lab 2',
        ]);

        $response->assertSessionHasErrors('serial_number');
    }
}
