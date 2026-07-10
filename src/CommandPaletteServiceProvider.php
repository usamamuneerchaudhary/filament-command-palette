<?php

namespace Usamamuneerchaudhary\CommandPalette;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Usamamuneerchaudhary\CommandPalette\Commands\CommandPalettePublishViewsCommand;

class CommandPaletteServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('command-palette')
            ->hasConfigFile()
            ->hasViews()
            ->hasCommand(CommandPalettePublishViewsCommand::class);
    }

    public function packageBooted(): void
    {
        if (! class_exists(FilamentAsset::class)) {
            return;
        }

        $cssPath = __DIR__.'/../resources/css/command-palette.css';

        FilamentAsset::register([
            Css::make('command-palette', $cssPath),
        ], 'usamamuneerchaudhary/filament-command-palette');

        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): HtmlString => new HtmlString(
                '<style>'.file_get_contents($cssPath).'</style>'
            ),
        );
    }
}
