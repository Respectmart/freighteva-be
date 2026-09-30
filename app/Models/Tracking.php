<?php

namespace App\Models;

use App\Observers\TrackingObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(TrackingObserver::class)]
class Tracking extends Model
{
    use HasFactory;

    protected $table = 'trackings';

    protected $fillable = [
        'purchase_request_id',
        'shipment_id',
        'tracking_number',
        'tracking_status_id',
        'tenant_id',
        'external_tracking_status',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function trackingStatus(): BelongsTo
    {
        return $this->belongsTo(TrackingStatus::class, 'tracking_status_id');
    }

    public function trackingHistory(): HasMany
    {
        return $this->hasMany(TrackingHistory::class, 'tracking_id');
    }
}
