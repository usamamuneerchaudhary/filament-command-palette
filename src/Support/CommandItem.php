<?php

namespace Usamamuneerchaudhary\CommandPalette\Support;

use Closure;
use Illuminate\Contracts\Support\Arrayable;

class CommandItem implements Arrayable
{
    public const TYPE_LINK = 'link';

    public const TYPE_ACTION = 'action';

    public const TYPE_EVENT = 'event';

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

    protected ?Closure $action = null;

    protected ?string $event = null;

    /** @var array<string, mixed> */
    protected array $eventPayload = [];

    protected Closure|bool $visible = true;

    protected Closure|bool $authorize = true;

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
     * Run a closure instead of navigating. Return a string to redirect once the
     * action has finished.
     */
    public function action(?Closure $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getAction(): ?Closure
    {
        return $this->action;
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
        $visible = $this->visible instanceof Closure
            ? ($this->visible)($this)
            : $this->visible;

        if (! $visible) {
            return false;
        }

        return $this->isAuthorized();
    }

    public function isAuthorized(): bool
    {
        return (bool) ($this->authorize instanceof Closure
            ? ($this->authorize)($this)
            : $this->authorize);
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
        ];
    }
}
