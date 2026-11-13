<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Contracts;

use Illuminate\Database\Eloquent\Model;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

interface HasPaletteRecordActions
{
    /**
     * Extra commands shown after the user steps into a record from search.
     *
     * @return iterable<int, CommandItem>
     */
    public static function getPaletteRecordActions(Model $record): iterable;
}
