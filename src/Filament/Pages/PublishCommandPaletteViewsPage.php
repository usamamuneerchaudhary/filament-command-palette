<?php

namespace Usamamuneerchaudhary\CommandPalette\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;

class PublishCommandPaletteViewsPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'command-palette-publish-views';

    protected static ?string $title = 'Publish Command Palette Views';

    protected static ?string $navigationLabel = 'Publish Views';

    protected static string|\UnitEnum|null $navigationGroup = 'Command Palette';

    protected string $view = 'command-palette::filament.pages.publish-views';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label('Publish now')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->action('publishViews')
                ->color('primary'),
        ];
    }

    public function publishViews(): void
    {
        Artisan::call('vendor:publish', [
            '--tag' => 'command-palette-views',
            '--force' => true,
        ]);

        Notification::make()
            ->title('Views published successfully')
            ->body('The command palette views have been published to resources/views/vendor/command-palette/')
            ->success()
            ->send();
    }
}
