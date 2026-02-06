<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant\ConfigurationPixel;

class ConfigurationPixelController extends Controller
{
    public function index()
    {
        return response()->json([
            'pixels' => ConfigurationPixel::orderBy('id')->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'pixels'              => 'required|array',
            'pixels.*.id'         => 'nullable|integer|exists:configuration_pixels,id',
            'pixels.*.title'      => 'required|string|max:255',
            'pixels.*.script'     => 'required|string',
            'pixels.*.position'   => 'required|in:head,body',
            'pixels.*.active'     => 'boolean',
            'deleted_ids'         => 'nullable|array',
            'deleted_ids.*'       => 'integer|exists:configuration_pixels,id',
        ]);

        return DB::transaction(function () use ($request) {

            if (!empty($request->deleted_ids)) {
                ConfigurationPixel::whereIn('id', $request->deleted_ids)->delete();
            }

            foreach ($request->pixels as $pixel) {

                $data = [
                    'title'    => $pixel['title'],
                    'script'   => $pixel['script'],

                    'position' => $pixel['position'],
                    'active'   => $pixel['active'] ?? true,
                ];

                if (!empty($pixel['id'])) {
                    ConfigurationPixel::where('id', $pixel['id'])->update($data);
                } else {
                    ConfigurationPixel::create($data);
                }
            }

            return response()->json([
                'success' => true,
                'pixels'  => ConfigurationPixel::orderBy('id')->get(),
            ]);
        });
    }
}
