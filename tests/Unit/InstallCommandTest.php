<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Unit;

use Illuminate\Support\Facades\File;
use Usamamuneerchaudhary\CommandPalette\Commands\InstallCommand;

test('it finds panel providers nested under the Filament directory', function () {
    $directory = app_path('Filament/Admin');
    $path = $directory.'/AdminPanelProvider.php';

    File::ensureDirectoryExists($directory);
    File::put($path, "<?php\n\nnamespace App\\Filament\\Admin;\n\nclass AdminPanelProvider {}\n");

    $command = new class extends InstallCommand
    {
        /**
         * @return array<int, string>
         */
        public function foundProviders(): array
        {
            return $this->discoverProviders();
        }
    };

    try {
        $found = array_map(
            fn (string $file): string => realpath($file) ?: $file,
            $command->foundProviders(),
        );

        $this->assertContains(realpath($path), $found);
    } finally {
        File::delete($path);
        File::deleteDirectory($directory);

        if (is_dir(app_path('Filament')) && File::allFiles(app_path('Filament')) === []) {
            File::deleteDirectory(app_path('Filament'));
        }
    }
});

test('it fails when a panel provider has nowhere to register the plugin', function () {
    $path = storage_path('framework/no-injection-panel.php');

    File::ensureDirectoryExists(dirname($path));
    File::put($path, "<?php\n\nclass AdminPanelProvider {}\n");

    try {
        $this->artisan('command-palette:install', [
            '--panel' => [$path],
            '--no-interaction' => true,
        ])->assertFailed();
    } finally {
        File::delete($path);
    }
});
