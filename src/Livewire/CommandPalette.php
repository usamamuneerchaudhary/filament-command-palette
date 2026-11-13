<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Livewire;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\MessageBag as MessageBagContract;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\MessageBag;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Usamamuneerchaudhary\CommandPalette\Events\CommandExecuted;
use Usamamuneerchaudhary\CommandPalette\Events\CommandPaletteOpened;
use Usamamuneerchaudhary\CommandPalette\Exceptions\CommandNotAuthorized;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;
use Usamamuneerchaudhary\CommandPalette\Support\CommandRegistry;
use Usamamuneerchaudhary\CommandPalette\Support\PaletteContext;
use Usamamuneerchaudhary\CommandPalette\Support\PaletteSettings;
use Usamamuneerchaudhary\CommandPalette\Support\PanelAccess;
use Usamamuneerchaudhary\CommandPalette\Support\RecordReference;

/**
 * The palette ships an empty shell with the page and fetches its commands the
 * first time someone opens it.
 */
class CommandPalette extends Component
{
    /** @var array<int, string> */
    public const TEMPLATES = ['standard', 'groups', 'preview'];

    #[Locked]
    public ?string $panelId = null;

    #[Locked]
    public bool $opened = false;

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
     * Loads cached commands and merges commands for the current page.
     *
     * @return array<string, mixed>
     */
    #[Renderless]
    public function loadCommands(?string $pageClass = null, mixed $recordKey = null): array
    {
        $panel = $this->requirePanel();
        $context = PaletteContext::resolve($panel, $pageClass, $recordKey);

        if (! $this->opened) {
            CommandPaletteOpened::dispatch($panel->getId(), Filament::auth()->user());
            $this->opened = true;
        }

        $payload = $this->registry()->rememberClient(
            $panel,
            function (Collection $commands): array {
                return [
                    'commands' => $commands
                        ->map(fn (CommandItem $item): array => $item->toArray())
                        ->values()
                        ->all(),
                    'icons' => $this->iconsFor($commands),
                ];
            },
        );

        $contextual = $this->registry()->contextCommands($panel, $context);

        $payload['commands'] = array_values(array_merge(
            $contextual->map(fn (CommandItem $item): array => $item->toArray())->all(),
            $payload['commands'] ?? [],
        ));
        $payload['icons'] = array_merge(
            $payload['icons'] ?? [],
            $this->iconsFor($contextual),
        );

        return $payload;
    }

    /**
     * Loads submenu commands after the parent id is authorized.
     *
     * @return array<string, mixed>
     */
    #[Renderless]
    public function loadChildren(string $id, ?string $pageClass = null, mixed $recordKey = null): array
    {
        $panel = $this->requirePanel();
        $context = PaletteContext::resolve($panel, $pageClass, $recordKey);
        $parent = $this->registry()->find($id, $panel, null, $context);

        if (! $parent instanceof CommandItem || ! $parent->isDrillable() || ! $parent->isAuthorized()) {
            throw CommandNotAuthorized::make($id);
        }

        $children = $this->registry()->childrenOf($parent, $panel, $context);

        return [
            'commands' => $children
                ->map(fn (CommandItem $item): array => $item->toArray())
                ->values()
                ->all(),
            'icons' => $this->iconsFor($children),
        ];
    }

