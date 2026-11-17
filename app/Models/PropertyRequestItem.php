<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyRequestItem extends Model
{
    protected $fillable = [
        'property_request_id',
        'property_id',
        'inventory_id',
        'requested_item_name',
        'requested_quantity',
        'approved_property_id',
        'assignment_id',
    ];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'integer',
        ];
    }

    public function propertyRequest(): BelongsTo
    {
        return $this->belongsTo(PropertyRequestRecord::class, 'property_request_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function approvedProperty(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'approved_property_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }
}
