<?php

namespace Usamamuneerchaudhary\CommandPalette\Contracts;

use Filament\Panel;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

interface CommandProvider
{
    /**
     * Return the static commands this provider contributes for the given panel.
     *
     * Providers are responsible for authorization: never return a command the
     * current user is not allowed to reach. The registry applies a second
     * filter on top of this, but defence in depth is cheap here.
     *
     * @return iterable<int, CommandItem>
     */
    public function commands(Panel $panel): iterable;
}
