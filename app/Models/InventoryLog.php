<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLog extends Model
{
    use HasFactory;

    public const ACTION_ADDED = 'stock_added';

    public const ACTION_RESERVED = 'reserved';

    public const ACTION_RELEASED = 'released';

    public const ACTION_FULFILLED = 'fulfilled';

    protected $fillable = [
        'inventory_id', 'property_id', 'property_request_id', 'user_id',
        'action', 'quantity_change', 'available_after', 'notes',
    ];

    protected function casts(): array
    {
        return ['quantity_change' => 'integer', 'available_after' => 'integer'];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function propertyRequest(): BelongsTo
    {
        return $this->belongsTo(PropertyRequestRecord::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function actions(): array
    {
        return [self::ACTION_ADDED, self::ACTION_RESERVED, self::ACTION_RELEASED, self::ACTION_FULFILLED];
    }
}
