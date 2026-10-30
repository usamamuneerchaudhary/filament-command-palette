<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a palette command runs. The payload has no closures, so queued listeners can serialize it.
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
