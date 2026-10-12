<?php

namespace App\Observers;

use App\Models\Tracking;
use App\Models\TrackingStatus;

class TrackingObserver
{
    /**
     * Handle the Tracking "created" event.
     */
    public function created(Tracking $tracking): void
    {
        $statusName = $tracking->trackingStatus?->name 
            ?: (TrackingStatus::find($tracking->tracking_status_id)?->name ?? 'Shipment Booked and Manifested');

        $tracking->trackingHistory()->create([
            'tracking_status' => $statusName,
        ]);
    }

    /**
     * Handle the Tracking "updated" event.
     */
    public function updated(Tracking $tracking): void
    {
        if ($tracking->wasChanged('tracking_status_id')) {
            $status = TrackingStatus::find($tracking->tracking_status_id);
            $statusName = $status ? $status->name : ('Status #' . $tracking->tracking_status_id);

            $tracking->trackingHistory()->create([
                'tracking_status' => $statusName,
            ]);
        }
    }
}
