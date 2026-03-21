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
        abort_unless(in_array(auth()->user()->type ?? '', ['admin', 'superadmin']), 403);
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
        abort_unless(in_array(auth()->user()->type ?? '', ['admin', 'superadmin']), 403);
        $request->validate([
            'name'      => "required|string|max:120|unique:tenant.courier_companies,name,{$courier->id}",
            'is_active' => 'boolean',
        ]);

        $courier->update([
            'name'      => $request->name,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Courier actualizado.');
    }

    public function destroy(CourierCompany $courier): RedirectResponse
    {
        abort_unless(in_array(auth()->user()->type ?? '', ['admin', 'superadmin']), 403);
        $courier->delete();
        return back()->with('success', "Courier \"{$courier->name}\" eliminado.");
    }
}
