<?php

namespace Usamamuneerchaudhary\CommandPalette\Livewire;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\MessageBag as MessageBagContract;
use Illuminate\Contracts\View\View;
use Illuminate\Support\MessageBag;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Usamamuneerchaudhary\CommandPalette\Events\CommandExecuted;
use Usamamuneerchaudhary\CommandPalette\Events\CommandPaletteOpened;
use Usamamuneerchaudhary\CommandPalette\Exceptions\CommandNotAuthorized;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;
use Usamamuneerchaudhary\CommandPalette\Support\CommandRegistry;
use Usamamuneerchaudhary\CommandPalette\Support\PaletteSettings;

/**
 * The palette ships an empty shell with the page and fetches its commands the
 * first time someone opens it.
 *
 * v1 base64-encoded the full command list — with pre-rendered icon markup —
 * into every single page response, for a UI most people open occasionally.
 */
class CommandPalette extends Component
{
    /** @var array<int, string> */
    public const TEMPLATES = ['standard', 'groups', 'preview'];

    public ?string $panelId = null;

    public function mount(?string $panelId = null): void
    {
        $this->panelId = $panelId;
    }

    /**
     * Livewire shares this bag with Blade during render. If the underlying
     * store has no bag (or a non-MessageBag value), `ViewErrorBag::put()`
     * type-errors. Always hand back a real message bag.
     */
    public function getErrorBag(): MessageBag
    {
        try {
            $bag = parent::getErrorBag();
        } catch (\Throwable) {
            $bag = null;
        }

        if (! $bag instanceof MessageBagContract) {
            $bag = new MessageBag;
            $this->setErrorBag($bag);
        }

        return $bag instanceof MessageBag
            ? $bag
            : new MessageBag($bag->toArray());
    }

    /**
     * Called once, the first time the palette is opened.
     *
     * @return array<string, mixed>
     */
    #[Renderless]
    public function loadCommands(): array
    {
        $panel = $this->panel();

        CommandPaletteOpened::dispatch($this->panelId, Filament::auth()->user());

        return [
            'commands' => $this->registry()->payload($panel),
            'icons' => $this->icons($panel),
        ];
    }

    /**
     * Debounced from the client. Only providers that need a search term run
     * here — the static list is already filtered in the browser.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Renderless]
    public function searchRecords(string $query): array
    {
        $query = trim($query);
        $minCharacters = max(1, PaletteSettings::for($this->panel())->int('record_search.min_characters', 2));

        if (mb_strlen($query) < $minCharacters) {
            return [];
        }

        return $this->registry()->searchPayload($query, $this->panel());
    }

    /**
     * Run a command by id.
     *
     * The id is the only thing the client sends. Everything else — the URL, the
     * closure, whether the user may run it at all — is resolved server-side
     * from a freshly built, freshly authorized registry.
     */
    public function runCommand(string $id, ?string $query = null): mixed
    {
        $panel = $this->panel();
        $command = $this->registry()->find($id, $panel, $query);

        if (! $command instanceof CommandItem || ! $command->isAuthorized()) {
            throw CommandNotAuthorized::make($id);
        }

        CommandExecuted::dispatch($command, $this->panelId, $query, Filament::auth()->user());

        $action = $command->getAction();

        if ($action === null) {
            return filled($command->url)
                ? $this->redirect($command->url, navigate: ! $command->openInNewTab)
                : null;
        }

        $result = $action($command);

        if (is_string($result) && filled($result)) {
            return $this->redirect($result, navigate: true);
        }

        return null;
    }

    public function render(): View
    {
        $panel = $this->panel();
        $settings = PaletteSettings::for($panel);
        $template = $settings->template();
        $panelKey = $this->panelId ?? 'default';

        return view('command-palette::livewire.command-palette', [
            'template' => $template,
            'templateConfig' => $settings->templateCopy(),
            'placeholder' => $settings->get('placeholder')
                ?: __('command-palette::command-palette.placeholder'),
            'mousetrapBindings' => $this->mousetrapBindings($settings),
            'paletteConfig' => [
                'id' => 'fi-cp-'.md5($panelKey.$template),
                'panelKey' => $panelKey,
                'maxResults' => $settings->maxResults(),
                'recordSearch' => $settings->bool('record_search.enabled', true),
                'recentsEnabled' => $settings->bool('recents.enabled', true),
                'maxRecents' => max(1, $settings->int('recents.max', 5)),
                'modesEnabled' => $settings->bool('modes.enabled', true),
                'debounce' => max(0, $settings->int('record_search.debounce', 300)),
                'minCharacters' => max(1, $settings->int('record_search.min_characters', 2)),
                'spa' => $this->usesSpa($panel),
                'recentsGroup' => __('command-palette::command-palette.groups.recents'),
                'actionsGroup' => __('command-palette::command-palette.groups.actions'),
                'previewGroup' => __('command-palette::command-palette.preview.group'),
                'previewUrl' => __('command-palette::command-palette.preview.url'),
            ],
        ]);
    }

    protected function usesSpa(?Panel $panel): bool
    {
        if ($panel === null) {
            return false;
        }

        try {
            return $panel->hasSpaMode();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function mousetrapBindings(PaletteSettings $settings): string
    {
        return collect($settings->keyBindings())
            ->map(fn (string $key): string => str_replace('+', '-', trim($key)))
            ->filter()
            ->implode('.');
    }

    /**
     * Icons are rendered once, keyed by name, instead of being duplicated into
     * every row of the payload.
     *
     * The original icon value is handed to Filament rather than its string
     * value: Heroicon enums resolve through an alias layer that a bare string
     * does not always reach, which is why only some icons rendered.
     *
     * @return array<string, string>
     */
    protected function icons(?Panel $panel): array
    {
        $icons = [];

        foreach ($this->registry()->all($panel) as $command) {
            $icon = $command->icon;

            if ($icon === null) {
                continue;
            }

            $name = $icon instanceof \BackedEnum ? (string) $icon->value : (string) $icon;

            if ($name === '' || isset($icons[$name])) {
                continue;
            }

            $icons[$name] = $this->renderIcon($icon);
        }

        $icons['__fallback'] = $this->renderIcon(Heroicon::OutlinedArrowTopRightOnSquare);

        return array_filter($icons);
    }

    protected function renderIcon(string|\BackedEnum $icon): string
    {
        try {
            $html = \Filament\Support\generate_icon_html(
                $icon,
                size: IconSize::ExtraSmall,
            );
        } catch (\Throwable) {
            return '';
        }

        if ($html === null) {
            return '';
        }

        return $html instanceof Htmlable
            ? $html->toHtml()
            : (string) $html;
    }

    protected function registry(): CommandRegistry
    {
        return app(CommandRegistry::class);
    }

    protected function panel(): ?Panel
    {
        try {
            return $this->panelId
                ? Filament::getPanel($this->panelId, isStrict: false)
                : Filament::getCurrentOrDefaultPanel();
        } catch (\Throwable) {
            try {
                return Filament::getCurrentOrDefaultPanel();
            } catch (\Throwable) {
                return null;
            }
        }
    }

    public static function resolveTemplateName(mixed $template): string
    {
        return is_string($template) && in_array($template, self::TEMPLATES, true)
            ? $template
            : 'standard';
    }
}
