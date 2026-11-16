<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KycVerification extends Model
{
    use HasFactory;

    protected $table = 'kyc_verifications';

    protected $fillable = [
        'email',
        'session_id',
        'status',
        'decision_payload',
        'verified_at',
    ];

    protected $casts = [
        'decision_payload' => 'array',
        'verified_at' => 'datetime',
    ];
}
