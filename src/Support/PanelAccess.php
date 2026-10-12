<?php

namespace Usamamuneerchaudhary\CommandPalette\Support;

use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class PanelAccess
{
    public static function check(Panel $panel, mixed $user = null): bool
    {
        if ($user === null) {
            try {
                $user = Filament::auth()->user();
            } catch (\Throwable) {
                return false;
            }
        }

        if ($user === null) {
            return false;
        }

        if (! $user instanceof FilamentUser) {
            return true;
        }

        try {
            return $user->canAccessPanel($panel);
        } catch (\Throwable) {
            return false;
        }
    }
}
