<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\Theme;
use Illuminate\Http\Request;

/**
 * ThemeController — CRUD de themes para el Super Admin.
 */
class ThemeController extends Controller
{
    /**
     * Vista principal de gestión de themes.
     */
    public function index()
    {
        return view('system.themes.index');
    }

    /**
     * Listar todos los themes (API JSON).
     */
    public function records()
    {
        $themes = Theme::orderBy('sort_order')->get()->map(function ($theme) {
            return [
                'id'            => $theme->id,
                'name'          => $theme->name,
                'slug'          => $theme->slug,
                'path'          => $theme->path,
                'css_template'  => $theme->css_template,
                'description'   => $theme->description,
                'preview_image' => $theme->preview_image,
                'category'      => $theme->category,
                'is_active'     => $theme->is_active,
                'is_premium'    => $theme->is_premium,
                'sort_order'    => $theme->sort_order,
                'folder_exists' => $theme->folderExists(),
                'css_exists'    => $theme->cssExists(),
            ];
        });

        return response()->json(['data' => $themes]);
    }

    /**
     * Crear o actualizar un theme.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:60',
            'slug'         => 'required|string|max:60|unique:themes,slug,' . ($request->id ?? 'NULL'),
            'path'         => 'required|string|max:100',
            'css_template' => 'nullable|string|max:30',
            'description'  => 'nullable|string|max:500',
            'category'     => 'required|in:general,nicho',
            'is_active'    => 'boolean',
            'is_premium'   => 'boolean',
        ]);

        $theme = Theme::updateOrCreate(
            ['id' => $request->id],
            $request->only([
                'name', 'slug', 'path', 'css_template',
                'description', 'category', 'is_active', 'is_premium', 'sort_order',
            ])
        );

        return response()->json([
            'success' => true,
            'message' => $request->id ? 'Theme actualizado' : 'Theme creado',
            'data'    => $theme,
        ]);
    }

    /**
     * Activar/desactivar un theme.
     */
    public function toggleStatus($id)
    {
        $theme = Theme::findOrFail($id);

        // No permitir desactivar el default
        if ($theme->slug === 'default') {
            return response()->json([
                'success' => false,
                'message' => 'El theme default no puede ser desactivado.',
            ], 422);
        }

        $theme->is_active = !$theme->is_active;
        $theme->save();

        return response()->json([
            'success' => true,
            'message' => $theme->is_active ? 'Theme activado' : 'Theme desactivado',
        ]);
    }

    /**
     * Eliminar un theme.
     */
    public function destroy($id)
    {
        $theme = Theme::findOrFail($id);

        if ($theme->slug === 'default') {
            return response()->json([
                'success' => false,
                'message' => 'El theme default no puede ser eliminado.',
            ], 422);
        }

        $theme->delete();

        return response()->json([
            'success' => true,
            'message' => 'Theme eliminado',
        ]);
    }

    /**
     * Instalar un theme para un client específico.
     */
    public function installForClient(Request $request, $themeId)
    {
        $request->validate(['client_id' => 'required|exists:clients,id']);

        $theme  = Theme::findOrFail($themeId);
        $client = \App\Models\System\Client::findOrFail($request->client_id);

        if (!$client->hostname_id) {
            return response()->json(['success' => false, 'message' => 'Client sin hostname'], 422);
        }

        $installation = \App\Models\System\ThemeInstallation::updateOrCreate(
            ['theme_id' => $theme->id, 'hostname_id' => $client->hostname_id],
            ['version' => $theme->version ?? '1.0.0', 'status' => 'active', 'installed_at' => now()]
        );

        $installation->activate();

        // Actualizar theme del client
        $client->update(['theme_id' => $theme->id]);

        return response()->json([
            'success' => true,
            'message' => "Theme '{$theme->name}' instalado para '{$client->name}'",
        ]);
    }

    /**
     * Themes activos disponibles para selección por empresas (API pública para tenants).
     */
    public function available()
    {
        $themes = Theme::availableForTenants()
            ->get(['id', 'name', 'slug', 'path', 'css_template', 'description', 'preview_image', 'category', 'is_premium']);

        return response()->json(['data' => $themes]);
    }
}
