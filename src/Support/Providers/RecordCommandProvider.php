<?php

namespace Usamamuneerchaudhary\CommandPalette\Support\Providers;

use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
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
        $minCharacters = PaletteSettings::for($panel)->recordSearchMinCharacters();

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

                foreach ($this->resultsFor($resource, $query) as $result) {
                    if ($commands->count() >= $limit) {
                        break 2;
                    }

                    $title = $this->stringify($result->title ?? null);
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
    protected function resultsFor(string $resource, string $query): iterable
    {
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
