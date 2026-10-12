<?php

namespace Usamamuneerchaudhary\CommandPalette\Support\Providers;

use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Usamamuneerchaudhary\CommandPalette\Contracts\CommandProvider;
use Usamamuneerchaudhary\CommandPalette\Filament\Pages\PublishCommandPaletteViewsPage;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;
use Usamamuneerchaudhary\CommandPalette\Support\PaletteSettings;

class CustomCommandProvider implements CommandProvider
{
    public function commands(Panel $panel): iterable
    {
        $settings = PaletteSettings::for($panel);
        $commands = collect();

        foreach ($settings->plugin()?->getRegisteredCommands() ?? [] as $callback) {
            $commands = $commands->merge($this->resolve($callback, $panel));
        }

        foreach ((array) config('command-palette.custom_commands', []) as $callback) {
            $commands = $commands->merge($this->resolve($callback, $panel));
        }

        if ($settings->bool('include_publish_views_command', true)) {
            $commands->push($this->publishViewsCommand($panel));
        }

        return $commands->filter(fn (mixed $item): bool => $item instanceof CommandItem);
    }

    /**
     * @return array<int, mixed>
     */
    protected function resolve(mixed $callback, Panel $panel): array
    {
        if ($callback instanceof CommandItem) {
            return [$callback];
        }

        if (! is_callable($callback)) {
            return [];
        }

        try {
            $result = $callback($panel);
        } catch (\Throwable $e) {
            // One broken custom command should never take the palette down.
            report($e);

            return [];
        }

        if ($result instanceof CommandItem) {
            return [$result];
        }

        if ($result instanceof Collection) {
            return $result->all();
        }

        return is_array($result) ? $result : [];
    }

    protected function publishViewsCommand(Panel $panel): CommandItem
    {
        return CommandItem::make(
            label: (string) __('command-palette::command-palette.commands.publish_views'),
            url: PublishCommandPaletteViewsPage::getUrl(panel: $panel->getId()),
            group: (string) __('command-palette::command-palette.groups.command_palette'),
            icon: Heroicon::OutlinedArrowUpTray,
            keywords: ['customise', 'customize', 'views', 'blade', 'theme'],
        )
            ->id('command-palette:publish-views')
            ->priority(-20)
            ->authorize(fn (): bool => PublishCommandPaletteViewsPage::canAccess());
    }
}
