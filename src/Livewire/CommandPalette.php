<?php

namespace Usamamuneerchaudhary\CommandPalette\Livewire;

use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;
use Usamamuneerchaudhary\CommandPalette\Support\CommandRegistry;

class CommandPalette extends Component
{
    public ?string $panelId = null;

    public function mount(?string $panelId = null): void
    {
        $this->panelId = $panelId;
    }

    public function render(): View
    {
        $panel = $this->panelId
            ? Filament::getPanel($this->panelId, isStrict: false)
            : Filament::getCurrentOrDefaultPanel();

        $commands = collect();
        try {
            $commands = app(CommandRegistry::class)->getAllCommands($panel);
        } catch (\Throwable $e) {
            report($e);
        }

        $commandsJson = $commands->map(function (CommandItem $item): array {
            $icon = $item->icon ?? \Filament\Support\Icons\Heroicon::OutlinedArrowTopRightOnSquare;
            $iconHtml = \Filament\Support\generate_icon_html($icon, size: \Filament\Support\Enums\IconSize::ExtraSmall);
            $iconHtmlString = '';
            if ($iconHtml !== null) {
                $iconHtmlString = $iconHtml instanceof \Illuminate\Contracts\Support\Htmlable
                    ? $iconHtml->toHtml()
                    : (string) $iconHtml;
            }

            return [
                'id' => $item->id,
                'label' => $item->label,
                'url' => $item->url,
                'group' => $item->group,
                'iconHtml' => $iconHtmlString,
                'openInNewTab' => $item->openInNewTab,
            ];
        })->values()->all();

        return view('command-palette::livewire.command-palette', [
            'commands' => $commandsJson,
            'commandsBase64' => base64_encode(json_encode($commandsJson)),
            'maxResults' => config('command-palette.max_results', 5),
        ]);
    }
}
