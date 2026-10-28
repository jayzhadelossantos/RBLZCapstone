<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = ['property_id', 'available_quantity'];

    protected function casts(): array
    {
        return ['available_quantity' => 'integer'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
