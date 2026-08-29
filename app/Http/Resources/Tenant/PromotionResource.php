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
            // Slider administrable (fase 3)
            'image_mobile'     => $this->image_mobile,
            'image_mobile_url' => $this->image_mobile_url,
            'title'            => $this->title,
            'subtitle'         => $this->subtitle,
            'button_text'      => $this->button_text,
            'link_type'        => $this->link_type,
            'link_category_id' => $this->link_category_id,
            'sort_order'       => (int) $this->sort_order,
            // El form usa <el-date-picker> con datetime: formato plano.
            'starts_at'        => optional($this->starts_at)->format('Y-m-d H:i:s'),
            'ends_at'          => optional($this->ends_at)->format('Y-m-d H:i:s'),
        ];
    }
}