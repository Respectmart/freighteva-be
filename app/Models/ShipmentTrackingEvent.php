<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentTrackingEvent extends Model
{
    protected $table = 'shipment_tracking_events';

    protected $fillable = [
        'shipment_id',
        'status',
        'title',
        'description',
        'location',
        'event_time',
        'actor_type',
        'actor_id',
        'metadata',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the parent shipment.
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    /**
     * Scope to order events chronologically.
     */
    public function scopeChronological($query)
    {
        return $query->orderBy('event_time', 'asc')->orderBy('id', 'asc');
    }
}
