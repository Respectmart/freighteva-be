<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'shipment_id' => $this->shipment_id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'reviewer_name' => $this->user ? trim($this->user->first_name . ' ' . $this->user->last_name) : 'Anonymous',
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
