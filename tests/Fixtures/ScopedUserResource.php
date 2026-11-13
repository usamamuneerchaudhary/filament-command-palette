<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;

class ScopedUserResource extends SearchableUserResource
{
    /** @var list<int|string> */
    public static array $visibleIds = [];

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->whereIn('id', static::$visibleIds);
    }
}
