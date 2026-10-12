<?php

namespace Usamamuneerchaudhary\CommandPalette\Tests\Fixtures;

use Filament\Resources\Resource;

class CreateDeniedPostResource extends Resource
{
    protected static ?string $model = PaletteUser::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $modelLabel = 'Article';

    protected static ?string $pluralModelLabel = 'Articles';

    public static function canAccess(): bool
    {
        return true;
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => 'index',
            'create' => 'create',
        ];
    }
}
