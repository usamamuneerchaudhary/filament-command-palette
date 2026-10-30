<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Fixtures;

use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

class ClearCacheCommand
{
    public static bool $ran = false;

    public function __invoke(CommandItem $command): string
    {
        self::$ran = true;

        return '/cache-cleared';
    }
}
