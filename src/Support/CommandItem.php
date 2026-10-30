<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Support;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CommandItem implements Arrayable
{
    public const string TYPE_LINK = 'link';

    public const string TYPE_ACTION = 'action';

    public const string TYPE_EVENT = 'event';

    public const string TYPE_MOUNT_ACTION = 'mountAction';

    public const string TYPE_SUBMENU = 'submenu';

    public const int MAX_SUBMENU_DEPTH = 8;

    public string $label;

    public ?string $url = null;

    public string $group = 'Navigation';

    public string|\BackedEnum|null $icon = null;

    public bool $openInNewTab = false;

    public ?string $description = null;

    /** @var array<int, string> */
    public array $keywords = [];

    /** @var array<string, string>|null */
    public ?array $preview = null;

    /**
     * Higher sorts earlier when scores tie. Use sparingly — frecency already
     * does most of the ordering work for the people using the palette.
     */
    public int $priority = 0;

    public ?string $badge = null;

    protected ?string $id = null;

    protected Closure|string|null $action = null;

    protected ?string $event = null;

    /** @var array<string, mixed> */
    protected array $eventPayload = [];

    protected Closure|bool $visible = true;

    protected Closure|bool $authorize = true;

    protected ?string $htmlLabel = null;

    /** @var array<int, array{label: string, url: string, openInNewTab: bool}> */
    protected array $resultActions = [];

    protected ?string $mountAction = null;

    /** @var array<string, mixed> */
    protected array $mountActionArguments = [];

    protected Closure|array|null $children = null;

    protected ?string $chord = null;

    protected bool $requiresRecord = false;

    protected bool $requiresTableSelection = false;

    protected bool $context = false;

    protected int $submenuDepth = 0;

    protected mixed $boundRecord = null;

    public function __construct(string $label)
    {
        $this->label = $label;
    }

    /**
     * @param  array<int, string>  $keywords
     */
    public static function make(
        string $label,
        ?string $url = null,
        ?string $group = 'Navigation',
        string|\BackedEnum|null $icon = null,
        bool $openInNewTab = false,
        array $keywords = [],
    ): static {
        $item = new static($label);

        $item->url = $url;
        $item->group = $group ?? 'Navigation';
        $item->icon = $icon;
        $item->openInNewTab = $openInNewTab;
        $item->keywords = $keywords;

        return $item;
    }

    public function id(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): string
    {
        return $this->id ??= 'cmd_'.substr(
            hash('xxh128', $this->group.'|'.$this->label.'|'.($this->url ?? 'action')),
            0,
            16,
        );
    }

    public function url(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function group(string $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function icon(string|\BackedEnum|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function openInNewTab(bool $openInNewTab = true): static
    {
        $this->openInNewTab = $openInNewTab;

        return $this;
    }

    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Extra terms that match this command without appearing in its label.
     *
     * Useful for synonyms ("new client" finding "Create customer") and for
     * keeping search usable once labels are translated.
     *
     * @param  array<int, string>|string  $keywords
     */
    public function keywords(array|string $keywords): static
    {
        $this->keywords = array_values(array_filter(
            array_map('strval', (array) $keywords),
        ));

        return $this;
    }

    public function badge(?string $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    public function priority(int $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * @param  array<string, string>  $preview
     */
    public function preview(array $preview): static
    {
        $this->preview = $preview;

        return $this;
    }

    /**
     * Sanitized HTML shown in the preview pane. The plain `label` is still
     * used for fuzzy matching and list highlighting.
     */
    public function htmlLabel(?string $html): static
    {
        $this->htmlLabel = $html === null ? null : SafeHtml::label($html);

        return $this;
    }

    public function getHtmlLabel(): ?string
    {
        return $this->htmlLabel;
    }

    /**
     * @param  array<int, array{label: string, url: string, openInNewTab?: bool}>  $actions
     */
    public function resultActions(array $actions): static
    {
        $this->resultActions = [];

        foreach ($actions as $action) {
            $url = $action['url'] ?? null;
            $label = $action['label'] ?? null;

            if (! is_string($url) || $url === '' || ! is_string($label) || $label === '') {
                continue;
            }

            $this->resultActions[] = [
                'label' => $label,
                'url' => $url,
                'openInNewTab' => (bool) ($action['openInNewTab'] ?? false),
            ];
        }

        return $this;
    }

    /**
     * Run a closure or invokable class instead of navigating. Return a string
     * to redirect once the action has finished.
     *
     * Privileged work must also call `authorize()`. The default allows any
     * user who can open this panel; that is not enough for destructive actions.
     *
     * Closures may accept the command as the first argument and the active
     * Eloquent record as the second. Class names are resolved from the
     * container and invoked the same way, so they survive `config:cache`.
     *
     * @param  Closure|class-string|null  $action
     */
    public function action(Closure|string|null $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getAction(): Closure|string|null
    {
        return $this->action;
    }

    /**
     * Ask the host Filament page to `mountAction()` after this command is
     * authorized. The palette never renders the modal itself.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function mountAction(string $name, array $arguments = []): static
    {
        $this->mountAction = $name;
        $this->mountActionArguments = $arguments;

        return $this;
    }

    public function getMountAction(): ?string
    {
        return $this->mountAction;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMountActionArguments(): array
    {
        return $this->mountActionArguments;
    }

    /**
     * Nested options shown after this command is selected. Closures are
     * resolved on the server; the client only ever receives `hasChildren`.
     *
     * @param  Closure(): (iterable<int, self>|Collection<int, self>)|array<int, self>|null  $children
     */
    public function children(Closure|array|null $children): static
    {
        $this->children = $children;

        return $this;
    }

    public function hasChildren(): bool
    {
        return $this->children !== null && $this->children !== [];
    }

    /**
     * @return array<int, self>
     */
    public function resolveChildren(): array
    {
        if ($this->children instanceof Closure) {
            try {
                $result = ($this->children)($this);
            } catch (\Throwable $e) {
                report($e);

                return [];
            }
        } else {
            $result = $this->children ?? [];
        }

        if ($result instanceof Collection) {
            $result = $result->all();
        }

        if (! is_array($result)) {
            return [];
        }

        return array_values(array_filter(
            $result,
            fn (mixed $item): bool => $item instanceof self,
        ));
    }

    public function submenuDepth(int $depth): static
    {
        $this->submenuDepth = max(0, $depth);

        return $this;
    }

    public function getSubmenuDepth(): int
    {
        return $this->submenuDepth;
    }

    /**
     * Two-stroke shortcut, written as `"g c"`. Ignored while typing in a field.
     */
    public function chord(?string $chord): static
    {
        $this->chord = self::normalizeChord($chord);

        return $this;
    }

    public function getChord(): ?string
    {
        return $this->chord;
    }

    public static function normalizeChord(?string $chord): ?string
    {
        if ($chord === null) {
            return null;
        }

        $parts = preg_split('/[\s+]+/', strtolower(trim($chord))) ?: [];
        $parts = array_values(array_filter($parts, fn (string $part): bool => $part !== ''));

        if (count($parts) !== 2) {
            return null;
        }

        return $parts[0].' '.$parts[1];
    }

    public function requiresRecord(bool $condition = true): static
    {
        $this->requiresRecord = $condition;

        return $this;
    }

    public function requiresTableSelection(bool $condition = true): static
    {
        $this->requiresTableSelection = $condition;

        return $this;
    }

    public function context(bool $condition = true): static
    {
        $this->context = $condition;

        return $this;
    }

    public function isContext(): bool
    {
        return $this->context;
    }

    public function bindRecord(mixed $record): static
    {
        $this->boundRecord = $record;

        return $this;
    }

    public function getBoundRecord(): mixed
    {
        return $this->boundRecord;
    }

    /**
     * Fire a browser event instead of hitting the server. The right fit for
     * anything that lives purely in the client — theme switching, opening a
     * drawer, focusing a field.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $event, array $payload = []): static
    {
        $this->event = $event;
        $this->eventPayload = $payload;

        return $this;
    }

    public function getType(): string
    {
        return match (true) {
            $this->hasChildren() => self::TYPE_SUBMENU,
            $this->mountAction !== null => self::TYPE_MOUNT_ACTION,
            $this->event !== null => self::TYPE_EVENT,
            $this->action !== null => self::TYPE_ACTION,
            default => self::TYPE_LINK,
        };
    }

    public function visible(Closure|bool $condition = true): static
    {
        $this->visible = $condition;

        return $this;
    }

    public function hidden(Closure|bool $condition = true): static
    {
        $this->visible = is_bool($condition)
            ? ! $condition
            : fn (): bool => ! (bool) $condition($this);

        return $this;
    }

    /**
     * Checked when the palette is built and again immediately before an action
     * runs, so a stale client payload can never execute anything.
     */
    public function authorize(Closure|bool $condition = true): static
    {
        $this->authorize = $condition;

        return $this;
    }

    public function isVisible(): bool
    {
        if (! $this->evaluate($this->visible)) {
            return false;
        }

        return $this->isAuthorized();
    }

    public function isAuthorized(): bool
    {
        return $this->evaluate($this->authorize);
    }

    public function needsRecord(): bool
    {
        if ($this->requiresRecord) {
            return true;
        }

        return $this->typedRecordParameterIsRequired($this->action)
            || $this->typedRecordParameterIsRequired($this->authorize)
            || $this->typedRecordParameterIsRequired($this->visible);
    }

    /**
     * Run the server action. Closures and invokable classes both land here.
     */
    public function execute(mixed $record = null): mixed
    {
        $record ??= $this->boundRecord;
        $action = $this->action;

        if (is_string($action)) {
            if (! class_exists($action)) {
                return null;
            }

            $instance = app($action);

            if (! is_callable($instance)) {
                return null;
            }

            return $this->callInvokable($instance, $record);
        }

        if (! $action instanceof Closure) {
            return null;
        }

        return $this->callInvokable($action, $record);
    }

    /**
     * Everything the browser needs and nothing it does not. Closures never
     * cross this boundary.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'type' => $this->getType(),
            'label' => $this->label,
            'url' => $this->url,
            'group' => $this->group,
            'icon' => $this->icon instanceof \BackedEnum ? $this->icon->value : $this->icon,
            'openInNewTab' => $this->openInNewTab,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'badge' => $this->badge,
            'priority' => $this->priority,
            'preview' => $this->preview,
            'event' => $this->event,
            'eventPayload' => (object) $this->eventPayload,
            'hasChildren' => $this->hasChildren(),
            'chord' => $this->chord,
            'htmlLabel' => $this->htmlLabel,
            'resultActions' => $this->resultActions,
            'mountAction' => $this->mountAction,
            'mountActionArguments' => (object) $this->mountActionArguments,
            'requiresTableSelection' => $this->requiresTableSelection,
            'context' => $this->context,
        ];
    }

    protected function evaluate(Closure|bool $condition): bool
    {
        if (is_bool($condition)) {
            return $condition;
        }

        try {
            return (bool) $this->callInvokable($condition, $this->boundRecord);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    protected function callInvokable(callable $action, mixed $record): mixed
    {
        $reflection = $action instanceof Closure
            ? new \ReflectionFunction($action)
            : new \ReflectionMethod($action, '__invoke');

        $count = $reflection->getNumberOfParameters();

        return match (true) {
            $count >= 2 => $action($this, $record),
            $count === 1 => $action($this),
            default => $action(),
        };
    }

    protected function typedRecordParameterIsRequired(mixed $callback): bool
    {
        if ($callback instanceof Closure) {
            $parameters = (new \ReflectionFunction($callback))->getParameters();
        } elseif (is_string($callback) && class_exists($callback) && method_exists($callback, '__invoke')) {
            $parameters = (new \ReflectionMethod($callback, '__invoke'))->getParameters();
        } else {
            return false;
        }

        $parameter = $parameters[1] ?? null;

        if ($parameter === null || $parameter->allowsNull() || $parameter->isDefaultValueAvailable()) {
            return false;
        }

        $type = $parameter->getType();

        if (! $type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return false;
        }

        return is_a($type->getName(), Model::class, true);
    }
}
