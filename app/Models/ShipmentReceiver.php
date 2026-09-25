<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Unguarded;

#[Unguarded]
class ShipmentReceiver extends Model
{
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'shipment_receiver_id');
    }
}
