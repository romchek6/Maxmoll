<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'customer' => $this->customer,
            'created_at' => $this->created_at,
            'completed_at' => $this->created_at,
            'warehouse_id' => $this->warehouse_id,
            'status' => $this->status,
            'items' => OrderItemsResource::collection($this->whenLoaded('orderItems'))
        ];
    }
}
