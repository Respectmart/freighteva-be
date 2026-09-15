<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    'booking_success_rate'
])]
class Tenant extends Model
{
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
}
