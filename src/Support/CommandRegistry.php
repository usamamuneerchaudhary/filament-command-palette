<?php

namespace Usamamuneerchaudhary\CommandPalette\Support;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Usamamuneerchaudhary\CommandPalette\Contracts\CommandProvider;
use Usamamuneerchaudhary\CommandPalette\Contracts\SearchesCommands;
use Usamamuneerchaudhary\CommandPalette\Support\Providers\ActionCommandProvider;
use Usamamuneerchaudhary\CommandPalette\Support\Providers\CustomCommandProvider;
use Usamamuneerchaudhary\CommandPalette\Support\Providers\NavigationCommandProvider;
use Usamamuneerchaudhary\CommandPalette\Support\Providers\PageCommandProvider;
use Usamamuneerchaudhary\CommandPalette\Support\Providers\RecordCommandProvider;
use Usamamuneerchaudhary\CommandPalette\Support\Providers\ResourceCommandProvider;

class CommandRegistry
{
    /** @var array<int, class-string<CommandProvider>> */
    public const DEFAULT_PROVIDERS = [
        NavigationCommandProvider::class,
        PageCommandProvider::class,
        ResourceCommandProvider::class,
        ActionCommandProvider::class,
        CustomCommandProvider::class,
    ];

    /** @var array<int, class-string<SearchesCommands>> */
    public const DEFAULT_SEARCH_PROVIDERS = [
        RecordCommandProvider::class,
    ];

    /**
     * Build every static command the current user is allowed to see.
     *
     * @return Collection<int, CommandItem>
     */
    public function all(?Panel $panel = null): Collection
    {
        $panel = $this->resolvePanel($panel);

        if (! $panel) {
            return collect();
        }

        $settings = PaletteSettings::for($panel);
        $commands = collect();

        foreach ($this->providers($settings) as $provider) {
            try {
                $commands = $commands->merge($provider->commands($panel));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->finalize($commands, $settings);
    }

    /**
     * Query the providers that need a search term — record search and anything
     * the application has added alongside it.
     *
     * @return Collection<int, CommandItem>
     */
    public function search(string $query, ?Panel $panel = null): Collection
    {
        $panel = $this->resolvePanel($panel);
        $query = trim($query);

        if (! $panel || $query === '') {
            return collect();
        }

        $settings = PaletteSettings::for($panel);

        if (! $settings->bool('record_search.enabled', true)) {
            return collect();
        }

        $limit = max(1, $settings->int('record_search.max_results', 15));
        $commands = collect();

        foreach ($this->searchProviders($settings) as $provider) {
            try {
                $commands = $commands->merge(
                    $provider->search($panel, $query, $limit - $commands->count())
                );
            } catch (\Throwable $e) {
                report($e);
            }

            if ($commands->count() >= $limit) {
                break;
            }
        }

        return $this->finalize($commands, $settings)->take($limit)->values();
    }

    /**
     * Look a command up by id, rebuilding it from scratch.
     *
     * Nothing here trusts the browser: the command is only returned if the
     * providers still produce it and it still passes authorization right now.
     */
    public function find(string $id, ?Panel $panel = null, ?string $query = null): ?CommandItem
    {
        $found = $this->all($panel)->first(
            fn (CommandItem $item): bool => hash_equals($item->getId(), $id)
        );

        if ($found instanceof CommandItem) {
            return $found;
        }

        if (filled($query)) {
            $found = $this->search($query, $panel)->first(
                fn (CommandItem $item): bool => hash_equals($item->getId(), $id)
            );
        }

        return $found instanceof CommandItem ? $found : null;
    }

    /**
     * The array the browser receives. Cacheable because it contains no closures.
     *
     * @return array<int, array<string, mixed>>
     */
    public function payload(?Panel $panel = null): array
    {
        $panel = $this->resolvePanel($panel);

        if (! $panel) {
            return [];
        }

        $settings = PaletteSettings::for($panel);
        $ttl = $settings->cacheTtl();

        if ($ttl <= 0) {
            return $this->buildPayload($panel);
        }

        return Cache::remember(
            $this->cacheKey($panel),
            $ttl,
            fn (): array => $this->buildPayload($panel),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchPayload(string $query, ?Panel $panel = null): array
    {
        return $this->search($query, $panel)
            ->map(fn (CommandItem $item): array => $item->toArray())
            ->values()
            ->all();
    }

    public function forget(?Panel $panel = null): void
    {
        $panel = $this->resolvePanel($panel);

        if ($panel) {
            Cache::forget($this->cacheKey($panel));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildPayload(Panel $panel): array
    {
        return $this->all($panel)
            ->map(fn (CommandItem $item): array => $item->toArray())
            ->values()
            ->all();
    }

    protected function cacheKey(Panel $panel): string
    {
        $userKey = 'guest';

        try {
            $userKey = (string) (Filament::auth()->id() ?? 'guest');
        } catch (\Throwable) {
            // Auth may not be resolvable outside a request; the guest key is fine.
        }

        $tenantKey = 'none';

        try {
            $tenantKey = (string) (Filament::getTenant()?->getKey() ?? 'none');
        } catch (\Throwable) {
            // No tenancy configured.
        }

        return implode(':', [
            'command-palette',
            $panel->getId(),
            $userKey,
            $tenantKey,
            app()->getLocale(),
        ]);
    }

    /**
     * Drop hidden and unauthorized commands, remove duplicates, apply ordering.
     *
     * @param  Collection<int, mixed>  $commands
     * @return Collection<int, CommandItem>
     */
    protected function finalize(Collection $commands, PaletteSettings $settings): Collection
    {
        $gate = $settings->get('authorize');

        return $commands
            ->filter(fn (mixed $item): bool => $item instanceof CommandItem)
            ->filter(function (CommandItem $item): bool {
                try {
                    return $item->isVisible();
                } catch (\Throwable $e) {
                    report($e);

                    // A check that errors is treated as a denial.
                    return false;
                }
            })
            ->when(
                $gate instanceof \Closure,
                fn (Collection $items): Collection => $items->filter(
                    fn (CommandItem $item): bool => (bool) $gate($item)
                )
            )
            ->unique(fn (CommandItem $item): string => $item->getId())
            ->sortByDesc(fn (CommandItem $item): int => $item->priority)
            ->values();
    }

    /**
     * @return array<int, CommandProvider>
     */
    protected function providers(PaletteSettings $settings): array
    {
        return $this->instantiate(
            $settings->array('providers', self::DEFAULT_PROVIDERS),
            CommandProvider::class,
        );
    }

    /**
     * @return array<int, SearchesCommands>
     */
    protected function searchProviders(PaletteSettings $settings): array
    {
        return $this->instantiate(
            $settings->array('search_providers', self::DEFAULT_SEARCH_PROVIDERS),
            SearchesCommands::class,
        );
    }

    /**
     * @param  array<int, mixed>  $classes
     * @return array<int, mixed>
     */
    protected function instantiate(array $classes, string $contract): array
    {
        $resolved = [];

        foreach ($classes as $class) {
            try {
                $instance = is_object($class) ? $class : app($class);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            if ($instance instanceof $contract) {
                $resolved[] = $instance;
            }
        }

        return $resolved;
    }

    protected function resolvePanel(?Panel $panel): ?Panel
    {
        if ($panel) {
            return $panel;
        }

        try {
            return Filament::getCurrentOrDefaultPanel();
        } catch (\Throwable) {
            return null;
        }
    }
}
