<?php

namespace App\Http\Controllers\Tenant\Logistic;

use App\Http\Controllers\Controller;
use App\Models\Tenant\CourierCompany;
use App\Models\Tenant\LogisticOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CourierCompanyController extends Controller
{
    public function list()
    {
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);
        return response()->json(
            CourierCompany::active()->pluck('name')
        );
    }

    public function index()
    {
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);
        $couriers = CourierCompany::orderBy('sort_order')->orderBy('name')->get();
        return view('tenant.logistic.courier_companies', compact('couriers'));
    }

    public function store(Request $request): RedirectResponse
    {
        \App\Helpers\AuthorizationHelper::authorize('logistics.manage_couriers');
        $request->validate(['name' => 'required|string|max:120|unique:tenant.courier_companies,name']);

        CourierCompany::create([
            'name'       => $request->name,
            'is_active'  => true,
            'sort_order' => CourierCompany::max('sort_order') + 1,
        ]);

        return back()->with('success', "Courier \"{$request->name}\" agregado.");
    }

    public function update(Request $request, CourierCompany $courier): RedirectResponse
    {
        \App\Helpers\AuthorizationHelper::authorize('logistics.manage_couriers');

        $allowedDrivers = ['manual', 'chazki', 'nueveminutos'];

        $request->validate([
            'name'         => "required|string|max:120|unique:tenant.courier_companies,name,{$courier->id}",
            'is_active'    => 'boolean',
            'api_driver'   => 'nullable|string|in:' . implode(',', $allowedDrivers),
            'api_key'      => 'nullable|string|max:300',
            'api_secret'   => 'nullable|string|max:300',
            'api_endpoint' => 'nullable|url|max:300',
            'api_sandbox'  => 'boolean',
        ]);

        $data = [
            'name'         => $request->name,
            'is_active'    => $request->boolean('is_active'),
            'api_driver'   => $request->input('api_driver', 'manual') ?: 'manual',
            'api_endpoint' => $request->input('api_endpoint'),
            'api_sandbox'  => $request->boolean('api_sandbox'),
        ];

        // Only overwrite secrets if provided (avoid blanking stored credentials)
        if ($request->filled('api_key'))    $data['api_key']    = $request->api_key;
        if ($request->filled('api_secret')) $data['api_secret'] = $request->api_secret;

        $courier->update($data);

        return back()->with('success', 'Courier actualizado.');
    }

    public function destroy(CourierCompany $courier): RedirectResponse
    {
        \App\Helpers\AuthorizationHelper::authorize('logistics.manage_couriers');
        $courier->delete();
        return back()->with('success', "Courier \"{$courier->name}\" eliminado.");
    }
}
