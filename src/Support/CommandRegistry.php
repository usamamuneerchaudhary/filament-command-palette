<?php

namespace Usamamuneerchaudhary\CommandPalette\Support;

use Illuminate\Support\Collection;
use Usamamuneerchaudhary\CommandPalette\Support\Commands\NavigationCommands;

class CommandRegistry
{
    /**
     * Get all commands
     */
    public function getCommands(?string $search = null, bool $limit = true, ?\Filament\Panel $panel = null): Collection
    {
        $commands = collect();

        $commands = $commands->merge(NavigationCommands::get($panel));

        foreach (config('command-palette.custom_commands', []) as $callback) {
            if (is_callable($callback)) {
                try {
                    $custom = $callback();
                    if (is_array($custom)) {
                        $commands = $commands->merge($custom);
                    } elseif ($custom instanceof Collection) {
                        $commands = $commands->merge($custom);
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        if (filled($search)) {
            $search = strtolower(trim($search));
            $commands = $commands->filter(function (CommandItem $item) use ($search) {
                return str_contains(strtolower($item->label), $search)
                    || str_contains(strtolower($item->group), $search);
            });
        }

        $commands = $commands->unique(fn (CommandItem $item) => $item->url);

        $grouped = $commands->groupBy('group');

        $result = collect();
        foreach ($grouped as $group => $items) {
            $result = $result->merge($items);
        }

        if ($limit) {
            $maxResults = config('command-palette.max_results', 5);
            $result = $result->take($maxResults);
        }

        return $result->values();
    }

    /**
     * Get all commands (no search filter, no limit) for client-side filtering.
     */
    public function getAllCommands(?\Filament\Panel $panel = null): Collection
    {
        return $this->getCommands(search: null, limit: false, panel: $panel);
    }
}
