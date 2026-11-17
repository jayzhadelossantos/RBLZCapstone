<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Inventory;
use App\Models\Property;
use App\Models\PropertyRequestItem;
use App\Models\PropertyRequestRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiItemPropertyRequestTest extends TestCase
{
    use RefreshDatabase;

    private function property(string $code, int $quantity): Property
    {
        return Property::create([
            'property_code' => $code,
            'qr_reference' => $code,
            'qr_token' => $code,
            'property_name' => $code.' Item',
            'quantity' => $quantity,
            'unit' => 'units',
            'condition_status' => 'good',
            'status' => 'available',
        ]);
    }

    public function test_teacher_can_submit_and_fulfill_multiple_request_items(): void
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $first = $this->property('MULTI-A', 5);
        $second = $this->property('MULTI-B', 4);
        $replacement = $this->property('MULTI-C', 3);

        $this->actingAs($teacher)->post('/teacher/property-requests', [
            'request_type' => PropertyRequestRecord::TYPE_NEW,
            'needed_by' => '2026-12-01',
            'purpose' => 'Materials for the upcoming classroom activities.',
            'items' => [
                ['property_id' => $first->id, 'requested_item_name' => $first->property_name, 'requested_quantity' => 2, 'source' => 'inventory'],
                ['property_id' => $second->id, 'requested_item_name' => $second->property_name, 'requested_quantity' => 1, 'source' => 'inventory'],
                ['property_id' => '', 'requested_item_name' => 'Extension Cord', 'requested_quantity' => 2, 'source' => 'unavailable'],
            ],
        ])->assertRedirect('/teacher/property-requests')->assertSessionHasNoErrors();

        $request = PropertyRequestRecord::firstOrFail();
        $this->assertNotNull($request->reference_no);
        $this->assertSame(3, $request->items()->count());
        $this->assertSame(5, $request->requested_quantity);
        $this->assertDatabaseHas('property_request_items', [
            'property_request_id' => $request->id,
            'requested_item_name' => 'Extension Cord',
            'requested_quantity' => 2,
        ]);
        $this->assertSame(3, Inventory::where('property_id', $first->id)->value('available_quantity'));
        $this->assertSame(3, Inventory::where('property_id', $second->id)->value('available_quantity'));
        $this->assertSame(3, Inventory::where('property_id', $replacement->id)->value('available_quantity'));

        $this->actingAs($staff)->patch('/property-requests/'.$request->id.'/status', [
            'status' => PropertyRequestRecord::STATUS_REVIEWED,
            'response_notes' => 'All items checked.',
        ])->assertSessionHasNoErrors();

        $items = $request->items()->get()->keyBy('requested_item_name');
        $this->actingAs($admin)->patch('/property-requests/'.$request->id.'/status', [
            'status' => PropertyRequestRecord::STATUS_APPROVED,
            'items' => [
                $items[$first->property_name]->id => ['property_id' => $first->id],
                $items[$second->property_name]->id => ['property_id' => $second->id],
                $items['Extension Cord']->id => ['property_id' => $replacement->id],
            ],
        ])->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame(PropertyRequestRecord::STATUS_APPROVED, $request->status);
        $this->assertSame(3, $request->items()->whereNotNull('approved_property_id')->count());
        $this->assertDatabaseCount('assignments', 0);

        $this->actingAs($staff)->patch('/property-requests/'.$request->id.'/status', [
            'status' => PropertyRequestRecord::STATUS_FULFILLED,
            'department' => 'Science Department',
        ])->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame(PropertyRequestRecord::STATUS_FULFILLED, $request->status);
        $this->assertSame(3, Assignment::count());
        $this->assertSame(3, $request->items()->whereNotNull('assignment_id')->count());
        $this->assertSame(3, $first->fresh()->quantity);
        $this->assertSame(3, $second->fresh()->quantity);
        $this->assertSame(1, $replacement->fresh()->quantity);
        $this->assertSame('Science Department', Assignment::where('property_id', $replacement->id)->value('department'));
        $this->assertSame(5, (int) $request->items()->sum('requested_quantity'));
        $this->assertSame(0, PropertyRequestItem::whereNull('assignment_id')->count());

        $this->actingAs($teacher)->get('/teacher/property-requests')
            ->assertOk()
            ->assertSee($request->reference_number)
            ->assertSee('Extension Cord');
    }

    public function test_multi_item_request_rejects_quantity_above_available_stock(): void
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $property = $this->property('LIMIT-A', 2);

        $this->actingAs($teacher)->post('/teacher/property-requests', [
            'purpose' => 'Test stock validation.',
            'items' => [
                ['property_id' => $property->id, 'requested_item_name' => $property->property_name, 'requested_quantity' => 3, 'source' => 'inventory'],
            ],
        ])->assertSessionHasErrors('items.0.requested_quantity');

        $this->assertDatabaseCount('property_requests', 0);
        $this->assertSame(2, Inventory::where('property_id', $property->id)->value('available_quantity'));
    }

    public function test_request_pdf_is_available_to_the_owner_and_authorized_staff(): void
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $property = $this->property('PDF-A', 2);
        $other = $this->property('PDF-B', 2);

        $this->actingAs($teacher)->get('/teacher/property-requests/create')
            ->assertOk()
            ->assertSee('Select the items you need');

        $this->actingAs($teacher)->post('/teacher/property-requests', [
            'purpose' => 'PDF export test.',
            'items' => [
                ['property_id' => $property->id, 'requested_item_name' => $property->property_name, 'requested_quantity' => 1, 'source' => 'inventory'],
                ['property_id' => $other->id, 'requested_item_name' => $other->property_name, 'requested_quantity' => 2, 'source' => 'inventory'],
            ],
        ])->assertSessionHasNoErrors();

        $request = PropertyRequestRecord::firstOrFail();

        $this->actingAs($teacher)->get('/property-requests/'.$request->id.'/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('property-request-'.$request->reference_number.'.pdf');

        $this->actingAs($staff)->get('/property-requests/'.$request->id.'/pdf')->assertOk();

        $otherTeacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $this->actingAs($otherTeacher)->get('/property-requests/'.$request->id.'/pdf')->assertForbidden();
    }
}
