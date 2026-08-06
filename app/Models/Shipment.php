<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'origin',
    'destination',
    'weight',
    'carrier',
    'mode',
    'status',
    'tenant_id',
    'user_id',
    'invoice_prefix',
    'invoice_no',
    'awb_number',
    'shipment_receiver_id',
    'shipment_sender_id',
    'freight_id',
    'amount',
    'total_amount',
    'currency',
    'handling_fee'
])]
class Shipment extends Model
{
    /**
     * Get the tenant that owns the shipment.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user that booked the shipment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
