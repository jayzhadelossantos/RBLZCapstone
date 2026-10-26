<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';
    public const ROLE_TEACHER = 'teacher';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'two_factor_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getCreatedAtForDisplayAttribute(): ?Carbon
    {
        $createdAt = $this->getRawOriginal('created_at');

        return filled($createdAt)
            ? Carbon::parse($createdAt, 'UTC')->timezone('Asia/Manila')
            : null;
    }

    public function freshTimestamp(): Carbon
    {
        return Carbon::now('UTC');
    }

    public function propertiesCreated(): HasMany
    {
        return $this->hasMany(Property::class, 'created_by');
    }

    public function profilePhoto(): HasOne
    {
        return $this->hasOne(ProfilePhoto::class);
    }

    public function adminProfile(): HasOne
    {
        return $this->hasOne(Admin::class);
    }

    public function getAdminProfileAttribute(): ?Admin
    {
        if (! $this->isAdmin()) {
            return null;
        }

        $profile = $this->getRelationValue('adminProfile');

        if (! $profile && $this->exists) {
            $profile = Admin::query()->firstOrCreate(['user_id' => $this->id]);
            $this->setRelation('adminProfile', $profile);
        }

        return $profile;
    }

    public static function activeAdmins()
    {
        return static::query()
            ->where('role', self::ROLE_ADMIN)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    public static function defaultAdmin(): ?self
    {
        return static::activeAdmins()->first();
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'teacher_id');
    }

    public function assignmentsIssued(): HasMany
    {
        return $this->hasMany(Assignment::class, 'assigned_by');
    }

    public function disposalsRecorded(): HasMany
    {
        return $this->hasMany(Disposal::class, 'disposed_by');
    }

    public function propertyRequests(): HasMany
    {
        return $this->hasMany(PropertyRequestRecord::class, 'requested_by');
    }

    public function processedPropertyRequests(): HasMany
    {
        return $this->hasMany(PropertyRequestRecord::class, 'processed_by');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    public function isTeacher(): bool
    {
        return $this->role === self::ROLE_TEACHER;
    }

    public static function roles(): array
    {
        return [
            self::ROLE_ADMIN,
            self::ROLE_STAFF,
            self::ROLE_TEACHER,
        ];
    }

    public function syncRoleProfile(array $attributes = []): void
    {
        $role = $attributes['role'] ?? $this->role;

        if ($role === self::ROLE_ADMIN) {
            Admin::query()->updateOrCreate(
                ['user_id' => $this->id],
                ['department' => $this->normalizeProfileValue($attributes['department'] ?? $this->department)]
            );
            Staff::query()->where('user_id', $this->id)->delete();
            Teacher::query()->where('user_id', $this->id)->delete();
        }

        if ($role === self::ROLE_STAFF) {
            Staff::query()->updateOrCreate(
                ['user_id' => $this->id],
                [
                    'employee_number' => $this->normalizeProfileValue($attributes['employee_number'] ?? $this->employee_number),
                    'department' => $this->normalizeProfileValue($attributes['department'] ?? $this->department),
                ]
            );
            Admin::query()->where('user_id', $this->id)->delete();
            Teacher::query()->where('user_id', $this->id)->delete();
        }

        if ($role === self::ROLE_TEACHER) {
            Teacher::query()->updateOrCreate(
                ['user_id' => $this->id],
                [
                    'employee_number' => $this->normalizeProfileValue($attributes['employee_number'] ?? $this->employee_number),
                    'subject_area' => $this->normalizeProfileValue($attributes['subject_area'] ?? $this->subject_area),
                ]
            );
            Admin::query()->where('user_id', $this->id)->delete();
            Staff::query()->where('user_id', $this->id)->delete();
        }
    }

    public function getEmployeeNumberAttribute(): ?string
    {
        return $this->teacherProfile?->employee_number ?? $this->staffProfile?->employee_number;
    }

    public function getDepartmentAttribute(): ?string
    {
        return $this->adminProfile?->department ?? $this->staffProfile?->department;
    }

    public function getSubjectAreaAttribute(): ?string
    {
        return $this->teacherProfile?->subject_area;
    }

    private function normalizeProfileValue(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
