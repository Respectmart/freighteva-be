<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingSession extends Model
{
    use HasFactory;

    protected $table = 'booking_sessions';

    protected $fillable = [
        'booking_token',
        'customer_name',
        'customer_email',
        'customer_phone',
        'route_from',
        'route_to',
        'cargo_details',
        'base_price',
        'protection_fee',
        'total_price',
        'currency',
        'current_step',
        'is_kyc_verified',
        'status',
        'portal_user_id',
        'reminder_sent_at',
    ];

    protected $casts = [
        'cargo_details' => 'array',
        'base_price' => 'decimal:2',
        'protection_fee' => 'decimal:2',
        'total_price' => 'decimal:2',
        'is_kyc_verified' => 'boolean',
        'reminder_sent_at' => 'datetime',
    ];
}
