<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Usamamuneerchaudhary\CommandPalette\FilamentCommandPalettePlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->plugin(FilamentCommandPalettePlugin::make());
    }
}
