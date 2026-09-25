<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantPerformanceLog extends Model
{
    protected $table = 'merchant_performance_logs';

    protected $fillable = [
        'tenant_id',
        'metric_type',
        'impact_score',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'impact_score' => 'float',
        'metadata' => 'array',
    ];

    /**
     * Get the merchant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
