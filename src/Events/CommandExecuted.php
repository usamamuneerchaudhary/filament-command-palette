<?php

namespace Usamamuneerchaudhary\CommandPalette\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

/**
 * Fired every time someone runs something from the palette.
 *
 * Listen to this to find out which commands actually earn their place — the
 * ones nobody runs are the ones to cut.
 */
class CommandExecuted
{
    use Dispatchable;

    public function __construct(
        public CommandItem $command,
        public ?string $panelId = null,
        public ?string $query = null,
        public mixed $user = null,
    ) {}
}
