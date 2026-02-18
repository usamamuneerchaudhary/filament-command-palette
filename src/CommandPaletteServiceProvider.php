<?php

namespace Usamamuneerchaudhary\CommandPalette;

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
}
