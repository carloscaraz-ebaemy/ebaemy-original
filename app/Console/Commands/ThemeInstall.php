<?php

namespace App\Console\Commands;

use App\Models\System\Theme;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Instalar/registrar un theme nuevo en el sistema.
 *
 * Uso:
 *   php artisan theme:install mascotas --css=pets --category=nicho
 *   php artisan theme:install --list
 */
class ThemeInstall extends Command
{
    protected $signature = 'theme:install
                            {name? : Nombre del theme (ej: mascotas)}
                            {--css= : CSS template name}
                            {--category=nicho : general o nicho}
                            {--premium : Marcar como premium}
                            {--list : Listar themes instalados}
                            {--scaffold : Crear carpeta con archivos base}';

    protected $description = 'Instalar o registrar un theme en el sistema';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listThemes();
        }

        $name = $this->argument('name');
        if (!$name) {
            $this->error('Nombre del theme requerido. Usa --list para ver los existentes.');
            return 1;
        }

        $slug = Str::slug($name);

        // Verificar si ya existe
        if (Theme::where('slug', $slug)->exists()) {
            $this->warn("El theme '{$slug}' ya existe en la BD.");
            if (!$this->confirm('¿Quieres actualizar el registro existente?')) {
                return 0;
            }
        }

        // Crear/actualizar registro en BD
        $theme = Theme::updateOrCreate(
            ['slug' => $slug],
            [
                'name'         => Str::title($name),
                'path'         => $slug,
                'css_template' => $this->option('css') ?: null,
                'category'     => $this->option('category'),
                'is_active'    => true,
                'is_premium'   => $this->option('premium'),
            ]
        );

        $this->info("Theme '{$theme->name}' registrado (ID: {$theme->id})");

        // Scaffold: crear carpeta con archivos base
        if ($this->option('scaffold')) {
            $this->scaffold($slug);
        } elseif (!$theme->folderExists()) {
            $this->warn("La carpeta resources/views/themes/{$slug}/ no existe.");
            if ($this->confirm('¿Crear estructura base?')) {
                $this->scaffold($slug);
            }
        }

        return 0;
    }

    protected function listThemes(): int
    {
        $themes = Theme::orderBy('sort_order')->get();

        $this->table(
            ['ID', 'Nombre', 'Slug', 'CSS', 'Categoría', 'Activo', 'Premium', 'Carpeta'],
            $themes->map(fn($t) => [
                $t->id,
                $t->name,
                $t->slug,
                $t->css_template ?? 'generic',
                $t->category,
                $t->is_active ? 'Si' : 'No',
                $t->is_premium ? 'Si' : 'No',
                $t->folderExists() ? 'Existe' : 'NO',
            ])
        );

        return 0;
    }

    protected function scaffold(string $slug): void
    {
        $base = resource_path("views/themes/{$slug}");

        // Crear directorios
        $dirs = ['', 'items', 'layouts/partials_ecommerce', 'cart', 'partials'];
        foreach ($dirs as $dir) {
            $path = $base . ($dir ? "/{$dir}" : '');
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }

        // Crear theme.json con settings por defecto
        $configFile = $base . '/theme.json';
        if (!file_exists($configFile)) {
            file_put_contents($configFile, json_encode([
                'name'        => Str::title($slug),
                'version'     => '1.0.0',
                'description' => "Theme {$slug}",
                'settings'    => [
                    'font_heading' => 'inherit',
                    'font_body'    => 'inherit',
                    'card_style'   => 'default',
                    'image_ratio'  => '1:1',
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // Crear .gitkeep
        file_put_contents($base . '/.gitkeep', '');

        $this->info("Scaffold creado en: resources/views/themes/{$slug}/");
        $this->line("  Archivos: theme.json, .gitkeep");
        $this->line("  Carpetas: items/, layouts/partials_ecommerce/, cart/, partials/");
    }
}
