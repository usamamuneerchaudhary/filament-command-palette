<?php

namespace Usamamuneerchaudhary\CommandPalette\Contracts;

use Filament\Panel;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

/**
 * Implemented by providers whose results depend on what the user typed
 * These providers are queried on the server on every (debounced) keystroke,
 * so keep them fast and always respect the configured result limit.
 */
interface SearchesCommands
{
    /**
     * @return iterable<int, CommandItem>
     */
    public function search(Panel $panel, string $query, int $limit): iterable;
}
