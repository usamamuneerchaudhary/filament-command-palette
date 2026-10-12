<?php

namespace Usamamuneerchaudhary\CommandPalette\Tests\Fixtures;

use Filament\Pages\Page;

class VisibleToolsPage extends Page
{
    protected string $view = 'filament-panels::pages.page';

    protected static ?string $slug = 'visible-tools';

    protected static ?string $title = 'Visible tools';

    public static function canAccess(): bool
    {
        return true;
    }

    public static function getNavigationLabel(): string
    {
        return 'Visible tools';
    }

    public static function getUrl(mixed ...$arguments): string
    {
        return '/admin/visible-tools';
    }
}
