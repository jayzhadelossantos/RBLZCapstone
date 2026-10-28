<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyRequestRecord extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_REVIEWED = 'awaiting_admin';

    public const STATUS_AWAITING_STOCK = 'awaiting_stock';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FULFILLED = 'fulfilled';

    public const TYPE_NEW = 'new';

    public const TYPE_REPLACEMENT = 'replacement';

    protected $table = 'property_requests';

    protected $fillable = [
        'requested_by',
        'request_type',
        'processed_by',
        'requested_item_name',
        'requested_quantity',
        'needed_by',
        'purpose',
        'additional_notes',
        'issue_description',
        'status',
        'response_notes',
        'processed_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'assignment_id',
        'selected_property_id',
        'inventory_id',
        'replacement_assignment_id',
    ];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'integer',
            'needed_by' => 'date',
            'processed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_REVIEWED,
            self::STATUS_AWAITING_STOCK,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_FULFILLED,
        ];
    }

    public static function requestTypes(): array
    {
        return [self::TYPE_NEW, self::TYPE_REPLACEMENT];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function replacementAssignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class, 'replacement_assignment_id');
    }

    public function selectedProperty(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'selected_property_id');
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Awaiting staff review',
            self::STATUS_REVIEWED => 'Awaiting admin approval',
            self::STATUS_AWAITING_STOCK => 'Awaiting stock',
            self::STATUS_APPROVED => 'Approved - awaiting staff assignment',
            self::STATUS_FULFILLED => 'Assigned - receipt ready',
            default => ucfirst($this->status),
        };
    }

    public function getRequestTypeLabelAttribute(): string
    {
        return $this->request_type === self::TYPE_REPLACEMENT
            ? 'Replacement request'
            : 'New property request';
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