    /**
     * Searches records after the query passes the length and rate limits.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Renderless]
    public function searchRecords(string $query, ?string $scope = null): array
    {
        $panel = $this->requirePanel();
        $settings = PaletteSettings::for($panel);
        $query = trim($query);
        $minCharacters = $settings->recordSearchMinCharacters();
        $maxCharacters = $settings->recordSearchMaxCharacters();

        if (mb_strlen($query) < $minCharacters) {
            return [];
        }

        if (mb_strlen($query) > $maxCharacters) {
            $query = mb_substr($query, 0, $maxCharacters);
        }

        $rateLimitKey = $this->recordSearchRateLimitKey($panel);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $settings->recordSearchMaxAttemptsPerMinute())) {
            return [];
        }

        RateLimiter::hit($rateLimitKey, 60);

        $scope = $this->normalizedResourceScope($scope, $panel);

        if ($scope !== null) {
            return $this->registry()->searchWithin($scope, $query, $panel)
                ->map(fn (CommandItem $item): array => $item->toArray())
                ->values()
                ->all();
        }

        return $this->registry()->searchPayload($query, $panel);
    }

    /**
     * Runs a command by id, resolving the url and authorization on the server.
     *
     * @return array<string, mixed>|null
     */
    public function runCommand(
        string $id,
        ?string $query = null,
        ?string $pageClass = null,
        mixed $recordKey = null,
        ?string $parentId = null,
    ): mixed {
        $panel = $this->requirePanel();
        $context = PaletteContext::resolve($panel, $pageClass, $recordKey);
        $command = $this->registry()->find($id, $panel, $query, $context, $parentId);

        if (! $command instanceof CommandItem || ! $command->isAuthorized()) {
            throw CommandNotAuthorized::make($id);
        }

        if ($command->needsRecord() && $command->getBoundRecord() === null) {
            $command->bindRecord($context->record());
        }

        if ($command->needsRecord() && $command->getBoundRecord() === null) {
            throw CommandNotAuthorized::make($id);
        }

        CommandExecuted::dispatch(
            $command->toArray(),
            $panel->getId(),
            $query,
            Filament::auth()->id(),
        );

        if ($command->getType() === CommandItem::TYPE_MOUNT_ACTION) {
            $name = $command->getMountAction();

            if (blank($name)) {
                throw CommandNotAuthorized::make($id);
            }

            $arguments = $command->getMountActionArguments();

            $this->dispatch('filament-command-palette:trigger-action', name: $name, arguments: $arguments);

            return [
                'mountAction' => $name,
                'arguments' => $arguments,
            ];
        }

        if ($command->getType() === CommandItem::TYPE_SUBMENU) {
            return null;
        }

        $action = $command->getAction();

        if ($action === null) {
            return filled($command->url)
                ? $this->redirect($command->url, navigate: ! $command->openInNewTab)
                : null;
        }

        $result = $command->execute($command->getBoundRecord() ?? $context->record());

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
                'currentPageGroup' => __('command-palette::command-palette.groups.current_page'),
                'chordsEnabled' => $settings->bool('chords.enabled', true),
                'chordTimeout' => max(300, $settings->int('chords.timeout', 1100)),
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
     * @param  Collection<int, CommandItem>  $commands
     * @return array<string, string>
     */
    protected function iconsFor(Collection $commands): array
    {
        $icons = [];

        foreach ($commands as $command) {
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

    protected function requirePanel(): Panel
    {
        $panel = $this->panel();

        if (! $panel instanceof Panel) {
            throw CommandNotAuthorized::make('panel');
        }

        return $panel;
    }

    protected function panel(): ?Panel
    {
        $panel = $this->resolveRequestedPanel();

        if (! $panel instanceof Panel || ! PanelAccess::check($panel)) {
            return null;
        }

        return $panel;
    }

    /**
     * The panel the request is actually serving wins over a client-supplied id.
     * `$panelId` is locked, but current-panel resolution is the real boundary.
     */
    protected function resolveRequestedPanel(): ?Panel
    {
        try {
            $current = Filament::getCurrentPanel();

            if ($current instanceof Panel) {
                return $current;
            }
        } catch (\Throwable) {
            // Fall through to the locked id, then the default panel.
        }

        try {
            if (filled($this->panelId)) {
                $fromId = Filament::getPanel($this->panelId, isStrict: false);

                if ($fromId instanceof Panel) {
                    return $fromId;
                }
            }

            return Filament::getCurrentOrDefaultPanel();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Accepts a resource hash from the current panel, or null.
     */
    protected function normalizedResourceScope(mixed $scope, Panel $panel): ?string
    {
        if (! is_string($scope) || $scope === '') {
            return null;
        }

        if (strlen($scope) !== RecordReference::HASH_LENGTH || ! ctype_xdigit($scope)) {
            return null;
        }

        return RecordReference::resourceByHash($panel, strtolower($scope)) === null
            ? null
            : strtolower($scope);
    }

    protected function recordSearchRateLimitKey(Panel $panel): string
    {
        $userKey = 'guest';

        try {
            $userKey = (string) (Filament::auth()->id() ?? 'guest');
        } catch (\Throwable) {
            // Auth may not be resolvable outside a request.
        }

        return implode(':', [
            'command-palette',
            'search',
            $panel->getId(),
            $userKey,
        ]);
    }

    public static function resolveTemplateName(mixed $template): string
    {
        return is_string($template) && in_array($template, self::TEMPLATES, true)
            ? $template
            : 'standard';
    }
}
