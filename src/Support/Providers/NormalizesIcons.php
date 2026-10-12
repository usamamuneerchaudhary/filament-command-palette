<?php

namespace Usamamuneerchaudhary\CommandPalette\Support\Providers;

use Illuminate\Contracts\Support\Htmlable;

trait NormalizesIcons
{
    /**
     * Filament will hand back closures, Htmlable icons and nulls depending on
     * how a page declared its icon. Only enums and plain strings are safe to
     * pass on to the renderer.
     */
    protected function normalizeIcon(mixed $icon, string|\BackedEnum|null $fallback = null): string|\BackedEnum|null
    {
        if ($icon instanceof \Closure) {
            try {
                $icon = $icon();
            } catch (\Throwable) {
                return $fallback;
            }
        }

        if ($icon instanceof \BackedEnum) {
            return $icon;
        }

        if (is_string($icon) && filled($icon)) {
            return $icon;
        }

        return $fallback;
    }

    protected function stringify(mixed $value, string $default = ''): string
    {
        if ($value instanceof \Closure) {
            try {
                $value = $value();
            } catch (\Throwable) {
                return $default;
            }
        }

        if ($value instanceof Htmlable) {
            return strip_tags($value->toHtml());
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return strip_tags((string) $value);
        }

        if (is_string($value)) {
            return strip_tags($value);
        }

        return $default;
    }
}
