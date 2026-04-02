<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'name' => $this->name,
            'status' => $this->status,
            'type'=> $this->type,
            'image_url' => $this->image_url,
            'item_id' => $this->item_id,
            'spot_url' => $this->spot_url,
            'banner_url' => $this->banner_url,
        ];
    }
}