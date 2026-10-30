<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Fixtures;

use Filament\Resources\Resource;
use Usamamuneerchaudhary\CommandPalette\Contracts\HasPaletteCommands;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

class ContextualUserResource extends Resource implements HasPaletteCommands
{
    protected static ?string $model = PaletteUser::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $modelLabel = 'Person';

    protected static ?string $pluralModelLabel = 'People';

    public static function canAccess(): bool
    {
        return true;
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function getPages(): array
    {
        return [
            'edit' => ContextualEditUserPage::class,
        ];
    }

    public static function getPaletteCommands(): iterable
    {
        return [
            CommandItem::make('Copy record ID')
                ->id('context:copy-id')
                ->action(fn (CommandItem $command, PaletteUser $record): string => '/copied/'.$record->getKey()),
        ];
    }
}
