<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Fixtures;

class DeniedRecordResource extends SearchableUserResource
{
    public static function canViewAny(): bool
    {
        return false;
    }
}
