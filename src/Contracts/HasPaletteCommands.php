<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Contracts;

use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

interface HasPaletteCommands
{
    /**
     * Commands that belong to the page or resource currently on screen.
     *
     * @return iterable<int, CommandItem>
     */
    public static function getPaletteCommands(): iterable;
}
