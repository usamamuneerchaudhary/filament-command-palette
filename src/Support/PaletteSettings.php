<?php

namespace Usamamuneerchaudhary\CommandPalette\Support;

use Filament\Facades\Filament;
use Filament\Panel;
use Usamamuneerchaudhary\CommandPalette\FilamentCommandPalettePlugin;

/**
 * Resolves a setting for one specific panel.
 */
class PaletteSettings
{
    protected static array $memo = [];

    public function __construct(
        protected ?Panel $panel = null,
        protected ?FilamentCommandPalettePlugin $plugin = null,
    ) {}

    public static function for(?Panel $panel = null): static
    {
        $panel ??= static::currentPanel();

        return new static($panel, static::resolvePlugin($panel));
    }

    public static function flushMemo(): void
    {
        static::$memo = [];
    }

    protected static function currentPanel(): ?Panel
    {
        try {
            return Filament::getCurrentOrDefaultPanel();
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function resolvePlugin(?Panel $panel): ?FilamentCommandPalettePlugin
    {
        if (! $panel) {
            return null;
        }

        $key = $panel->getId();

        if (array_key_exists($key, static::$memo)) {
            return static::$memo[$key];
        }

        try {
            $plugin = $panel->getPlugin(FilamentCommandPalettePlugin::PLUGIN_ID);
        } catch (\Throwable) {
            $plugin = null;
        }

        return static::$memo[$key] = $plugin instanceof FilamentCommandPalettePlugin ? $plugin : null;
    }

    public function panel(): ?Panel
    {
        return $this->panel;
    }

    public function plugin(): ?FilamentCommandPalettePlugin
    {
        return $this->plugin;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $override = $this->plugin?->getOption($key);

        if ($override !== null) {
            return value($override, $this->panel);
        }

        return config("command-palette.{$key}", $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    /**
     * @return array<mixed>
     */
    public function array(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : $default;
    }

    public function template(): string
    {
        $template = $this->get('template', 'standard');

        return in_array($template, ['standard', 'groups', 'preview'], true)
            ? $template
            : 'standard';
    }

    /**
     * @return array<int, string>
     */
    public function keyBindings(): array
    {
        $bindings = $this->array('key_bindings', ['mod+k']);

        $bindings = array_values(array_filter($bindings, 'is_string'));

        return $bindings !== [] ? $bindings : ['mod+k'];
    }

    public function maxResults(): int
    {
        return max(1, $this->int('max_results', 10));
    }

    public function cacheTtl(): int
    {
        return max(0, $this->int('cache.ttl', 0));
    }

    /**
     * @return array<string, mixed>
     */
    public function templateCopy(): array
    {
        $copy = $this->array('templates', [])[$this->template()] ?? [];

        return is_array($copy) ? $copy : [];
    }
}
