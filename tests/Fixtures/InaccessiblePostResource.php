<?php

namespace Usamamuneerchaudhary\CommandPalette\Tests\Fixtures;

use Filament\Resources\Resource;

class InaccessiblePostResource extends Resource
{
    protected static ?string $model = PaletteUser::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $modelLabel = 'Secret';

    protected static ?string $pluralModelLabel = 'Secrets';

    public static function canAccess(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        return true;
    }

    public static function getPages(): array
    {
        return [
            'index' => 'index',
            'create' => 'create',
        ];
    }
}
