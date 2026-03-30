<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado cuando una empresa cambia su theme.
 */
class ThemeChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $tenantUuid,
        public ?int $oldThemeId,
        public ?int $newThemeId,
        public string $newThemePath,
    ) {}
}
