<?php

namespace Usamamuneerchaudhary\CommandPalette\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Usamamuneerchaudhary\CommandPalette\CommandPaletteServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            CommandPaletteServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('command-palette.custom_commands', []);
        config()->set('command-palette.max_results', 10);
    }
}
