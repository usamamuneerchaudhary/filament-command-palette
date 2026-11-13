<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Support\Providers;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Usamamuneerchaudhary\CommandPalette\Contracts\HasPaletteRecordActions;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;
use Usamamuneerchaudhary\CommandPalette\Support\RecordReference;

class RecordActionProvider
{
    use NormalizesIcons;

    /**
     * @param  class-string<resource>  $resource
     * @return array<int, CommandItem>
     */
    public function commands(string $resource, Model $record): array
    {
        $commands = collect();
        $title = $this->recordTitle($resource, $record);
        $group = $this->stringify($resource::getPluralModelLabel(), class_basename($resource));
        $tenant = $this->currentTenant();

        $this->pushPage(
            $commands,
            $resource,
            $record,
            $tenant,
            'view',
            $title,
            $group,
            Heroicon::OutlinedEye,
        );

        $this->pushPage(
            $commands,
            $resource,
            $record,
            $tenant,
            'edit',
            $title,
            $group,
            Heroicon::OutlinedPencilSquare,
        );

        foreach ($this->resultActionCommands($resource, $record, $group) as $item) {
            $commands->push($item);
        }

        if ($this->can($resource, 'delete', $record)) {
            $commands->push(
                CommandItem::make(
                    label: (string) __('command-palette::command-palette.commands.delete', ['label' => $title]),
                    group: $group,
                    icon: Heroicon::OutlinedTrash,
                    keywords: ['delete', 'remove', 'destroy', $title],
                )
                    ->id(RecordReference::childId($resource, $record->getKey(), 'delete'))
                    ->filamentAction(fn (): Action => DeleteAction::make()
                        ->record($record)
                        ->recordTitle($title)
                        ->authorize(fn (): bool => $this->can($resource, 'delete', $record)))
                    ->authorize(fn (): bool => $this->can($resource, 'delete', $record))
                    ->bindRecord($record)
            );
        }

        foreach ($this->customCommands($resource, $record, $group) as $item) {
            $commands->push($item);
        }

        return $commands->values()->all();
    }

    /**
     * @param  Collection<int, CommandItem>  $commands
     * @param  class-string<resource>  $resource
     */
    protected function pushPage(
        Collection $commands,
        string $resource,
        Model $record,
        mixed $tenant,
        string $page,
        string $title,
        string $group,
        Heroicon $icon,
    ): void {
        if (! $resource::hasPage($page) || ! $this->can($resource, $page, $record)) {
            return;
        }

        $url = $this->url($resource, $page, $record, $tenant);

        if (blank($url)) {
            return;
        }

        $commands->push(
            CommandItem::make(
                label: (string) __("command-palette::command-palette.commands.{$page}", ['label' => $title]),
                url: $url,
                group: $group,
                icon: $icon,
                keywords: [$page, $title],
            )
                ->id(RecordReference::childId($resource, $record->getKey(), $page))
                ->bindRecord($record)
        );
    }

    /**
     * @param  class-string<resource>  $resource
     * @return array<int, CommandItem>
     */
    protected function resultActionCommands(string $resource, Model $record, string $group): array
    {
        if (! method_exists($resource, 'getGlobalSearchResultActions')) {
            return [];
        }

        try {
            $actions = $resource::getGlobalSearchResultActions($record);
        } catch (\Throwable) {
            return [];
        }

        $out = [];

        foreach ($actions as $action) {
            if (! $action instanceof Action) {
                continue;
            }

            try {
                if (method_exists($action, 'hasModal') && $action->hasModal()) {
                    continue;
                }

                $url = method_exists($action, 'getUrl') ? $action->getUrl() : null;
                $label = method_exists($action, 'getLabel') ? (string) $action->getLabel() : '';
            } catch (\Throwable) {
                continue;
            }

            if (! is_string($url) || $url === '' || $label === '') {
                continue;
            }

            $suffix = 'action:'.md5($label.'|'.$url);

            $out[] = CommandItem::make(
                label: strip_tags($label),
                url: $url,
                group: $group,
                icon: Heroicon::OutlinedArrowTopRightOnSquare,
                openInNewTab: method_exists($action, 'shouldOpenUrlInNewTab') && $action->shouldOpenUrlInNewTab(),
            )
                ->id(RecordReference::childId($resource, $record->getKey(), $suffix))
                ->bindRecord($record);
        }

        return $out;
    }

    /**
     * @param  class-string<resource>  $resource
     * @return array<int, CommandItem>
     */
    protected function customCommands(string $resource, Model $record, string $group): array
    {
        if (! is_subclass_of($resource, HasPaletteRecordActions::class)) {
            return [];
        }

        try {
            $items = $resource::getPaletteRecordActions($record);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        $out = [];

        foreach ($this->wrap($items) as $item) {
            if (! $item instanceof CommandItem) {
                continue;
            }

            $item->bindRecord($record);

            if ($item->group === 'Navigation') {
                $item->group($group);
            }

            $out[] = $item;
        }

        return $out;
    }

    /**
     * @return array<int, mixed>
     */
    protected function wrap(mixed $items): array
    {
        if ($items instanceof Collection) {
            return $items->all();
        }

        if ($items instanceof CommandItem) {
            return [$items];
        }

        return is_iterable($items) ? iterator_to_array($items, false) : [];
    }

    /**
     * @param  class-string<resource>  $resource
     */
    protected function can(string $resource, string $ability, Model $record): bool
    {
        $method = 'can'.ucfirst($ability);

        if (! method_exists($resource, $method)) {
            return true;
        }

        try {
            return (bool) $resource::{$method}($record);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  class-string<resource>  $resource
     */
    protected function url(string $resource, string $name, Model $record, mixed $tenant): ?string
    {
        try {
            return $resource::getUrl(
                name: $name,
                parameters: ['record' => $record],
                shouldGuessMissingParameters: true,
                tenant: $tenant,
            );
        } catch (\Throwable) {
            try {
                return $resource::getUrl($name, ['record' => $record]);
            } catch (\Throwable) {
                return null;
            }
        }
    }

    /**
     * @param  class-string<resource>  $resource
     */
    protected function recordTitle(string $resource, Model $record): string
    {
        try {
            if (method_exists($resource, 'getRecordTitle')) {
                return $this->stringify($resource::getRecordTitle($record), (string) $record->getKey());
            }
        } catch (\Throwable) {
            // Fall through to the key.
        }

        return (string) $record->getKey();
    }

    protected function currentTenant(): mixed
    {
        try {
            return Filament::getTenant();
        } catch (\Throwable) {
            return null;
        }
    }
}
