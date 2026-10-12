<?php

namespace Usamamuneerchaudhary\CommandPalette\Support\Providers;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Usamamuneerchaudhary\CommandPalette\Contracts\CommandProvider;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;
use Usamamuneerchaudhary\CommandPalette\Support\PaletteSettings;

class ActionCommandProvider implements CommandProvider
{
    public function commands(Panel $panel): iterable
    {
        $settings = PaletteSettings::for($panel);
        $enabled = $settings->array('built_in_commands', []);
        $group = (string) __('command-palette::command-palette.groups.actions');

        $commands = collect();

        if ($enabled['theme'] ?? true) {
            foreach (['light', 'dark', 'system'] as $mode) {
                $commands->push(
                    CommandItem::make(
                        label: (string) __("command-palette::command-palette.commands.theme.{$mode}"),
                        group: $group,
                        icon: match ($mode) {
                            'light' => Heroicon::OutlinedSun,
                            'dark' => Heroicon::OutlinedMoon,
                            default => Heroicon::OutlinedComputerDesktop,
                        },
                        keywords: ['theme', 'appearance', 'colour', 'color', 'contrast', $mode],
                    )
                        ->id('action:theme:'.$mode)
                        ->dispatch('command-palette-set-theme', ['theme' => $mode])
                );
            }
        }

        if (($enabled['profile'] ?? true) && filled($url = $this->profileUrl($panel))) {
            $commands->push(
                CommandItem::make(
                    label: (string) __('command-palette::command-palette.commands.profile'),
                    url: $url,
                    group: $group,
                    icon: Heroicon::OutlinedUserCircle,
                    keywords: ['account', 'settings', 'me'],
                )->id('action:profile')
            );
        }

        if ($enabled['panels'] ?? false) {
            $commands = $commands->merge($this->panelCommands($panel, $group));
        }

        if (($enabled['tenants'] ?? false) && $panel->hasTenancy()) {
            $commands = $commands->merge($this->tenantCommands($panel, $group));
        }

        if ($enabled['logout'] ?? true) {
            $commands->push(
                CommandItem::make(
                    label: (string) __('command-palette::command-palette.commands.logout'),
                    group: $group,
                    icon: Heroicon::OutlinedArrowLeftOnRectangle,
                    keywords: ['sign out', 'exit', 'leave'],
                )
                    ->id('action:logout')
                    ->priority(-10)
                    ->authorize(fn (): bool => Filament::auth()->check())
                    ->action(function () use ($panel): string {
                        Filament::auth()->logout();

                        session()->invalidate();
                        session()->regenerateToken();

                        return $panel->getLoginUrl() ?? url('/');
                    })
            );
        }

        return $commands;
    }

    protected function profileUrl(Panel $panel): ?string
    {
        try {
            return $panel->getProfileUrl();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return Collection<int, CommandItem>
     */
    protected function panelCommands(Panel $current, string $group): Collection
    {
        $commands = collect();

        try {
            foreach (Filament::getPanels() as $panel) {
                if ($panel->getId() === $current->getId()) {
                    continue;
                }

                $url = $panel->getUrl();

                if (blank($url)) {
                    continue;
                }

                $commands->push(
                    CommandItem::make(
                        label: (string) __('command-palette::command-palette.commands.switch_panel', [
                            'panel' => str($panel->getId())->headline()->toString(),
                        ]),
                        url: $url,
                        group: $group,
                        icon: Heroicon::OutlinedSquares2x2,
                        keywords: ['panel', 'switch', $panel->getId()],
                    )->id('action:panel:'.$panel->getId())
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $commands;
    }

    /**
     * @return Collection<int, CommandItem>
     */
    protected function tenantCommands(Panel $panel, string $group): Collection
    {
        $commands = collect();

        try {
            $user = Filament::auth()->user();
            $current = Filament::getTenant();

            if (! $user || ! method_exists($panel, 'getTenants')) {
                return $commands;
            }

            foreach ($panel->getTenants($user) ?? [] as $tenant) {
                if ($current && $current->getKey() === $tenant->getKey()) {
                    continue;
                }

                $label = Filament::getTenantName($tenant);

                $commands->push(
                    CommandItem::make(
                        label: (string) __('command-palette::command-palette.commands.switch_tenant', [
                            'tenant' => $label,
                        ]),
                        url: Filament::getUrl($tenant),
                        group: $group,
                        icon: Heroicon::OutlinedBuildingOffice2,
                        keywords: ['tenant', 'team', 'organisation', 'organization', 'workspace', $label],
                    )->id('action:tenant:'.$tenant->getKey())
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $commands;
    }
}
