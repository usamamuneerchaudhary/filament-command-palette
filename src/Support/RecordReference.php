<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Support;

use Filament\Panel;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Stable, self-describing ids for records in the palette.
 *
 * The client only ever sees `record:{hash}:{key}`. The hash is matched against
 * the current panel's resources, and the record is loaded through the resource
 * global-search query so tenancy scoping still applies.
 */
class RecordReference
{
    public const string PREFIX = 'record:';

    public const int HASH_LENGTH = 16;

    public const int MAX_KEY_LENGTH = 128;

    /**
     * @param  class-string  $resource
     */
    public static function hash(string $resource): string
    {
        return substr(hash('xxh128', $resource), 0, self::HASH_LENGTH);
    }

    /**
     * @param  class-string  $resource
     */
    public static function id(string $resource, int|string $key): string
    {
        return self::PREFIX.self::hash($resource).':'.rawurlencode((string) $key);
    }

    /**
     * @param  class-string  $resource
     */
    public static function childId(string $resource, int|string $key, string $suffix): string
    {
        return self::id($resource, $key).':'.$suffix;
    }

    /**
     * @return array{hash: string, key: string, suffix: ?string}|null
     */
    public static function parse(string $id): ?array
    {
        if (! str_starts_with($id, self::PREFIX)) {
            return null;
        }

        $rest = substr($id, strlen(self::PREFIX));
        $hashLength = self::HASH_LENGTH;

        if (strlen($rest) < $hashLength + 2 || $rest[$hashLength] !== ':') {
            return null;
        }

        $hash = substr($rest, 0, $hashLength);

        if (! ctype_xdigit($hash)) {
            return null;
        }

        $afterHash = substr($rest, $hashLength + 1);
        $encodedKey = strstr($afterHash, ':', true);
        $suffix = null;

        if ($encodedKey === false) {
            $encodedKey = $afterHash;
        } else {
            $suffix = substr($afterHash, strlen($encodedKey) + 1);
            $suffix = $suffix === '' ? null : $suffix;
        }

        if ($encodedKey === '') {
            return null;
        }

        $key = rawurldecode($encodedKey);

        if ($key === '' || strlen($key) > self::MAX_KEY_LENGTH || str_contains($key, "\0")) {
            return null;
        }

        return [
            'hash' => strtolower($hash),
            'key' => $key,
            'suffix' => $suffix,
        ];
    }

    /**
     * @return class-string<resource>|null
     */
    public static function resourceByHash(Panel $panel, string $hash): ?string
    {
        try {
            foreach ($panel->getResources() as $resource) {
                if (! is_string($resource) || ! is_subclass_of($resource, Resource::class)) {
                    continue;
                }

                if (hash_equals(self::hash($resource), $hash)) {
                    return $resource;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    /**
     * @return array{0: class-string<resource>, 1: Model}|null
     */
    public static function resolve(string $id, Panel $panel): ?array
    {
        $parsed = self::parse($id);

        if ($parsed === null) {
            return null;
        }

        $resource = self::resourceByHash($panel, $parsed['hash']);

        if ($resource === null || ! self::canAccess($resource)) {
            return null;
        }

        try {
            $record = $resource::getGlobalSearchEloquentQuery()
                ->whereKey($parsed['key'])
                ->first();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        if (! $record instanceof Model) {
            return null;
        }

        return [$resource, $record];
    }

    /**
     * @param  class-string<resource>  $resource
     */
    protected static function canAccess(string $resource): bool
    {
        try {
            if (method_exists($resource, 'canAccess') && ! $resource::canAccess()) {
                return false;
            }

            if (method_exists($resource, 'canViewAny') && ! $resource::canViewAny()) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        return true;
    }
}
