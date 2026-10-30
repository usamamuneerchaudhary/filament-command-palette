<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Support;

use Filament\Pages\Page as FilamentPage;
use Filament\Panel;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Usamamuneerchaudhary\CommandPalette\Contracts\HasPaletteCommands;

class PaletteContext
{
    /**
     * @param  class-string|null  $pageClass
     */
    public function __construct(
        public readonly ?string $pageClass,
        public readonly int|string|null $recordKey,
        protected readonly ?Panel $panel,
    ) {}

    public static function empty(?Panel $panel = null): self
    {
        return new self(null, null, $panel);
    }

    public static function resolve(Panel $panel, ?string $pageClass, mixed $recordKey): self
    {
        $class = self::normalizeClass($pageClass);

        if ($class === null || ! self::isRegistered($panel, $class)) {
            return self::empty($panel);
        }

        $key = self::normalizeKey($recordKey);

        return new self($class, $key, $panel);
    }

    /**
     * @return class-string|null
     */
    public function pageClass(): ?string
    {
        return $this->pageClass;
    }

    /**
     * @return class-string<resource>|null
     */
    public function resourceClass(): ?string
    {
        if ($this->pageClass === null) {
            return null;
        }

        if (is_subclass_of($this->pageClass, Resource::class)) {
            return $this->pageClass;
        }

        if (is_subclass_of($this->pageClass, ResourcePage::class)) {
            try {
                $resource = $this->pageClass::getResource();
            } catch (\Throwable) {
                return null;
            }

            return is_string($resource) && is_subclass_of($resource, Resource::class)
                ? $resource
                : null;
        }

        return null;
    }

    /**
     * @return list<class-string>
     */
    public function commandSources(): array
    {
        $sources = [];

        foreach ([$this->pageClass(), $this->resourceClass()] as $class) {
            if (is_string($class) && is_subclass_of($class, HasPaletteCommands::class)) {
                $sources[] = $class;
            }
        }

        return array_values(array_unique($sources));
    }

    public function record(): ?Model
    {
        $resource = $this->resourceClass();
        $key = $this->recordKey;

        if ($resource === null || $key === null) {
            return null;
        }

        try {
            return $resource::resolveRecordRouteBinding($key);
        } catch (ModelNotFoundException) {
            return null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @return class-string|null
     */
    protected static function normalizeClass(?string $pageClass): ?string
    {
        if (blank($pageClass)) {
            return null;
        }

        if (class_exists($pageClass)) {
            return $pageClass;
        }

        $registryClass = 'Livewire\\Mechanisms\\ComponentRegistry';

        if (! class_exists($registryClass)) {
            return null;
        }

        try {
            $resolved = app($registryClass)->getClass($pageClass);
        } catch (\Throwable) {
            return null;
        }

        return is_string($resolved) && class_exists($resolved) ? $resolved : null;
    }

    protected static function normalizeKey(mixed $recordKey): int|string|null
    {
        if (is_int($recordKey)) {
            return $recordKey;
        }

        if (is_string($recordKey) && $recordKey !== '') {
            return $recordKey;
        }

        return null;
    }

    /**
     * @param  class-string  $class
     */
    public static function isRegistered(Panel $panel, string $class): bool
    {
        if (! is_subclass_of($class, FilamentPage::class) && ! is_subclass_of($class, Resource::class)) {
            return false;
        }

        try {
            foreach ($panel->getPages() as $page) {
                if ($page === $class) {
                    return true;
                }
            }

            foreach ($panel->getResources() as $resource) {
                if ($resource === $class) {
                    return true;
                }

                if (! is_string($resource) || ! method_exists($resource, 'getPages')) {
                    continue;
                }

                foreach ($resource::getPages() as $registration) {
                    $page = $registration instanceof PageRegistration
                        ? $registration->getPage()
                        : (is_string($registration) ? $registration : null);

                    if ($page === $class) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        return false;
    }
}
