<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Fixtures;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

class SearchableUserResource extends Resource
{
    protected static ?string $model = PaletteUser::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $recordTitleAttribute = 'name';

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

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canView(Model $record): bool
    {
        return true;
    }

    public static function canEdit(Model $record): bool
    {
        return true;
    }

    public static function canDelete(Model $record): bool
    {
        return ! str_contains((string) $record->email, 'protected');
    }

    public static function getPages(): array
    {
        return [
            'index' => 'index',
            'create' => 'create',
            'view' => 'view',
            'edit' => 'edit',
        ];
    }

    /**
     * @param  array<mixed>  $parameters
     */
    public static function getUrl(?string $name = null, array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false, ?string $configuration = null): string
    {
        $record = $parameters['record'] ?? null;
        $key = $record instanceof Model ? $record->getKey() : $record;
        $page = $name ?? 'index';

        return '/admin/people/'.$page.(filled($key) ? '/'.$key : '');
    }
}
