<?php

namespace Usamamuneerchaudhary\CommandPalette\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired every time someone runs something from the palette.
 *
 * The command payload is the same array the browser receives — no closures —
 * so queued listeners can serialize this event.
 */
class CommandExecuted
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $command
     */
    public function __construct(
        public array $command,
        public ?string $panelId = null,
        public ?string $query = null,
        public int|string|null $userId = null,
    ) {}
}
