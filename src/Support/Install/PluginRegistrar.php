<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Support\Install;

class PluginRegistrar
{
    public function isRegistered(string $contents): bool
    {
        return str_contains($contents, 'FilamentCommandPalettePlugin');
    }

    /**
     * @return array{contents: string, changed: bool, reason: string}
     */
    public function inject(string $contents, string $shortcut = 'mod+k'): array
    {
        if ($this->isRegistered($contents)) {
            return [
                'contents' => $contents,
                'changed' => false,
                'reason' => 'already-registered',
            ];
        }

        $contents = $this->ensureImport($contents);
        $plugin = $this->pluginCall($shortcut);

        if (preg_match('/->plugins\(\s*\[/', $contents) === 1) {
            $updated = preg_replace(
                '/->plugins\(\s*\[/',
                "->plugins([\n            {$plugin},",
                $contents,
                1,
            );

            return $this->result($contents, is_string($updated) ? $updated : $contents, 'plugins-array');
        }

        if (preg_match('/->plugin\(/', $contents) === 1) {
            $updated = preg_replace(
                '/(return\s+\$panel)/',
                "$1\n            ->plugin({$plugin})",
                $contents,
                1,
            );

            return $this->result($contents, is_string($updated) ? $updated : $contents, 'plugin-chain');
        }

        if (preg_match('/return\s+\$panel/', $contents) === 1) {
            $updated = preg_replace(
                '/(return\s+\$panel)/',
                "$1\n            ->plugin({$plugin})",
                $contents,
                1,
            );

            return $this->result($contents, is_string($updated) ? $updated : $contents, 'return-panel');
        }

        return [
            'contents' => $contents,
            'changed' => false,
            'reason' => 'no-injection-point',
        ];
    }

    protected function ensureImport(string $contents): string
    {
        $import = 'use Usamamuneerchaudhary\\CommandPalette\\FilamentCommandPalettePlugin;';

        if (str_contains($contents, $import)) {
            return $contents;
        }

        if (preg_match('/^(use [^;]+;)$/m', $contents) === 1) {
            return preg_replace(
                '/^(use [^;]+;)$/m',
                "$1\n{$import}",
                $contents,
                1,
            ) ?? $contents;
        }

        if (preg_match('/^namespace [^;]+;/m', $contents) === 1) {
            return preg_replace(
                '/^(namespace [^;]+;)/m',
                "$1\n\n{$import}",
                $contents,
                1,
            ) ?? $contents;
        }

        return $import."\n\n".$contents;
    }

    protected function pluginCall(string $shortcut): string
    {
        $shortcut = trim($shortcut) !== '' ? trim($shortcut) : 'mod+k';
        $shortcut = addslashes($shortcut);

        return "FilamentCommandPalettePlugin::make()\n                ->keyBindings(['{$shortcut}'])";
    }

    /**
     * @return array{contents: string, changed: bool, reason: string}
     */
    protected function result(string $original, string $updated, string $reason): array
    {
        return [
            'contents' => $updated,
            'changed' => $updated !== $original,
            'reason' => $reason,
        ];
    }
}
