<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Support\Providers;

use Filament\Actions\Action;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Usamamuneerchaudhary\CommandPalette\Contracts\SearchesCommands;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;
use Usamamuneerchaudhary\CommandPalette\Support\PaletteSettings;

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

                if (! $this->isSearchable($resource)) {
                    continue;
                }

                $group = $this->stringify($resource::getPluralModelLabel(), class_basename($resource));

                foreach ($this->resultsFor($resource, $query, $limit - $commands->count(), $settings) as $result) {
                    if ($commands->count() >= $limit) {
                        break 2;
                    }

                    [$title, $htmlLabel] = $this->labels($result->title ?? null);
                    $url = $result->url ?? null;

                    if (blank($title) || blank($url)) {
                        continue;
                    }

                    $details = $this->details($result);

                    $commands->push(
                        CommandItem::make(
                            label: $title,
                            url: $url,
                            group: $group,
                            icon: $this->normalizeIcon(
                                method_exists($resource, 'getNavigationIcon') ? $resource::getNavigationIcon() : null,
                                Heroicon::OutlinedDocumentText,
                            ),
                        )
                            ->id('record:'.md5($resource.'|'.$url))
                            ->description($this->summarize($details))
                            ->preview($details)
                            ->htmlLabel($htmlLabel)
                            ->resultActions($this->resultActions($result))
                            ->badge((string) __('command-palette::command-palette.badges.record'))
                    );
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $commands;
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
     * @param  class-string<\Filament\Resources\Resource>  $resource
     * @return iterable<mixed>
     */
    protected function resultsFor(string $resource, string $query, int $limit, PaletteSettings $settings): iterable
    {
        if ($this->shouldUseScout($resource, $settings)) {
            $scouted = $this->scoutResults($resource, $query, $limit);

            if ($scouted !== null) {
                return $scouted;
            }
        }

        try {
            $results = $resource::getGlobalSearchResults($query);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        if ($results instanceof Collection) {
            return $results->all();
        }

        return is_iterable($results) ? $results : [];
    }

    /**
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
     * @param  class-string<\Filament\Resources\Resource>  $resource
     * @return array<int, mixed>|null
     */
    protected function scoutResults(string $resource, string $query, int $limit): ?array
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
                return [];
            }

            $eloquent = $resource::getGlobalSearchEloquentQuery()->whereKey($keyList);

            if (method_exists($resource, 'modifyGlobalSearchQuery')) {
                $resource::modifyGlobalSearchQuery($eloquent, $query);
            }

            $order = array_flip(array_map('strval', $keyList));

            return $eloquent
                ->get()
                ->sortBy(fn (Model $record): int => $order[(string) $record->getKey()] ?? PHP_INT_MAX)
                ->values()
                ->map(function (Model $record) use ($resource): ?GlobalSearchResult {
                    $url = $resource::getGlobalSearchResultUrl($record);

                    if (blank($url)) {
                        return null;
                    }

                    $actions = $resource::getGlobalSearchResultActions($record);

                    return new GlobalSearchResult(
                        title: $resource::getGlobalSearchResultTitle($record),
                        url: $url,
                        details: $resource::getGlobalSearchResultDetails($record),
                        actions: array_map(
                            fn (Action $action): Action => $action->hasRecord() ? $action : $action->record($record),
                            $actions,
                        ),
                    );
                })
                ->filter()
                ->all();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
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
     * @return array<int, array{label: string, url: string, openInNewTab: bool}>
     */
    protected function resultActions(mixed $result): array
    {
        if (! is_object($result) || ! method_exists($result, 'getVisibleActions')) {
            return [];
        }

        try {
            $actions = $result->getVisibleActions();
        } catch (\Throwable) {
            return [];
        }

        $out = [];

        foreach ($actions as $action) {
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

            $out[] = [
                'label' => strip_tags($label),
                'url' => $url,
                'openInNewTab' => method_exists($action, 'shouldOpenUrlInNewTab') && $action->shouldOpenUrlInNewTab(),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    protected function details(mixed $result): array
    {
        $details = $result->details ?? [];

        if (! is_array($details)) {
            return [];
        }

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
