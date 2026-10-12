<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingHistory extends Model
{
    use HasFactory;

    protected $table = 'tracking_histories';

    protected $fillable = [
        'tracking_id',
        'tracking_status',
        'tracking_status_id',
    ];

    public function tracking(): BelongsTo
    {
        return $this->belongsTo(Tracking::class, 'tracking_id');
    }
}
