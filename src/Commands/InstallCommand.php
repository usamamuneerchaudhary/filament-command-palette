<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

use Symfony\Component\Finder\Finder;
use Usamamuneerchaudhary\CommandPalette\Support\Install\PluginRegistrar;

class InstallCommand extends Command
{
    protected $signature = 'command-palette:install
        {--panel=* : Panel provider file paths to update}
        {--shortcut=mod+k : Default key binding}
        {--publish : Publish the config and translation files}';

    protected $description = 'Register the command palette on one or more Filament panels';

    /**
     * Registers the plugin on the selected panel providers.
     */
    public function handle(PluginRegistrar $registrar): int
    {
        $providers = $this->selectedProviders();

        if ($providers === []) {
            $this->error('No Filament panel providers were found.');

            return self::FAILURE;
        }

        $shortcut = $this->chosenShortcut();
        $updated = 0;
        $failed = 0;

        foreach ($providers as $path) {
            if (! is_file($path)) {
                $this->warn("Skipped missing file [{$path}].");
                $failed++;

                continue;
            }

            $contents = (string) file_get_contents($path);
            $result = $registrar->inject($contents, $shortcut);

            if ($result['reason'] === 'already-registered') {
                $this->line("Already registered in [{$path}].");

                continue;
            }

            if (! $result['changed']) {
                $this->warn("Could not find an injection point in [{$path}].");
                $failed++;

                continue;
            }

            file_put_contents($path, $result['contents']);
            $this->info("Registered the plugin in [{$path}].");
            $updated++;
        }

        if ($this->shouldPublish()) {
            $this->call('vendor:publish', ['--tag' => 'command-palette-config']);
            $this->call('vendor:publish', ['--tag' => 'command-palette-translations']);
        }

        if ($updated === 0) {
            $this->comment('No panel providers were modified.');
        }

        if ($updated === 0 && $failed > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Uses an explicit panel list, otherwise the providers found on disk.
     *
     * @return array<int, string>
     */
    protected function selectedProviders(): array
    {
        $explicit = array_values(array_filter($this->option('panel') ?: []));

        if ($explicit !== []) {
            return $explicit;
        }

        $discovered = $this->discoverProviders();

        if ($this->option('no-interaction') || $discovered === []) {
            return $discovered;
        }

        $choices = [];

        foreach ($discovered as $path) {
            $choices[$path] = Str::after($path, base_path().DIRECTORY_SEPARATOR);
        }

        /** @var array<int, string> $selected */
        $selected = multiselect(
            label: 'Which panels should receive the command palette?',
            options: $choices,
            default: array_keys($choices),
            required: true,
        );

        return $selected;
    }

    /**
     * Reads the shortcut option, or asks for one outside of non-interactive runs.
     */
    protected function chosenShortcut(): string
    {
        $shortcut = trim((string) $this->option('shortcut'));

        if ($this->option('no-interaction')) {
            return $this->acceptedShortcut($shortcut);
        }

        return $this->acceptedShortcut(text(
            label: 'Default keyboard shortcut',
            default: $shortcut !== '' ? $shortcut : 'mod+k',
            hint: 'Use mod for Cmd on macOS and Ctrl elsewhere. Pick a different binding if Filament global search already uses this one.',
        ));
    }

    /**
     * Keeps key bindings that can be written into a panel provider as plain text.
     */
    protected function acceptedShortcut(string $shortcut): string
    {
        if (PluginRegistrar::isSafeShortcut($shortcut)) {
            return $shortcut;
        }

        $this->warn('That shortcut is not a key binding. Using mod+k.');

        return 'mod+k';
    }

    /**
     * Publishes config and translations only when asked, and stays quiet without a prompt.
     */
    protected function shouldPublish(): bool
    {
        if ($this->option('publish')) {
            return true;
        }

        if ($this->option('no-interaction')) {
            return false;
        }

        return confirm('Publish the configuration and translation files?', default: false);
    }

    /**
     * Finds panel provider files, including providers nested under app/Filament.
     *
     * @return array<int, string>
     */
    protected function discoverProviders(): array
    {
        $directories = array_values(array_filter([
            app_path('Providers/Filament'),
            app_path('Filament'),
        ], 'is_dir'));

        if ($directories === []) {
            return [];
        }

        $files = [];

        foreach ((new Finder)->files()->in($directories)->name('*PanelProvider.php') as $file) {
            $files[] = $file->getRealPath() ?: $file->getPathname();
        }

        sort($files);

        return array_values(array_unique($files));
    }
}
