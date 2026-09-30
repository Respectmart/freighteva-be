<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'tenant_id',
    'is_endorsed',
    'status',
    'tier',
    'endorsed_at',
    'expires_at',
    'endorsed_by',
    'notes',
])]
class MerchantEndorsement extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'merchant_endorsements';

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_endorsed' => 'boolean',
        'tier' => 'integer',
        'endorsed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the endorsement record.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Helper to check if endorsement is currently valid.
     */
    public function isValid(): bool
    {
        if (!$this->is_endorsed || $this->status !== 'ENDORSED') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
