<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Support\Providers;

use Filament\Actions\Action;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Usamamuneerchaudhary\CommandPalette\Contracts\SearchesCommands;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;
use Usamamuneerchaudhary\CommandPalette\Support\PaletteSettings;
use Usamamuneerchaudhary\CommandPalette\Support\RecordReference;

class RecordCommandProvider implements SearchesCommands
{
    use NormalizesIcons;

    public function search(Panel $panel, string $query, int $limit): iterable
    {
        $commands = collect();
        $settings = PaletteSettings::for($panel);
        $minCharacters = $settings->recordSearchMinCharacters();

        if (mb_strlen($query) < $minCharacters) {
            return $commands;
        }

        try {
            foreach ($panel->getResources() as $resource) {
                if ($commands->count() >= $limit) {
                    break;
                }

                if (! is_string($resource) || ! is_subclass_of($resource, Resource::class)) {
                    continue;
                }

                $commands = $commands->merge(
                    $this->searchResource($panel, $resource, $query, $limit - $commands->count())
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $commands;
    }

    /**
     * @param  class-string<resource>  $resource
     * @return Collection<int, CommandItem>
     */
    public function searchResource(Panel $panel, string $resource, string $query, int $limit): Collection
    {
        $commands = collect();
        $settings = PaletteSettings::for($panel);

        if ($limit < 1 || ! $this->isSearchable($resource)) {
            return $commands;
        }

        foreach ($this->recordsFor($resource, $query, $limit, $settings) as $record) {
            if ($commands->count() >= $limit) {
                break;
            }

            $item = $this->commandFor($resource, $record);

            if ($item instanceof CommandItem) {
                $commands->push($item);
            }
        }

        return $commands;
    }

    /**
     * Builds a drillable record command, or null when the record has no URL.
     *
     * @param  class-string<resource>  $resource
     */
    public function commandFor(string $resource, Model $record): ?CommandItem
    {
        $key = $record->getKey();

        if (! is_int($key) && ! is_string($key)) {
            return null;
        }

        try {
            $url = $resource::getGlobalSearchResultUrl($record);
            $title = $resource::getGlobalSearchResultTitle($record);
            $details = $this->detailsFromArray($resource::getGlobalSearchResultDetails($record));
            $actions = method_exists($resource, 'getGlobalSearchResultActions')
                ? $resource::getGlobalSearchResultActions($record)
                : [];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        [$label, $htmlLabel] = $this->labels($title);

        if (blank($label) || blank($url)) {
            return null;
        }

        $group = $this->stringify($resource::getPluralModelLabel(), class_basename($resource));

        return CommandItem::make(
            label: $label,
            url: $url,
            group: $group,
            icon: $this->normalizeIcon(
                method_exists($resource, 'getNavigationIcon') ? $resource::getNavigationIcon() : null,
                Heroicon::OutlinedDocumentText,
            ),
        )
            ->id(RecordReference::id($resource, $key))
            ->description($this->summarize($details))
            ->preview($details)
            ->htmlLabel($htmlLabel)
            ->resultActions($this->resultActionsFromList(is_array($actions) ? $actions : []))
            ->badge((string) __('command-palette::command-palette.badges.record'))
            ->bindRecord($record)
            ->drillDown(fn (): array => app(RecordActionProvider::class)->commands($resource, $record));
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    protected function isSearchable(string $resource): bool
    {
        try {
            if (method_exists($resource, 'canGloballySearch') && ! $resource::canGloballySearch()) {
                return false;
            }

            if (method_exists($resource, 'canAccess') && ! $resource::canAccess()) {
                return false;
            }

            if (method_exists($resource, 'canViewAny') && ! $resource::canViewAny()) {
                return false;
            }

            return method_exists($resource, 'getGlobalSearchResults');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Prefers Scout keys when enabled, then falls back to Filament global search.
     *
     * @param  class-string<resource>  $resource
     * @return Collection<int, Model>
     */
    protected function recordsFor(string $resource, string $query, int $limit, PaletteSettings $settings): Collection
    {
        if ($this->shouldUseScout($resource, $settings)) {
            $scouted = $this->scoutRecords($resource, $query, $limit);

            if ($scouted !== null) {
                return $scouted;
            }
        }

        try {
            $builder = $resource::getGlobalSearchEloquentQuery();
            $this->constrainGlobalSearch($resource, $builder, $query);

            if (method_exists($resource, 'modifyGlobalSearchQuery')) {
                $resource::modifyGlobalSearchQuery($builder, $query);
            }

            return $builder
                ->limit(max(1, $limit))
                ->get()
                ->values();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    /**
     * @param  class-string<resource>  $resource
     */
    protected function constrainGlobalSearch(string $resource, Builder $query, string $search): void
    {
        if (! method_exists($resource, 'applyGlobalSearchAttributeConstraints')) {
            return;
        }

        $method = new \ReflectionMethod($resource, 'applyGlobalSearchAttributeConstraints');
        $method->invoke(null, $query, $search);
    }

    /**
     * Uses Scout only when it is enabled and the resource model is searchable.
     *
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    protected function shouldUseScout(string $resource, PaletteSettings $settings): bool
    {
        if (! $settings->bool('scout.enabled', false)) {
            return false;
        }

        $trait = 'Laravel\\Scout\\Searchable';

        if (! trait_exists($trait)) {
            return false;
        }

        try {
            $model = $resource::getModel();
        } catch (\Throwable) {
            return false;
        }

        return in_array($trait, class_uses_recursive($model), true);
    }

    /**
     * Looks up Scout keys, then reloads the models through Filament's query.
     *
     * @param  class-string<resource>  $resource
     * @return Collection<int, Model>|null
     */
    protected function scoutRecords(string $resource, string $query, int $limit): ?Collection
    {
        try {
            $model = $resource::getModel();

            if (! method_exists($model, 'search')) {
                return null;
            }

            $builder = $model::{'search'}($query);

            if (method_exists($builder, 'take')) {
                $builder->take(max(1, $limit));
            }

            $keys = method_exists($builder, 'keys') ? $builder->keys() : collect();
            $keyList = $keys instanceof Collection ? $keys->all() : (array) $keys;

            if ($keyList === []) {
                return collect();
            }

            $eloquent = $resource::getGlobalSearchEloquentQuery()->whereKey($keyList);

            if (method_exists($resource, 'modifyGlobalSearchQuery')) {
                $resource::modifyGlobalSearchQuery($eloquent, $query);
            }

            $order = array_flip(array_map('strval', $keyList));

            return $eloquent
                ->get()
                ->sortBy(fn (Model $record): int => $order[(string) $record->getKey()] ?? PHP_INT_MAX)
                ->values();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Splits an Htmlable title into plain text and HTML for the preview pane.
     *
     * @return array{0: string, 1: ?string}
     */
    protected function labels(mixed $title): array
    {
        if ($title instanceof Htmlable) {
            $html = $title->toHtml();

            return [
                trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                $html,
            ];
        }

        return [$this->stringify($title), null];
    }

    /**
     * Keeps visible global-search actions that navigate by URL.
     *
     * @param  array<int, mixed>  $actions
     * @return array<int, array{label: string, url: string, openInNewTab: bool}>
     */
    protected function resultActionsFromList(array $actions): array
    {
        $out = [];

        foreach ($actions as $action) {
            if (! $action instanceof Action) {
                continue;
            }

            try {
                if (method_exists($action, 'isVisible') && ! $action->isVisible()) {
                    continue;
                }

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

            $out[] = [
                'label' => strip_tags($label),
                'url' => $url,
                'openInNewTab' => method_exists($action, 'shouldOpenUrlInNewTab') && $action->shouldOpenUrlInNewTab(),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, string>
     */
    protected function detailsFromArray(array $details): array
    {
        $clean = [];

        foreach ($details as $key => $value) {
            $value = $this->stringify($value);

            if (filled($value)) {
                $clean[(string) $key] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, string>  $details
     */
    protected function summarize(array $details): ?string
    {
        if ($details === []) {
            return null;
        }

        return collect($details)
            ->map(fn (string $value, string $key): string => "{$key}: {$value}")
            ->take(2)
            ->implode(' · ');
    }
}
