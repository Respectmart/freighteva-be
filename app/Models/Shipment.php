<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'origin',
    'destination',
    'weight',
    'carrier',
    'mode',
    'status',
    'payment_status',
    'date_paid',
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
    'insurance_value',
    'handling_fee',
    'pickup_date',
    'pickup_time_slot',
    'pickup_type',
    'freight_mode',
])]
class Shipment extends Model
{
    /**
     * Get the tenant that fulfills the shipment.
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

    public function shipmentSender(): BelongsTo
    {
        return $this->belongsTo(ShipmentSender::class, 'shipment_sender_id');
    }

    public function shipmentReceiver(): BelongsTo
    {
        return $this->belongsTo(ShipmentReceiver::class, 'shipment_receiver_id');
    }

    /**
     * Get the chronological tracking events for this shipment.
     */
    public function trackingEvents(): HasMany
    {
        return $this->hasMany(ShipmentTrackingEvent::class, 'shipment_id')->orderBy('event_time', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Get exception records logged for this shipment.
     */
    public function exceptions(): HasMany
    {
        return $this->hasMany(MerchantBookingException::class, 'shipment_id');
    }

    /**
     * Helper to log a tracking event.
     */
    public function recordTrackingEvent(
        string $status,
        string $title,
        ?string $description = null,
        ?string $location = null,
        string $actorType = 'system',
        ?int $actorId = null,
        array $metadata = []
    ): ShipmentTrackingEvent {
        return $this->trackingEvents()->create([
            'status' => $status,
            'title' => $title,
            'description' => $description,
            'location' => $location,
            'event_time' => now(),
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'metadata' => $metadata,
        ]);
    }
}
