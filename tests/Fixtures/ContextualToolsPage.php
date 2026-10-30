<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Fixtures;

use Filament\Pages\Page;
use Usamamuneerchaudhary\CommandPalette\Contracts\HasPaletteCommands;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

class ContextualToolsPage extends Page implements HasPaletteCommands
{
    protected string $view = 'filament-panels::pages.page';

    protected static ?string $slug = 'contextual-tools';

    protected static ?string $title = 'Contextual tools';

    public static function canAccess(): bool
    {
        return true;
    }

    public static function getNavigationLabel(): string
    {
        return 'Contextual tools';
    }

    public static function getUrl(mixed ...$arguments): string
    {
        return '/admin/contextual-tools';
    }

    public static function getPaletteCommands(): iterable
    {
        return [
            CommandItem::make('Regenerate invoice PDF')
                ->id('context:pdf')
                ->keywords(['pdf', 'invoice'])
                ->action(fn (): string => '/pdf-ran'),
        ];
    }
}
