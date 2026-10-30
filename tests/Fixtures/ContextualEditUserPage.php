<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Fixtures;

use Filament\Resources\Pages\Page;

class ContextualEditUserPage extends Page
{
    protected static string $resource = ContextualUserResource::class;

    protected string $view = 'filament-panels::pages.page';

    public static function canAccess(array $parameters = []): bool
    {
        return true;
    }
}
