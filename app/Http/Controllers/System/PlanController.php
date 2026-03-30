<?php

namespace App\Http\Controllers\System;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\System\Feature;
use App\Models\System\Plan;
use App\Models\System\PlanDocument;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\System\PlanCollection;
use App\Http\Resources\System\PlanResource;
use App\Http\Requests\System\PlanRequest;

class PlanController extends Controller
{
    public function index()
    {
        return view('system.plans.index');
    }

    
    public function records()
    {
        $records = Plan::with('features')->orderBy('pricing')->get()->map(function ($plan) {
            $data = $plan->toArray();
            $data['feature_list'] = $plan->features->map(fn($f) => [
                'key'      => $f->key,
                'name'     => $f->name,
                'category' => $f->category,
                'limit'    => $f->pivot->limit,
            ])->toArray();
            $data['feature_count'] = count($data['feature_list']);
            return $data;
        });

        return ['data' => $records];
    }

    public function record($id)
    {
        $record = new PlanResource(Plan::findOrFail($id));

        return $record;
    }

    public function tables()
    {
        $plan_documents = PlanDocument::all(); 

        return compact('plan_documents');
    }


    public function store(PlanRequest $request)
    {
        $id = $request->input('id');
        $plan = Plan::firstOrNew(['id' => $id]);
        $plan->fill($request->all());
        $plan->save();

        return [
            'success' => true,
            'message' => ($id)?'Plan editado con éxito':'Plan registrado con éxito'
        ];
    }

    public function destroy($id)
    {
        $plan = Plan::findOrFail($id);
        $plan->delete();

        return [
            'success' => true,
            'message' => 'Plan eliminado con éxito'
        ];
    }

    /**
     * Retorna todas las features con flag `enabled` y el `limit` actual del plan.
     */
    public function features(Plan $plan): array
    {
        $planFeatures = $plan->features()->get()->keyBy('key');

        $features = Feature::active()->orderBy('category')->orderBy('name')->get()
            ->map(function ($f) use ($planFeatures) {
                $pivot = $planFeatures->get($f->key);
                return [
                    'id'          => $f->id,
                    'key'         => $f->key,
                    'name'        => $f->name,
                    'description' => $f->description,
                    'category'    => $f->category,
                    'enabled'     => $pivot !== null,
                    'limit'       => $pivot?->pivot?->limit,
                ];
            });

        return ['data' => $features->toArray()];
    }

    /**
     * Sincroniza las features habilitadas para el plan.
     *
     * Body: { features: [ { id, enabled, limit } ] }
     */
    public function syncFeatures(Request $request, Plan $plan): array
    {
        $incoming = collect($request->input('features', []));

        $sync = [];
        foreach ($incoming as $item) {
            if (!empty($item['enabled'])) {
                $sync[$item['id']] = ['limit' => $item['limit'] ?? null];
            }
        }

        $plan->features()->sync($sync);

        return ['success' => true, 'message' => 'Features del plan actualizadas'];
    }

}
