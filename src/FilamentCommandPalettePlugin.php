<?php

namespace Usamamuneerchaudhary\CommandPalette;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use Usamamuneerchaudhary\CommandPalette\Filament\Pages\PublishCommandPaletteViewsPage;
use Usamamuneerchaudhary\CommandPalette\Livewire\CommandPalette;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;
use Usamamuneerchaudhary\CommandPalette\Support\PaletteSettings;

/**
 * Register the palette on a panel, and configure it per panel.
 *
 *     FilamentCommandPalettePlugin::make()
 *         ->keyBindings(['mod+k'])
 *         ->template('groups')
 *         ->commands(fn () => [
 *             CommandItem::make('Clear cache')
 *                 ->action(fn () => Artisan::call('cache:clear'))
 *                 ->authorize(fn () => auth()->user()->isAdmin()),
 *         ])
 *
 * Most options accept a closure, evaluated lazily against the panel at
 * request time. Page registration and render hooks always run; visibility
 * for the topbar button and publish-views page is decided per request.
 */
class FilamentCommandPalettePlugin implements Plugin
{
    public const PLUGIN_ID = 'command-palette';

    /** @var array<string, mixed> */
    protected array $options = [];

    /** @var array<int, Closure|CommandItem> */
    protected array $commands = [];

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return self::PLUGIN_ID;
    }

    public function register(Panel $panel): void
    {
        $panel
            ->livewireComponents([
                CommandPalette::class,
            ])
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('command-palette::hooks.body-end', [
                    'panelId' => $panel->getId(),
                ])->render(),
            )
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): HtmlString => $this->stylesheet(),
            )
            ->pages([
                PublishCommandPaletteViewsPage::class,
            ])
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): string => view('command-palette::hooks.topbar-trigger', [
                    'panelId' => $panel->getId(),
                ])->render(),
            );
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * Load the stylesheet on panels that actually register the plugin.
     *
     * The asset is registered with `loadedOnRequest()` so Filament does not
     * inject it into every panel. Inline the package source so a stale
     * `filament:assets` copy cannot ship an older stylesheet.
     */
    protected function stylesheet(): HtmlString
    {
        $cssPath = __DIR__.'/../resources/css/command-palette.css';

        if (! is_file($cssPath)) {
            return new HtmlString('');
        }

        return new HtmlString('<style>'.file_get_contents($cssPath).'</style>');
    }

    // ---------------------------------------------------------------------
    // Fluent configuration
    // ---------------------------------------------------------------------

    /**
     * @param  array<int, string>|Closure  $bindings
     */
    public function keyBindings(array|Closure $bindings): static
    {
        return $this->option('key_bindings', $bindings);
    }

    public function template(string|Closure $template): static
    {
        return $this->option('template', $template);
    }

    public function maxResults(int|Closure $max): static
    {
        return $this->option('max_results', $max);
    }

    public function topbarButton(bool|Closure $condition = true): static
    {
        return $this->option('show_topbar_button', $condition);
    }

    public function placeholder(string|Closure $placeholder): static
    {
        return $this->option('placeholder', $placeholder);
    }

    public function recordSearch(bool|Closure $condition = true, ?int $maxResults = null): static
    {
        $this->option('record_search.enabled', $condition);

        if ($maxResults !== null) {
            $this->option('record_search.max_results', $maxResults);
        }

        return $this;
    }

    public function recents(bool|Closure $condition = true, ?int $max = null): static
    {
        $this->option('recents.enabled', $condition);

        if ($max !== null) {
            $this->option('recents.max', $max);
        }

        return $this;
    }

    /**
     * Cache the built command list for this many seconds. Zero rebuilds on
     * every open, which is the safe default when navigation is dynamic.
     */
    public function cacheFor(int|Closure $seconds): static
    {
        return $this->option('cache.ttl', $seconds);
    }

    /**
     * Turn built-in commands on or off individually.
     *
     * @param  array<string, bool>|Closure  $commands
     */
    public function builtInCommands(array|Closure $commands): static
    {
        return $this->option('built_in_commands', $commands);
    }

    public function publishViewsCommand(bool|Closure $condition = true): static
    {
        return $this->option('include_publish_views_command', $condition);
    }

    /**
     * A final say over every command, whatever produced it.
     */
    public function authorize(Closure $callback): static
    {
        return $this->option('authorize', fn (): Closure => $callback);
    }

    /**
     * Replace the provider pipeline entirely.
     *
     * @param  array<int, class-string>|Closure  $providers
     */
    public function providers(array|Closure $providers): static
    {
        return $this->option('providers', $providers);
    }

    /**
     * @param  array<int, class-string>|Closure  $providers
     */
    public function searchProviders(array|Closure $providers): static
    {
        return $this->option('search_providers', $providers);
    }

    /**
     * Register application commands.
     *
     * This is the replacement for the `custom_commands` config key. Closures
     * belong here, not in a config file — config files get serialized by
     * `php artisan config:cache` and closures cannot survive that.
     *
     * @param  Closure|CommandItem|array<int, Closure|CommandItem>  $commands
     */
    public function commands(Closure|CommandItem|array $commands): static
    {
        foreach (is_array($commands) ? $commands : [$commands] as $command) {
            $this->commands[] = $command;
        }

        return $this;
    }

    /**
     * @return array<int, Closure|CommandItem>
     */
    public function getRegisteredCommands(): array
    {
        return $this->commands;
    }

    public function option(string $key, mixed $value): static
    {
        $this->options[$key] = $value;

        PaletteSettings::flushMemo();

        return $this;
    }

    public function getOption(string $key): mixed
    {
        return $this->options[$key] ?? null;
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(self::PLUGIN_ID);

        return $plugin;
    }
}
