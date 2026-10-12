<?php

namespace Usamamuneerchaudhary\CommandPalette\Filament\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Usamamuneerchaudhary\CommandPalette\Support\PaletteSettings;

class PublishCommandPaletteViewsPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'command-palette-publish-views';

    protected string $view = 'command-palette::filament.pages.publish-views';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    public static function getNavigationLabel(): string
    {
        return __('command-palette::command-palette.publish_views.navigation_label');
    }

    public function getTitle(): string
    {
        return __('command-palette::command-palette.publish_views.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('command-palette::command-palette.publish_views.group');
    }

    /**
     * Publishing writes to the application's view directory, so this is
     * restricted to local development unless an app opts in explicitly.
     */
    public static function canAccess(): bool
    {
        if (! app()->environment('local')) {
            return false;
        }

        try {
            $panel = Filament::getCurrentPanel() ?? Filament::getCurrentOrDefaultPanel();
        } catch (\Throwable) {
            $panel = null;
        }

        return PaletteSettings::for($panel)->bool('include_publish_views_command', true);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label(__('command-palette::command-palette.publish_views.action'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->requiresConfirmation()
                ->action('publishViews')
                ->color('primary'),
        ];
    }

    public function publishViews(): void
    {
        abort_unless(static::canAccess(), 403);

        Artisan::call('vendor:publish', [
            '--tag' => 'command-palette-views',
            '--force' => true,
        ]);

        Notification::make()
            ->title(__('command-palette::command-palette.publish_views.notification.title'))
            ->body(__('command-palette::command-palette.publish_views.notification.body'))
            ->success()
            ->send();
    }
}
