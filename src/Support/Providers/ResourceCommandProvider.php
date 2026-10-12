<?php

namespace Usamamuneerchaudhary\CommandPalette\Support\Providers;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Usamamuneerchaudhary\CommandPalette\Contracts\CommandProvider;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

/**
 * "List invoices" / "Create invoice" entries for every resource the current
 * user can actually reach.
 *
 * v1 pushed these for every registered resource, which meant unauthorized users
 * saw a full map of the panel and got a 403 on Enter. Each entry is now gated
 * on the same policy Filament itself checks.
 */
class ResourceCommandProvider implements CommandProvider
{
    use NormalizesIcons;

    public function commands(Panel $panel): iterable
    {
        $commands = collect();

        try {
            $tenant = $this->currentTenant($panel);

            foreach ($panel->getResources() as $resource) {
                try {
                    $this->collectResourceCommands($commands, $panel, $resource, $tenant);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $commands;
    }

    /**
     * @param  Collection<int, CommandItem>  $commands
     */
    protected function collectResourceCommands(Collection $commands, Panel $panel, mixed $resource, mixed $tenant): void
    {
        if (! is_string($resource) || ! is_subclass_of($resource, Resource::class)) {
            return;
        }

        if (! $this->canAccess($resource)) {
            return;
        }

        if ($this->requiresMissingTenant($resource, $tenant, $panel)) {
            return;
        }

        $singular = $this->stringify($resource::getModelLabel(), class_basename($resource));
        $plural = $this->stringify($resource::getPluralModelLabel(), $singular);
        $group = $this->resolveGroup($resource);
        $icon = $this->normalizeIcon(
            method_exists($resource, 'getNavigationIcon') ? $resource::getNavigationIcon() : null,
        );

        if ($this->can($resource, 'viewAny') && $resource::hasPage('index')) {
            $url = $this->url($resource, 'index', $tenant);

            if (filled($url)) {
                $commands->push(
                    CommandItem::make(
                        label: (string) __('command-palette::command-palette.commands.list', ['label' => $plural]),
                        url: $url,
                        group: $group,
                        icon: $icon ?? Heroicon::OutlinedListBullet,
                        keywords: [$plural, $singular, 'index', 'browse', 'all'],
                    )->id('resource:index:'.$resource)
                );
            }
        }

        if ($this->can($resource, 'create') && $resource::hasPage('create')) {
            $url = $this->url($resource, 'create', $tenant);

            if (filled($url)) {
                $commands->push(
                    CommandItem::make(
                        label: (string) __('command-palette::command-palette.commands.create', ['label' => $singular]),
                        url: $url,
                        group: $group,
                        icon: Heroicon::OutlinedPlus,
                        keywords: [$singular, 'new', 'add'],
                    )->id('resource:create:'.$resource)
                );
            }
        }
    }

    protected function currentTenant(Panel $panel): mixed
    {
        try {
            return $panel->hasTenancy() ? Filament::getTenant() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * A tenant-scoped resource has no reachable URL without a tenant in scope.
     * Advertising it would produce a broken link at best.
     *
     * Resources default to `isScopedToTenant = true` even on panels that do
     * not use tenancy; only skip when the panel actually has a tenant model.
     *
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    protected function requiresMissingTenant(string $resource, mixed $tenant, Panel $panel): bool
    {
        if ($tenant !== null) {
            return false;
        }

        try {
            if (! $panel->hasTenancy()) {
                return false;
            }

            return method_exists($resource, 'isScopedToTenant') && $resource::isScopedToTenant();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    protected function canAccess(string $resource): bool
    {
        if (! method_exists($resource, 'canAccess')) {
            return true;
        }

        try {
            return (bool) $resource::canAccess();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    protected function can(string $resource, string $ability): bool
    {
        $method = 'can'.ucfirst($ability);

        if (! method_exists($resource, $method)) {
            return true;
        }

        try {
            return (bool) $resource::{$method}();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    protected function url(string $resource, string $name, mixed $tenant): ?string
    {
        try {
            return $resource::getUrl(
                name: $name,
                shouldGuessMissingParameters: true,
                tenant: $tenant,
            );
        } catch (\Throwable) {
            // Older signatures, or a route that genuinely needs parameters we
            // cannot supply. Either way there is nothing useful to link to.
            try {
                return $resource::getUrl($name);
            } catch (\Throwable) {
                return null;
            }
        }
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    protected function resolveGroup(string $resource): string
    {
        try {
            $group = $resource::getNavigationGroup();
        } catch (\Throwable) {
            $group = null;
        }

        if ($group instanceof \BackedEnum) {
            return (string) $group->value;
        }

        if ($group instanceof \UnitEnum) {
            return $group->name;
        }

        $group = $this->stringify($group);

        return filled($group)
            ? $group
            : (string) __('command-palette::command-palette.groups.resources');
    }
}
