<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;
use Modules\Finance\Models\Income;
use Modules\Pos\Http\Controllers\CashController;

class CashCollection extends ResourceCollection
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
            $id_income=$row->user_id;
            $incomes=Income::where('user_id', $id_income)->whereTypeUser();

            $income_total=0;
            if($row->date_closed){
                $incomes=$incomes->whereBetween('date_of_issue',[$row->date_opening,$row->date_closed]);
                $incomes=$incomes->whereBetween('time_of_issue',[$row->time_opening,$row->time_closed]);
                $incomes=$incomes->get();
        
                if (isset($incomes[0])) {
                    foreach ($incomes as $income) {
                        $income_total += app(CashController::class)->getIncomeEgressCashDestination($income->payments);

                    }
                }
            }
            

            return [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'user' => $row->user->name,
                'date_opening' => $row->date_opening,
                'time_opening' => $row->time_opening,
                'opening' => "{$row->date_opening} {$row->time_opening}",
                'date_closed' => $row->date_closed,
                'time_closed' => $row->time_closed, 
                'closed' => "{$row->date_closed} {$row->time_closed}",
                'beginning_balance' => $row->beginning_balance,
                'final_balance' => $row->final_balance + $income_total,
                'income' => $row->income,
                'expense' => $row->expense,
                'filename' => $row->filename,
                'state' => (bool) $row->state, 
                'state_description' => ($row->state) ? 'Aperturada':'Cerrada',
                'reference_number' => $row->reference_number,

            ];
        });
    }
}