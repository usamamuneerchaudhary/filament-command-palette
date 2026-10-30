<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Usamamuneerchaudhary\CommandPalette\Commands\CommandPalettePublishViewsCommand;
use Usamamuneerchaudhary\CommandPalette\Commands\InstallCommand;
use Usamamuneerchaudhary\CommandPalette\Support\CommandRegistry;

class CommandPaletteServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('command-palette')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasCommands([
                CommandPalettePublishViewsCommand::class,
                InstallCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(CommandRegistry::class);
    }

    public function packageBooted(): void
    {
        if (! class_exists(FilamentAsset::class)) {
            return;
        }

        $cssPath = __DIR__.'/../resources/css/command-palette.css';

        if (! is_file($cssPath)) {
            return;
        }

        FilamentAsset::register([
            Css::make('command-palette', $cssPath)->loadedOnRequest(),
        ], 'usamamuneerchaudhary/filament-command-palette');
    }
}
