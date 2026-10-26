<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Disposal;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PardsSampleSeeder extends Seeder
{
    /**
     * Seed the application's database with beginner-friendly PARDS sample data.
     */
    public function run(): void
    {
        $password = Hash::make('password123');

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@pards.test'],
            [
                'name' => 'PARDS Admin',
                'password' => $password,
                'role' => User::ROLE_ADMIN,
            ]
        );
        $admin->syncRoleProfile(['department' => 'Supply Office']);

        $staff = User::query()->updateOrCreate(
            ['email' => 'staff@pards.test'],
            [
                'name' => 'PARDS Staff',
                'password' => $password,
                'role' => User::ROLE_STAFF,
            ]
        );
        $staff->syncRoleProfile(['employee_number' => 'STF-001', 'department' => 'Supply Office']);

        $teacherOne = User::query()->updateOrCreate(
            ['email' => 'teacher1@pards.test'],
            [
                'name' => 'Maria Santos',
                'password' => $password,
                'role' => User::ROLE_TEACHER,
            ]
        );
        $teacherOne->syncRoleProfile(['employee_number' => 'TCH-001', 'subject_area' => 'Science']);

        $teacherTwo = User::query()->updateOrCreate(
            ['email' => 'teacher2@pards.test'],
            [
                'name' => 'Juan Dela Cruz',
                'password' => $password,
                'role' => User::ROLE_TEACHER,
            ]
        );

        $teacherThree = User::query()->updateOrCreate(
            ['email' => 'teacher3@pards.test'],
            [
                'name' => 'Ana Reyes',
                'password' => $password,
                'role' => User::ROLE_TEACHER,
            ]
        );

        $propertyOne = Property::query()->updateOrCreate(
            ['property_code' => 'PAR-2026-001'],
            [
                'property_name' => 'Desktop Computer Set',
                'category' => 'ICT Equipment',
                'description' => 'Desktop computer set for Grade 10 laboratory use.',
                'quantity' => 10,
                'unit' => 'units',
                'date_acquired' => '2025-06-10',
                'condition_status' => Property::CONDITION_GOOD,
                'status' => Property::STATUS_ASSIGNED,
                'location' => 'Computer Laboratory 1',
                'qr_reference' => 'qr-par-2026-001',
                'qr_token' => 'qr-par-2026-001',
                'qr_code_path' => 'qrcodes/par-2026-001.svg',
                'created_by' => $admin->id,
            ]
        );

        $propertyTwo = Property::query()->updateOrCreate(
            ['property_code' => 'PAR-2026-002'],
            [
                'property_name' => 'LCD Projector',
                'category' => 'Audio Visual Equipment',
                'description' => 'Portable projector for classroom presentations.',
                'quantity' => 3,
                'unit' => 'units',
                'date_acquired' => '2025-07-01',
                'condition_status' => Property::CONDITION_GOOD,
                'status' => Property::STATUS_ASSIGNED,
                'location' => 'Faculty Room',
                'qr_reference' => 'qr-par-2026-002',
                'qr_token' => 'qr-par-2026-002',
                'qr_code_path' => 'qrcodes/par-2026-002.svg',
                'created_by' => $staff->id,
            ]
        );

        $propertyThree = Property::query()->updateOrCreate(
            ['property_code' => 'PAR-2026-003'],
            [
                'property_name' => 'Office Printer',
                'category' => 'Office Equipment',
                'description' => 'Multi-function printer previously used by the registrar office.',
                'quantity' => 1,
                'unit' => 'unit',
                'date_acquired' => '2024-01-15',
                'condition_status' => Property::CONDITION_POOR,
                'status' => Property::STATUS_DISPOSED,
                'location' => 'Registrar Office',
                'qr_reference' => 'qr-par-2026-003',
                'qr_token' => 'qr-par-2026-003',
                'qr_code_path' => 'qrcodes/par-2026-003.svg',
                'created_by' => $admin->id,
            ]
        );

        $propertyFour = Property::query()->updateOrCreate(
            ['property_code' => 'PAR-2026-004'],
            [
                'property_name' => 'Steel Filing Cabinet',
                'category' => 'Furniture',
                'description' => 'Four-drawer filing cabinet for records storage.',
                'quantity' => 2,
                'unit' => 'units',
                'date_acquired' => '2025-03-12',
                'condition_status' => Property::CONDITION_FAIR,
                'status' => Property::STATUS_AVAILABLE,
                'location' => 'Property Custodian Office',
                'qr_reference' => 'qr-par-2026-004',
                'qr_token' => 'qr-par-2026-004',
                'qr_code_path' => 'qrcodes/par-2026-004.svg',
                'created_by' => $staff->id,
            ]
        );

        $propertyFive = Property::query()->updateOrCreate(
            ['property_code' => 'PAR-2026-005'],
            [
                'property_name' => 'Science Microscope',
                'category' => 'Laboratory Equipment',
                'description' => 'Microscope assigned for laboratory demonstrations.',
                'quantity' => 5,
                'unit' => 'units',
                'date_acquired' => '2025-08-20',
                'condition_status' => Property::CONDITION_NEW,
                'status' => Property::STATUS_ASSIGNED,
                'location' => 'Science Laboratory',
                'qr_reference' => 'qr-par-2026-005',
                'qr_token' => 'qr-par-2026-005',
                'qr_code_path' => 'qrcodes/par-2026-005.svg',
                'created_by' => $admin->id,
            ]
        );

        Assignment::query()->updateOrCreate(
            [
                'property_id' => $propertyOne->id,
                'teacher_id' => $teacherOne->id,
                'date_assigned' => '2026-01-15',
            ],
            [
                'assigned_by' => $staff->id,
                'quantity_assigned' => 5,
                'assigned_at' => '2026-01-15',
                'remarks' => 'Assigned for Grade 10 laboratory activities.',
                'status' => Assignment::STATUS_ACTIVE,
            ]
        );

        Assignment::query()->updateOrCreate(
            [
                'property_id' => $propertyTwo->id,
                'teacher_id' => $teacherTwo->id,
                'date_assigned' => '2026-02-01',
            ],
            [
                'assigned_by' => $admin->id,
                'quantity_assigned' => 1,
                'assigned_at' => '2026-02-01',
                'remarks' => 'Assigned for multimedia classroom presentation.',
                'status' => Assignment::STATUS_ACTIVE,
            ]
        );

        Assignment::query()->updateOrCreate(
            [
                'property_id' => $propertyFive->id,
                'teacher_id' => $teacherThree->id,
                'date_assigned' => '2026-02-18',
            ],
            [
                'assigned_by' => $staff->id,
                'quantity_assigned' => 2,
                'assigned_at' => '2026-02-18',
                'remarks' => 'Assigned for biology laboratory sessions.',
                'status' => Assignment::STATUS_ACTIVE,
            ]
        );

        Disposal::query()->updateOrCreate(
            [
                'property_id' => $propertyThree->id,
                'disposal_date' => '2026-03-10',
            ],
            [
                'disposed_by' => $admin->id,
                'quantity_disposed' => 1,
                'disposal_reason' => 'Printer is no longer repairable and replacement parts are unavailable.',
                'disposal_method' => 'condemnation',
                'remarks' => 'Approved for disposal after inspection.',
                'status' => Disposal::STATUS_COMPLETED,
            ]
        );
    }
}
