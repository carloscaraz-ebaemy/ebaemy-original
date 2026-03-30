<?php

namespace Tests\Unit;

use App\Services\ThemeManager;
use Tests\TestCase;

class ThemeManagerTest extends TestCase
{
    protected ThemeManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new ThemeManager();
    }

    public function test_default_theme_when_not_booted(): void
    {
        $this->assertEquals('default', $this->manager->getActiveTheme());
    }

    public function test_is_default_returns_true_initially(): void
    {
        $this->assertTrue($this->manager->isDefault());
    }

    public function test_set_theme_with_valid_folder(): void
    {
        // 'ropa' folder exists
        $this->manager->setTheme('ropa');
        $this->assertEquals('ropa', $this->manager->getActiveTheme());
        $this->assertFalse($this->manager->isDefault());
    }

    public function test_set_theme_falls_back_on_invalid_folder(): void
    {
        $this->manager->setTheme('nonexistent_theme_xyz');
        $this->assertEquals('default', $this->manager->getActiveTheme());
    }

    public function test_path_exists_for_existing_theme(): void
    {
        $this->assertTrue($this->manager->pathExists('ropa'));
        $this->assertTrue($this->manager->pathExists('default'));
    }

    public function test_path_exists_returns_false_for_missing(): void
    {
        $this->assertFalse($this->manager->pathExists('nonexistent_xyz'));
    }

    public function test_get_view_paths_returns_default_when_default(): void
    {
        $paths = $this->manager->getViewPaths();
        $this->assertCount(1, $paths);
        $this->assertStringContainsString('default', $paths[0]);
    }

    public function test_get_view_paths_returns_active_then_default(): void
    {
        $this->manager->setTheme('ropa');
        $paths = $this->manager->getViewPaths();
        $this->assertCount(2, $paths);
        $this->assertStringContainsString('ropa', $paths[0]);
        $this->assertStringContainsString('default', $paths[1]);
    }

    public function test_available_themes_includes_default(): void
    {
        $themes = $this->manager->getAvailableThemes();
        $this->assertArrayHasKey('default', $themes);
        $this->assertTrue($themes['default']['is_default']);
    }

    public function test_template_map_has_expected_entries(): void
    {
        $map = ThemeManager::getTemplateMap();
        $this->assertEquals('default', $map['generic']);
        $this->assertEquals('ropa', $map['fashion']);
        $this->assertEquals('tecnologia', $map['tech']);
    }

    public function test_register_template_adds_mapping(): void
    {
        ThemeManager::registerTemplate('pets', 'mascotas');
        $map = ThemeManager::getTemplateMap();
        $this->assertEquals('mascotas', $map['pets']);
    }

    public function test_setting_returns_default_when_no_config(): void
    {
        $val = $this->manager->setting('nonexistent_key', 'fallback');
        $this->assertEquals('fallback', $val);
    }

    public function test_asset_returns_base_asset_as_fallback(): void
    {
        $url = $this->manager->asset('css/nonexistent.css');
        $this->assertStringContainsString('css/nonexistent.css', $url);
    }
}
