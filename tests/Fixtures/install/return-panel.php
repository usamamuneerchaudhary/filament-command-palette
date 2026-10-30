<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('app')
            ->path('app');
    }
}
