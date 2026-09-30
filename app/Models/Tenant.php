<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'name',
    'company_name',
    'company_slug',
    'subdomain',
    'custom_domain',
    'status',
    'rating_avg',
    'rating_count',
    'sub_type',
    'active',
    'latitude',
    'longitude',
    'country_code',
    'zone_code',
    'metro_area',
    'service_radius_km',
    'is_verified',
    'rating_score',
    'booking_success_rate',
])]
class Tenant extends Model
{
    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_verified' => 'boolean',
        'active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'rating_avg' => 'float',
        'rating_count' => 'integer',
    ];

    /**
     * Get the users for the tenant.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the shipping capabilities for the merchant tenant.
     */
    public function capabilities(): HasMany
    {
        return $this->hasMany(MerchantShippingCapability::class, 'tenant_id');
    }

    /**
     * Get the official Freighteva endorsement record for the merchant (1-to-1).
     */
    public function endorsement(): HasOne
    {
        return $this->hasOne(MerchantEndorsement::class, 'tenant_id');
    }

    /**
     * Scope query to only include actively endorsed merchants.
     */
    public function scopeEndorsed($query)
    {
        return $query->whereHas('endorsement', function ($q) {
            $q->where('is_endorsed', true)
              ->where('status', 'ENDORSED')
              ->where(function ($sub) {
                  $sub->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
              });
        })->where(function ($q) {
            $q->where('status', 'active')
              ->orWhere('active', true);
        });
    }

    /**
     * Check if the tenant currently holds an active endorsement.
     */
    public function isEndorsed(): bool
    {
        if (!$this->endorsement) {
            return false;
        }

        if (!$this->endorsement->isValid()) {
            return false;
        }

        return $this->status === 'active' || $this->active;
    }
}
