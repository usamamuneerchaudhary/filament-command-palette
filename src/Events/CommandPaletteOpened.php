<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Events;

use Illuminate\Foundation\Events\Dispatchable;

class CommandPaletteOpened
{
    use Dispatchable;

    public function __construct(
        public ?string $panelId = null,
        public mixed $user = null,
    ) {}
}
