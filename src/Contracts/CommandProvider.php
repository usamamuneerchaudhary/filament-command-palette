<?php

namespace Usamamuneerchaudhary\CommandPalette\Contracts;

use Filament\Panel;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

interface CommandProvider
{
    /**
     * Return the static commands this provider contributes for the given panel.
     *
     * Providers are responsible for authorization
     *
     * @return iterable<int, CommandItem>
     */
    public function commands(Panel $panel): iterable;
}
