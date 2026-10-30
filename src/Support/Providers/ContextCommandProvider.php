<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Support\Providers;

use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Usamamuneerchaudhary\CommandPalette\Contracts\HasPaletteCommands;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;
use Usamamuneerchaudhary\CommandPalette\Support\PaletteContext;

class ContextCommandProvider
{
    /**
     * @return Collection<int, CommandItem>
     */
    public function commands(Panel $panel, PaletteContext $context): Collection
    {
        $record = $context->record();
        $group = (string) __('command-palette::command-palette.groups.current_page');
        $commands = collect();

        foreach ($context->commandSources() as $class) {
            if (! is_subclass_of($class, HasPaletteCommands::class)) {
                continue;
            }

            try {
                $items = $class::getPaletteCommands();
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            foreach ($this->wrap($items) as $item) {
                if (! $item instanceof CommandItem) {
                    continue;
                }

                $item->bindRecord($record);
                $item->context();

                if ($item->needsRecord() && $record === null) {
                    continue;
                }

                if ($item->group === 'Navigation') {
                    $item->group($group);
                }

                if ($item->priority === 0) {
                    $item->priority(1000);
                }

                if ($item->icon === null) {
                    $item->icon(Heroicon::OutlinedCursorArrowRays);
                }

                $commands->push($item);
            }
        }

        return $commands->values();
    }

    /**
     * @return array<int, mixed>
     */
    protected function wrap(mixed $items): array
    {
        if ($items instanceof Collection) {
            return $items->all();
        }

        if ($items instanceof CommandItem) {
            return [$items];
        }

        return is_iterable($items) ? iterator_to_array($items, false) : [];
    }
}
