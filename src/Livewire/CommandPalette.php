<?php

namespace Usamamuneerchaudhary\CommandPalette\Livewire;

use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;
use Usamamuneerchaudhary\CommandPalette\Support\CommandRegistry;

class CommandPalette extends Component
{
    /**
     * @var list<string>
     */
    public const TEMPLATES = ['standard', 'groups', 'preview'];

    public ?string $panelId = null;

    public function mount(?string $panelId = null): void
    {
        $this->panelId = $panelId;
    }

    protected function resolvePanel(string $panelId): ?\Filament\Panel
    {
        try {
            return Filament::getPanel($panelId, isStrict: false);
        } catch (\ArgumentError $e) {
            return Filament::getPanel($panelId);
        }
    }

    public function resolveTemplate(): string
    {
        return self::resolveTemplateName(config('command-palette.template', 'standard'));
    }

    public static function resolveTemplateName(mixed $template): string
    {
        if (! is_string($template) || ! in_array($template, self::TEMPLATES, true)) {
            return 'standard';
        }

        return $template;
    }

    public function render(): View
    {
        $panel = $this->panelId
            ? $this->resolvePanel($this->panelId)
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
                'description' => $item->description,
                'preview' => $item->preview,
            ];
        })->values()->all();

        $template = $this->resolveTemplate();

        $mousetrapBindings = collect(config('command-palette.key_bindings', ['mod+k']))
            ->map(fn (string $key): string => str_replace('+', '-', $key))
            ->implode('.');

        return view('command-palette::livewire.command-palette', [
            'commands' => $commandsJson,
            'commandsBase64' => base64_encode(json_encode($commandsJson)),
            'maxResults' => config('command-palette.max_results', 5),
            'template' => $template,
            'templateConfig' => config("command-palette.templates.{$template}", []),
            'mousetrapBindings' => $mousetrapBindings,
        ]);
    }
}
