<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Concerns;

use Livewire\Attributes\On;

/**
 * Optional listener for pages that prefer a Livewire event over the default
 * `Livewire.find(hostId).call('mountAction')` bridge.
 */
trait ListensForPaletteActions
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    #[On('filament-command-palette:trigger-action')]
    public function mountCommandPaletteAction(string $name, array $arguments = []): mixed
    {
        return $this->mountAction($name, $arguments);
    }
}
