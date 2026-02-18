<?php

namespace Usamamuneerchaudhary\CommandPalette;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Usamamuneerchaudhary\CommandPalette\Filament\Pages\PublishCommandPaletteViewsPage;
use Usamamuneerchaudhary\CommandPalette\Livewire\CommandPalette;

class FilamentCommandPalettePlugin implements Plugin
{
    public function getId(): string
    {
        return 'command-palette';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->livewireComponents([
                CommandPalette::class,
            ])
            ->renderHook(PanelsRenderHook::BODY_END, function () use ($panel): string {
                return view('command-palette::hooks.body-end', ['panel' => $panel])->render();
            });

        if (config('command-palette.include_publish_views_command', true)) {
            $panel->pages([
                PublishCommandPaletteViewsPage::class,
            ]);
        }

        if (config('command-palette.show_topbar_button', true)) {
            $panel->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, function (): string {
                return view('command-palette::hooks.topbar-trigger')->render();
            });
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return new static();
    }
}
