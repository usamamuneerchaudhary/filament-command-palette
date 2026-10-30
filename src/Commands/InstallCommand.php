<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

use Usamamuneerchaudhary\CommandPalette\Support\Install\PluginRegistrar;

class InstallCommand extends Command
{
    protected $signature = 'command-palette:install
        {--panel=* : Panel provider file paths to update}
        {--shortcut=mod+k : Default key binding}
        {--publish : Publish the config and translation files}';

    protected $description = 'Register the command palette on one or more Filament panels';

    public function handle(PluginRegistrar $registrar): int
    {
        $providers = $this->selectedProviders();

        if ($providers === []) {
            $this->error('No Filament panel providers were found.');

            return self::FAILURE;
        }

        $shortcut = $this->chosenShortcut();
        $updated = 0;

        foreach ($providers as $path) {
            if (! is_file($path)) {
                $this->warn("Skipped missing file [{$path}].");

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

        return self::SUCCESS;
    }

    /**
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

    protected function chosenShortcut(): string
    {
        $shortcut = (string) $this->option('shortcut');

        if ($this->option('no-interaction')) {
            return $shortcut !== '' ? $shortcut : 'mod+k';
        }

        return text(
            label: 'Default keyboard shortcut',
            default: $shortcut !== '' ? $shortcut : 'mod+k',
            hint: 'Use mod for Cmd on macOS and Ctrl elsewhere. Pick a different binding if Filament global search already uses this one.',
        );
    }

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
     * @return array<int, string>
     */
    protected function discoverProviders(): array
    {
        $patterns = [
            app_path('Providers/Filament/*PanelProvider.php'),
            app_path('Filament/**/*PanelProvider.php'),
        ];

        $files = [];

        foreach ($patterns as $pattern) {
            $matched = glob($pattern) ?: [];
            $files = array_merge($files, $matched);
        }

        return array_values(array_unique($files));
    }
}
