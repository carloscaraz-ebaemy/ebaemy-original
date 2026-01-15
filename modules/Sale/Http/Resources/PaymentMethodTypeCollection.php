<?php

namespace Modules\Sale\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PaymentMethodTypeCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function toArray($request)
    {

        return $this->collection->transform(function($row, $key) {

            /** @var \App\Models\Tenant\PaymentMethodType  $row */
            $show_actions = true;

            $default_payment_method_type_ids = [
                '01', '02', '03', '04', '05', '06', '07', '08', '09', '10',
            ];

            $can_delete = !in_array((string) $row->id, $default_payment_method_type_ids, true);

            if(in_array($row->id, ['01', '05', '08', '09', '04'])){
                $show_actions = false;
            }
            $return = $row->toArray();
            $return['show_actions'] = $show_actions;
            $return['can_delete'] = $can_delete;
            return $return;

            return [
                'id' => $row->id,
                'description' => $row->description,
                'show_actions' => $show_actions
            ];
        });
    }
}
