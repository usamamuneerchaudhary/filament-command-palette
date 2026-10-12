<?php

namespace Usamamuneerchaudhary\CommandPalette\Tests\Fixtures;

use Filament\Pages\Page;

class HiddenToolsPage extends Page
{
    protected string $view = 'filament-panels::pages.page';

    protected static ?string $slug = 'hidden-tools';

    protected static ?string $title = 'Hidden tools';

    public static function canAccess(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return 'Hidden tools';
    }

    public static function getUrl(mixed ...$arguments): string
    {
        return '/admin/hidden-tools';
    }
}
