<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Property extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_FOR_DISPOSAL = 'for_disposal';

    public const STATUS_DISPOSED = 'disposed';

    public const CONDITION_NEW = 'new';

    public const CONDITION_GOOD = 'good';

    public const CONDITION_FAIR = 'fair';

    public const CONDITION_POOR = 'poor';

    public const CONDITION_DAMAGED = 'damaged';

    protected $fillable = [
        'property_name',
        'property_code',
        'category',
        'property_category_id',
        'description',
        'brand',
        'model',
        'serial_number',
        'unit_cost',
        'quantity',
        'unit',
        'date_acquired',
        'acquired_at',
        'condition_status',
        'status',
        'location',
        'office',
        'department',
        'location_id',
        'qr_reference',
        'qr_token',
        'qr_code_path',
        'created_by',
        'staff_id',
    ];

    public function getUnitSerialNumbersAttribute(): string
    {
        return $this->units->pluck('serial_number')->join(', ') ?: (string) $this->serial_number;
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'date_acquired' => 'date',
            'acquired_at' => 'date',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_AVAILABLE,
            self::STATUS_ASSIGNED,
            self::STATUS_FOR_DISPOSAL,
            self::STATUS_DISPOSED,
        ];
    }

    public static function conditionStatuses(): array
    {
        return [
            self::CONDITION_NEW,
            self::CONDITION_GOOD,
            self::CONDITION_FAIR,
            self::CONDITION_POOR,
            self::CONDITION_DAMAGED,
        ];
    }

    public static function measurementUnits(): array
    {
        return [
            'piece',
            'units',
            'unit',
            'set',
            'pair',
            'pack',
            'box',
            'ream',
            'roll',
            'bundle',
            'cartridge',
            'liter',
            'kilogram',
            'meter',
        ];
    }

    public static function standardCategories(): array
    {
        return [
            'Furniture',
            'ICT Equipment',
            'Office Equipment',
            'Office Supplies',
            'Classroom Equipment',
            'Laboratory Equipment',
            'Electrical Equipment',
            'Cleaning Supplies',
            'Other',
        ];
    }

    public static function generateSerialNumber(): string
    {
        do {
            $serialNumber = 'SN-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (
            self::query()->where('serial_number', $serialNumber)->exists()
            || PropertyUnit::query()->where('serial_number', $serialNumber)->exists()
        );

        return $serialNumber;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function managedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function propertyLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function propertyCategory(): BelongsTo
    {
        return $this->belongsTo(PropertyCategory::class, 'property_category_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    protected static function booted(): void
    {
        static::created(function (self $property): void {
            if ($property->quantity > 0 && ! in_array($property->status, [self::STATUS_DISPOSED, self::STATUS_FOR_DISPOSAL], true)) {
                $property->inventory()->firstOrCreate([], ['available_quantity' => $property->quantity]);
            }
        });
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(Assignment::class)
            ->where('status', Assignment::STATUS_ACTIVE)
            ->orderBy('date_assigned')
            ->orderBy('id');
    }

    public function disposals(): HasMany
    {
        return $this->hasMany(Disposal::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(PropertyHistory::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(PropertyUnit::class);
    }

    public function availableUnits(): HasMany
    {
        return $this->hasMany(PropertyUnit::class)
            ->where('status', PropertyUnit::STATUS_AVAILABLE)
            ->orderBy('id');
    }

    public function getQrScanUrlAttribute(): string
    {
        return route('qr.scan', $this->qr_token);
    }

    public function getActiveAssignedQuantityAttribute(): int
    {
        if (array_key_exists('active_quantity_assigned', $this->attributes)) {
            return (int) $this->attributes['active_quantity_assigned'];
        }

        return (int) $this->assignments()
            ->where('status', Assignment::STATUS_ACTIVE)
            ->sum('quantity_assigned');
    }

    public function getDisposedQuantityAttribute(): int
    {
        if (array_key_exists('completed_quantity_disposed', $this->attributes)) {
            return (int) $this->attributes['completed_quantity_disposed'];
        }

        return (int) $this->disposals()
            ->whereIn('status', Disposal::finalizedStatuses())
            ->sum('quantity_disposed');
    }

    public function getAvailableQuantityAttribute(): int
    {
        return max(0, (int) $this->quantity);
    }

    public function getTrackedQuantityAttribute(): int
    {
        return max(1, (int) $this->quantity + $this->active_assigned_quantity + $this->disposed_quantity);
    }

    public function getTotalCostAttribute(): ?float
    {
        if ($this->unit_cost === null) {
            return null;
        }

        return round((float) $this->unit_cost * $this->tracked_quantity, 2);
    }

    public function getCategoryLabelAttribute(): ?string
    {
        return $this->propertyCategory?->category_name ?: $this->category;
    }

    public function getLocationLabelAttribute(): ?string
    {
        return $this->propertyLocation?->location_name ?: $this->location;
    }

    public function syncInventoryStatus(): void
    {
        $status = self::STATUS_AVAILABLE;

        if ($this->active_assigned_quantity > 0) {
            $status = self::STATUS_ASSIGNED;
        } elseif ((int) $this->quantity === 0 && $this->disposed_quantity > 0) {
            $status = self::STATUS_DISPOSED;
        }

        if ($this->status !== $status) {
            $this->forceFill(['status' => $status])->save();
        }
    }
}
