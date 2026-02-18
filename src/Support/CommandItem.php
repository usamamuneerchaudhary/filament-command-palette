<?php

namespace Usamamuneerchaudhary\CommandPalette\Support;

use Illuminate\Contracts\Support\Arrayable;

class CommandItem implements Arrayable
{
    public function __construct(
        public string $id,
        public string $label,
        public string $url,
        public string $group = 'Navigation',
        public string|\BackedEnum|null $icon = null,
        public bool $openInNewTab = false,
    ) {}

    public static function make(
        string $label,
        string $url,
        ?string $group = 'Navigation',
        string|\BackedEnum|null $icon = null,
        bool $openInNewTab = false,
    ): static {
        return new static(
            id: md5($label . $url),
            label: $label,
            url: $url,
            group: $group ?? 'Navigation',
            icon: $icon,
            openInNewTab: $openInNewTab,
        );
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'url' => $this->url,
            'group' => $this->group,
            'icon' => $this->icon,
            'openInNewTab' => $this->openInNewTab,
        ];
    }
}
