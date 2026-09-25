<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantBookingException extends Model
{
    protected $table = 'merchant_booking_exceptions';

    protected $fillable = [
        'shipment_id',
        'original_tenant_id',
        'reassigned_tenant_id',
        'reason_code',
        'reason_notes',
        'penalty_applied',
        'penalty_points',
        'resolution_status',
        'resolved_at',
        'metadata',
    ];

    protected $casts = [
        'penalty_applied' => 'boolean',
        'penalty_points' => 'float',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the associated shipment.
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    /**
     * Get the merchant who rejected the booking.
     */
    public function originalTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'original_tenant_id');
    }

    /**
     * Get the newly assigned partner (if reassigned).
     */
    public function reassignedTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'reassigned_tenant_id');
    }
}
