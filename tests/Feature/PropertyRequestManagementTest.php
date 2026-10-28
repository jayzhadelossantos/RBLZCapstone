<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Property;
use App\Models\PropertyRequestRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyRequestManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_submit_a_property_request(): void
    {
        $teacher = User::factory()->create([
            'role' => User::ROLE_TEACHER,
            'email' => 'teacher.request@gmail.com',
        ]);

        $response = $this->actingAs($teacher)->post('/teacher/property-requests', [
            'requested_item_name' => 'Wireless Mouse',
            'requested_quantity' => 2,
            'needed_by' => '2026-05-01',
            'purpose' => 'Needed for classroom computer activities.',
            'additional_notes' => 'Prefer rechargeable units.',
        ]);

        $response->assertRedirect('/teacher/property-requests');

        $this->assertDatabaseHas('property_requests', [
            'requested_by' => $teacher->id,
            'requested_item_name' => 'Wireless Mouse',
            'requested_quantity' => 2,
            'status' => PropertyRequestRecord::STATUS_PENDING,
        ]);
    }

    public function test_teacher_can_request_a_replacement_for_an_active_assigned_property(): void
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $property = Property::create([
            'property_code' => 'RPL-001', 'qr_reference' => 'RPL-001', 'qr_token' => 'RPL-001',
            'property_name' => 'Classroom Projector', 'quantity' => 1, 'unit' => 'unit',
            'condition_status' => 'good', 'status' => 'available',
        ]);
        $assignment = Assignment::create([
            'property_id' => $property->id, 'teacher_id' => $teacher->id, 'assigned_by' => $staff->id,
            'quantity_assigned' => 1, 'assigned_at' => now()->toDateString(), 'date_assigned' => now()->toDateString(),
            'status' => Assignment::STATUS_ACTIVE,
        ]);

        $this->actingAs($teacher)->post('/teacher/property-requests', [
            'request_type' => PropertyRequestRecord::TYPE_REPLACEMENT,
            'replacement_assignment_id' => $assignment->id,
            'requested_item_name' => 'Changed by browser',
            'requested_quantity' => 1,
            'purpose' => 'Replace unusable classroom equipment.',
            'issue_description' => 'The projector no longer powers on.',
        ])->assertRedirect('/teacher/property-requests');

        $this->assertDatabaseHas('property_requests', [
            'requested_by' => $teacher->id,
            'request_type' => PropertyRequestRecord::TYPE_REPLACEMENT,
            'replacement_assignment_id' => $assignment->id,
            'requested_item_name' => 'Classroom Projector',
            'issue_description' => 'The projector no longer powers on.',
        ]);
    }

    public function test_teacher_can_select_an_available_inventory_item_from_the_request_catalog(): void
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $property = Property::create([
            'property_code' => 'CAT-001', 'qr_reference' => 'CAT-001', 'qr_token' => 'CAT-001',
            'property_name' => 'Wireless Keyboard', 'quantity' => 3, 'unit' => 'units',
            'condition_status' => 'good', 'status' => 'available',
        ]);

        $this->actingAs($teacher)->post('/teacher/property-requests', [
            'request_type' => PropertyRequestRecord::TYPE_NEW,
            'request_source' => 'inventory',
            'available_property_id' => $property->id,
            'requested_quantity' => 2,
            'purpose' => 'Needed for the computer laboratory.',
        ])->assertRedirect('/teacher/property-requests');

        $this->assertDatabaseHas('property_requests', [
            'requested_by' => $teacher->id,
            'requested_item_name' => 'Wireless Keyboard',
            'requested_quantity' => 2,
        ]);

        $this->assertDatabaseHas('inventories', [
            'property_id' => $property->id,
            'available_quantity' => 1,
        ]);

        $this->assertDatabaseHas('inventory_logs', [
            'property_id' => $property->id,
            'action' => 'reserved',
            'quantity_change' => -2,
            'available_after' => 1,
        ]);
    }

    public function test_teacher_cannot_submit_a_duplicate_active_request_for_the_same_item(): void
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        PropertyRequestRecord::query()->create([
            'requested_by' => $teacher->id,
            'requested_item_name' => 'Wireless Mouse',
            'requested_quantity' => 1,
            'purpose' => 'Existing active request.',
            'status' => PropertyRequestRecord::STATUS_PENDING,
        ]);

        $this->actingAs($teacher)->post('/teacher/property-requests', [
            'request_source' => 'unavailable',
            'requested_item_name' => 'wireless mouse',
            'requested_quantity' => 1,
            'purpose' => 'Duplicate request should not be saved.',
        ])->assertSessionHasErrors('requested_item_name');

        $this->assertDatabaseCount('property_requests', 1);
    }

    public function test_teacher_dashboard_shows_property_request_entry_points(): void
    {
        $teacher = User::factory()->create([
            'role' => User::ROLE_TEACHER,
            'email' => 'teacher.dashboard.request@gmail.com',
        ]);

        PropertyRequestRecord::query()->create([
            'requested_by' => $teacher->id,
            'requested_item_name' => 'Extension Cord',
            'requested_quantity' => 1,
            'needed_by' => '2026-05-03',
            'purpose' => 'Needed for classroom projector setup.',
            'status' => PropertyRequestRecord::STATUS_PENDING,
        ]);

        $response = $this->actingAs($teacher)->get('/teacher/dashboard');

        $response->assertOk();
        $response->assertSee('New Property Request');
        $response->assertSee('Request Tracking');
        $response->assertSee('Extension Cord');
    }

    public function test_supply_office_can_review_and_update_a_property_request(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'email' => 'staff.review@gmail.com',
        ]);

        $teacher = User::factory()->create([
            'role' => User::ROLE_TEACHER,
            'email' => 'teacher.review@gmail.com',
        ]);

        $propertyRequest = PropertyRequestRecord::query()->create([
            'requested_by' => $teacher->id,
            'requested_item_name' => 'Printer Ink',
            'requested_quantity' => 1,
            'needed_by' => '2026-05-05',
            'purpose' => 'Needed for printing instructional materials.',
            'status' => PropertyRequestRecord::STATUS_PENDING,
        ]);

        $showResponse = $this->actingAs($staff)->get('/property-requests/'.$propertyRequest->id);
        $showResponse->assertOk();
        $showResponse->assertSee('Review and Forward');
        $showResponse->assertSee('Printer Ink');

        $updateResponse = $this->actingAs($staff)->patch('/property-requests/'.$propertyRequest->id.'/status', [
            'status' => PropertyRequestRecord::STATUS_REVIEWED,
            'response_notes' => 'Reviewed for admin approval.',
        ]);

        $updateResponse->assertRedirect('/property-requests/'.$propertyRequest->id);

        $this->assertDatabaseHas('property_requests', [
            'id' => $propertyRequest->id,
            'reviewed_by' => $staff->id,
            'status' => PropertyRequestRecord::STATUS_REVIEWED,
            'review_notes' => 'Reviewed for admin approval.',
        ]);
    }
}
