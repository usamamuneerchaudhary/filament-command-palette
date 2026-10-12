<?php

namespace Usamamuneerchaudhary\CommandPalette\Support\Providers;

use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Usamamuneerchaudhary\CommandPalette\Contracts\CommandProvider;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

/**
 * Custom Filament pages, including ones hidden from the sidebar.
 *
 * Unlike getNavigation(), $panel->getPages() returns every registered page
 * regardless of policy, so authorization has to happen here.
 */
class PageCommandProvider implements CommandProvider
{
    use NormalizesIcons;

    public function commands(Panel $panel): iterable
    {
        $commands = collect();

        try {
            foreach ($panel->getPages() as $page) {
                if (! is_string($page) || ! is_subclass_of($page, Page::class)) {
                    continue;
                }

                if ($this->isAuthPage($page) || ! $this->canAccess($page)) {
                    continue;
                }

                try {
                    $url = $page::getUrl(panel: $panel->getId());
                } catch (\Throwable) {
                    continue;
                }

                if (blank($url)) {
                    continue;
                }

                $label = $this->stringify($page::getNavigationLabel(), class_basename($page));

                $commands->push(
                    CommandItem::make(
                        label: $label,
                        url: $url,
                        group: $this->resolveGroup($page),
                        icon: $this->normalizeIcon(
                            method_exists($page, 'getNavigationIcon') ? $page::getNavigationIcon() : null,
                            Heroicon::OutlinedDocument,
                        ),
                    )->id('page:'.$page)
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $commands;
    }

    /**
     * @param  class-string<Page>  $page
     */
    protected function canAccess(string $page): bool
    {
        if (! method_exists($page, 'canAccess')) {
            return true;
        }

        try {
            return (bool) $page::canAccess();
        } catch (\Throwable) {
            // A page whose access check blows up is a page we do not advertise.
            return false;
        }
    }

    /**
     * @param  class-string<Page>  $page
     */
    protected function resolveGroup(string $page): string
    {
        try {
            $group = $page::getNavigationGroup();
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
            : (string) __('command-palette::command-palette.groups.pages');
    }

    protected function isAuthPage(string $page): bool
    {
        return str_starts_with($page, 'Filament\\Auth\\')
            || str_starts_with($page, 'Filament\\Pages\\Auth\\');
    }
}
