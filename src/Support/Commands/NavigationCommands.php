<?php

namespace Usamamuneerchaudhary\CommandPalette\Support\Commands;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Support\Collection;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

class NavigationCommands
{
    public static function get(?\Filament\Panel $panel = null): Collection
    {
        $commands = collect();

        try {
            $panel = $panel ?? Filament::getCurrentOrDefaultPanel();

            if (! $panel) {
                return $commands;
            }

            $commands = $commands
                ->merge(static::getFromNavigation($panel))
                ->merge(static::getFromPages($panel))
                ->merge(static::getFromResources($panel));
        } catch (\Throwable $e) {
            report($e);
        }

        return $commands;
    }

    protected static function getFromNavigation($panel): Collection
    {
        $commands = collect();

        try {
            $navigation = $panel->getNavigation();

            foreach ($navigation as $group) {
                if ($group instanceof NavigationGroup) {
                    $groupLabel = $group->getLabel() ?? 'Navigation';
                    $items = $group->getItems();

                    if ($items instanceof \Traversable) {
                        $items = iterator_to_array($items);
                    }

                    foreach ($items as $item) {
                        if ($item instanceof NavigationItem && $item->isVisible()) {
                            static::flattenNavigationItem($item, $groupLabel, $commands);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $commands;
    }


    protected static function flattenNavigationItem(NavigationItem $item, string $groupLabel, Collection $commands): void
    {
        $url = $item->getUrl();
        $childItems = $item->getChildItems();

        if ($childItems instanceof \Traversable) {
            $childItems = iterator_to_array($childItems);
        }

        if (filled($url)) {
            $icon = $item->getIcon();
            $icon = ($icon instanceof \BackedEnum || is_string($icon)) ? $icon : \Filament\Support\Icons\Heroicon::OutlinedArrowTopRightOnSquare;

            $commands->push(CommandItem::make(
                label: $item->getLabel(),
                url: $url,
                group: $groupLabel,
                icon: $icon,
                openInNewTab: $item->shouldOpenUrlInNewTab(),
            ));
        }

        foreach ($childItems as $child) {
            if ($child instanceof NavigationItem && $child->isVisible() && filled($child->getUrl())) {
                $icon = $child->getIcon();
                $icon = ($icon instanceof \BackedEnum || is_string($icon)) ? $icon : \Filament\Support\Icons\Heroicon::OutlinedArrowTopRightOnSquare;

                $commands->push(CommandItem::make(
                    label: $child->getLabel(),
                    url: $child->getUrl(),
                    group: $groupLabel,
                    icon: $icon,
                    openInNewTab: $child->shouldOpenUrlInNewTab(),
                ));
            }
        }
    }

    protected static function getFromPages($panel): Collection
    {
        $commands = collect();

        try {
            $pages = $panel->getPages();

            foreach ($pages as $page) {
                if (! is_string($page) || ! is_subclass_of($page, Page::class)) {
                    continue;
                }

                if (static::isAuthPage($page)) {
                    continue;
                }

                if (! method_exists($page, 'getUrl')) {
                    continue;
                }

                try {
                    $url = $page::getUrl();

                    if (blank($url)) {
                        continue;
                    }

                    $title = $page::getNavigationLabel();
                    $group = $page::getNavigationGroup();

                    if (blank($title)) {
                        $title = $page::getTitle();
                    }

                    if ($title instanceof \Illuminate\Contracts\Support\Htmlable) {
                        $title = $title->toHtml();
                    } else {
                        $title = (string) $title;
                    }

                    $groupValue = $group instanceof \UnitEnum
                        ? (string) ($group->value ?? $group->name)
                        : ((string) ($group ?? 'Pages'));

                    $navIcon = method_exists($page, 'getNavigationIcon') ? $page::getNavigationIcon() : null;
                    $icon = ($navIcon instanceof \BackedEnum || is_string($navIcon)) ? $navIcon : \Filament\Support\Icons\Heroicon::OutlinedHome;

                    $commands->push(CommandItem::make(
                        label: strip_tags($title),
                        url: $url,
                        group: $groupValue,
                        icon: $icon,
                    ));
                } catch (\Throwable $e) {
                    continue;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $commands;
    }

    protected static function isAuthPage(string $page): bool
    {
        return str_starts_with($page, 'Filament\\Auth\\');
    }

    protected static function getFromResources($panel): Collection
    {
        $commands = collect();

        try {
            $resources = $panel->getResources();

            foreach ($resources as $resource) {
                if (! is_string($resource) || ! is_subclass_of($resource, Resource::class)) {
                    continue;
                }

                $modelLabel = $resource::getModelLabel();
                $pluralLabel = $resource::getPluralModelLabel();

                if ($pluralLabel instanceof \Closure) {
                    $pluralLabel = $pluralLabel();
                }
                if (is_object($pluralLabel) && method_exists($pluralLabel, '__toString')) {
                    $pluralLabel = (string) $pluralLabel;
                }
                $pluralLabel = (string) $pluralLabel;

                if ($modelLabel instanceof \Closure) {
                    $modelLabel = $modelLabel();
                }
                if (is_object($modelLabel) && method_exists($modelLabel, '__toString')) {
                    $modelLabel = (string) $modelLabel;
                }
                $modelLabel = (string) $modelLabel;

                try {
                    if ($resource::hasPage('index')) {
                        $listUrl = $resource::getUrl('index', shouldGuessMissingParameters: true);
                        $commands->push(CommandItem::make(
                            label: "List {$pluralLabel}",
                            url: $listUrl,
                            group: 'Resources',
                            icon: \Filament\Support\Icons\Heroicon::OutlinedListBullet,
                        ));
                    }

                    if ($resource::hasPage('create')) {
                        $createUrl = $resource::getUrl('create', shouldGuessMissingParameters: true);
                        $commands->push(CommandItem::make(
                            label: "Create {$modelLabel}",
                            url: $createUrl,
                            group: 'Resources',
                            icon: \Filament\Support\Icons\Heroicon::OutlinedPlus,
                        ));
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $commands;
    }
}
