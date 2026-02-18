<?php

namespace Usamamuneerchaudhary\CommandPalette;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CommandPaletteServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('command-palette')
            ->hasConfigFile()
            ->hasViews();
    }
}
