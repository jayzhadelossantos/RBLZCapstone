<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrStickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_print_a_property_sticker_with_its_current_assignee(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER, 'name' => 'Sticker Owner']);
        $property = Property::create([
            'property_code' => 'STK-001', 'qr_reference' => 'STK-001', 'qr_token' => 'sticker-token',
            'property_name' => 'Classroom Tablet', 'serial_number' => 'TAB-001', 'quantity' => 1,
            'unit' => 'unit', 'condition_status' => 'good', 'status' => 'assigned', 'location' => 'Room 101',
        ]);
        Assignment::create([
            'property_id' => $property->id, 'teacher_id' => $teacher->id, 'assigned_by' => $admin->id,
            'quantity_assigned' => 1, 'assigned_at' => now()->toDateString(), 'date_assigned' => now()->toDateString(),
            'location' => 'Room 101', 'status' => Assignment::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)->get('/properties/'.$property->id.'/qr/print')
            ->assertOk()
            ->assertSee('PARDS PROPERTY STICKER')
            ->assertSee('Classroom Tablet')
            ->assertSee('TAB-001')
            ->assertSee('Sticker Owner');
    }
}
