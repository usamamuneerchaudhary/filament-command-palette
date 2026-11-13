<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Usamamuneerchaudhary\CommandPalette\Contracts\HasPaletteRecordActions;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

class PaletteRecordActionsResource extends SearchableUserResource implements HasPaletteRecordActions
{
    public static function getPaletteRecordActions(Model $record): iterable
    {
        return [
            CommandItem::make('Impersonate')
                ->id('record-custom:impersonate:'.$record->getKey())
                ->action(fn (): string => '/impersonate/'.$record->getKey()),
        ];
    }
}
