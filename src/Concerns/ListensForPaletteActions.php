<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Concerns;

use Livewire\Attributes\On;

trait ListensForPaletteActions
{
    /**
     * Mounts a host page action when the palette dispatches the trigger event.
     *
     * @param  array<string, mixed>  $arguments
     */
    #[On('filament-command-palette:trigger-action')]
    public function mountCommandPaletteAction(string $name, array $arguments = []): mixed
    {
        return $this->mountAction($name, $arguments);
    }
}
