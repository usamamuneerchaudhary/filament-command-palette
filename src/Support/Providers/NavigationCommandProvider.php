<?php

namespace Usamamuneerchaudhary\CommandPalette\Support\Providers;

use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Usamamuneerchaudhary\CommandPalette\Contracts\CommandProvider;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

class NavigationCommandProvider implements CommandProvider
{
    use NormalizesIcons;

    public function commands(Panel $panel): iterable
    {
        $commands = collect();

        try {
            foreach ($panel->getNavigation() as $group) {
                if ($group instanceof NavigationGroup) {
                    $this->collectItems(
                        $group->getItems(),
                        $this->groupLabel($group->getLabel()),
                        $commands,
                    );

                    continue;
                }

                // Panels can return bare navigation items alongside groups.
                if ($group instanceof NavigationItem) {
                    $this->collectItems([$group], $this->groupLabel(null), $commands);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $commands;
    }

    /**
     * Filament keys ungrouped navigation with serialize(null), so the array key
     * is never a usable label — it renders as the literal string "N;".
     */
    protected function groupLabel(mixed $label): string
    {
        if ($label instanceof \BackedEnum) {
            return (string) $label->value;
        }

        if ($label instanceof \UnitEnum) {
            return $label->name;
        }

        $label = $this->stringify($label);

        return filled($label)
            ? $label
            : (string) __('command-palette::command-palette.groups.navigation');
    }

    /**
     * @param  iterable<mixed>  $items
     */
    protected function collectItems(iterable $items, string $group, Collection $commands, int $depth = 0): void
    {
        // Guards against a navigation tree that somehow references itself.
        if ($depth > 5) {
            return;
        }

        if ($items instanceof \Traversable) {
            $items = iterator_to_array($items);
        }

        foreach ($items as $item) {
            if (! $item instanceof NavigationItem) {
                continue;
            }

            try {
                if (! $item->isVisible()) {
                    continue;
                }

                $url = $item->getUrl();

                if (filled($url)) {
                    $commands->push(
                        CommandItem::make(
                            label: strip_tags((string) $item->getLabel()),
                            url: $url,
                            group: $group,
                            icon: $this->normalizeIcon($item->getIcon(), Heroicon::OutlinedArrowTopRightOnSquare),
                            openInNewTab: $item->shouldOpenUrlInNewTab(),
                        )
                    );
                }

                $this->collectItems($item->getChildItems(), $group, $commands, $depth + 1);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
