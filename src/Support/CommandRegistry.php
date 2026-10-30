<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Support;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Usamamuneerchaudhary\CommandPalette\Contracts\CommandProvider;
use Usamamuneerchaudhary\CommandPalette\Contracts\SearchesCommands;
use Usamamuneerchaudhary\CommandPalette\Support\Providers\ActionCommandProvider;
use Usamamuneerchaudhary\CommandPalette\Support\Providers\ContextCommandProvider;
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
     * Query the providers that need a search term, record search and anything
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
    public function find(string $id, ?Panel $panel = null, ?string $query = null, ?PaletteContext $context = null): ?CommandItem
    {
        $panel = $this->resolvePanel($panel);
        $roots = $this->all($panel);

        if ($context instanceof PaletteContext) {
            $roots = $roots->concat($this->contextCommands($panel, $context));
        }

        $found = $this->findInTree($id, $roots);

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
     * Page and record commands for the screen currently open. Never cached
     * with the static list — the URL changes more often than the navigation.
     *
     * @return Collection<int, CommandItem>
     */
    public function contextCommands(?Panel $panel = null, ?PaletteContext $context = null): Collection
    {
        $panel = $this->resolvePanel($panel);

        if (! $panel || ! $context instanceof PaletteContext) {
            return collect();
        }

        $settings = PaletteSettings::for($panel);

        try {
            $commands = collect(app(ContextCommandProvider::class)->commands($panel, $context));
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }

        return $this->finalize($commands, $settings);
    }

    /**
     * @return Collection<int, CommandItem>
     */
    public function childrenOf(CommandItem $parent, ?Panel $panel = null, ?PaletteContext $context = null): Collection
    {
        $panel = $this->resolvePanel($panel);

        if (! $panel || ! $parent->hasChildren()) {
            return collect();
        }

        $depth = $parent->getSubmenuDepth() + 1;
        $children = collect($parent->resolveChildren());
        $record = $parent->getBoundRecord() ?? $context?->record();

        $children->each(function (CommandItem $child) use ($depth, $record): void {
            $child->submenuDepth($depth);
            $child->bindRecord($record);

            if ($depth >= CommandItem::MAX_SUBMENU_DEPTH) {
                $child->children(null);
            }
        });

        return $this->finalize($children, PaletteSettings::for($panel));
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

    /**
     * Cache the browser payload (commands + icons) against a single `all()` build.
     *
     * @param  callable(Collection<int, CommandItem>): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    public function rememberClient(Panel $panel, callable $callback): array
    {
        $ttl = PaletteSettings::for($panel)->cacheTtl();
        $build = fn (): array => $callback($this->all($panel));

        if ($ttl <= 0) {
            return $build();
        }

        return Cache::remember(
            $this->cacheKey($panel).':client',
            $ttl,
            $build,
        );
    }

    public function forget(?Panel $panel = null): void
    {
        $panel = $this->resolvePanel($panel);

        if ($panel) {
            Cache::forget($this->cacheKey($panel));
            Cache::forget($this->cacheKey($panel).':client');
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
            $this->authSignature($panel),
        ]);
    }

    /**
     * @param  Collection<int, mixed>  $items
     */
    protected function findInTree(string $id, Collection $items, int $depth = 0): ?CommandItem
    {
        if ($depth > CommandItem::MAX_SUBMENU_DEPTH) {
            return null;
        }

        foreach ($items as $item) {
            if (! $item instanceof CommandItem) {
                continue;
            }

            if (hash_equals($item->getId(), $id)) {
                return $item->submenuDepth($depth);
            }

            if (! $item->hasChildren()) {
                continue;
            }

            $found = $this->findInTree($id, collect($item->resolveChildren()), $depth + 1);

            if ($found instanceof CommandItem) {
                $found->bindRecord($found->getBoundRecord() ?? $item->getBoundRecord());

                return $found;
            }
        }

        return null;
    }

    protected function authSignature(Panel $panel): string
    {
        $settings = PaletteSettings::for($panel);
        $custom = $settings->get('cache.signature');

        if ($custom !== null) {
            $encoded = is_scalar($custom) ? (string) $custom : json_encode($custom);

            return hash('xxh128', (string) $encoded);
        }

        $parts = [];

        try {
            $user = Filament::auth()->user();
        } catch (\Throwable) {
            $user = null;
        }

        if ($user === null) {
            return 'guest';
        }

        if (method_exists($user, 'getRoleNames')) {
            try {
                $parts[] = 'roles:'.$user->getRoleNames()->sort()->implode(',');
            } catch (\Throwable) {
                // Role APIs differ across auth stacks.
            }
        }

        if (method_exists($user, 'getPermissionNames')) {
            try {
                $parts[] = 'permissions:'.$user->getPermissionNames()->sort()->implode(',');
            } catch (\Throwable) {
                // Permission APIs differ across auth stacks.
            }
        }

        if ($parts === []) {
            return 'auth';
        }

        return hash('xxh128', implode('|', $parts));
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
