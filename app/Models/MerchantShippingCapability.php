<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantShippingCapability extends Model
{
    use HasFactory;

    protected $table = 'merchant_shipping_capabilities';

    protected $fillable = [
        'tenant_id',
        'origin_country_code',
        'destination_country_code',
        'destination_city',
        'freight_mode',
        'min_weight_kg',
        'max_weight_kg',
        'pickup_available',
        'dropoff_available',
        'base_rate_per_kg',
        'min_transit_days',
        'max_transit_days',
        'corridor_specialization_tier',
        'is_active',
    ];

    protected $casts = [
        'min_weight_kg' => 'decimal:2',
        'max_weight_kg' => 'decimal:2',
        'pickup_available' => 'boolean',
        'dropoff_available' => 'boolean',
        'base_rate_per_kg' => 'decimal:2',
        'min_transit_days' => 'integer',
        'max_transit_days' => 'integer',
        'corridor_specialization_tier' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * The merchant tenant that owns this shipping capability.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Scope for active routes matching destination and freight mode.
     */
    public function scopeServingRoute($query, string $destinationCountry, string $freightMode)
    {
        $query->where('is_active', true)
            ->where(function ($q) use ($destinationCountry) {
                $q->where('destination_country_code', strtoupper($destinationCountry))
                  ->orWhere('destination_country_code', '*');
            });

        if (in_array(strtolower($freightMode), ['air', 'ocean'])) {
            $query->where(function ($q) use ($freightMode) {
                $q->where('freight_mode', strtolower($freightMode))
                  ->orWhere('freight_mode', 'both');
            });
        }

        return $query;
    }
}
