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
    'status',
    'rating_avg',
    'rating_count',
    'sub_type',
    'active'
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
}
