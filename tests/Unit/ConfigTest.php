<?php

namespace Usamamuneerchaudhary\CommandPalette\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /**
     * `php artisan config:cache` runs var_export() over the merged config.
     * A closure anywhere in the shipped defaults makes that command fatal for
     * every application installing this package, which is exactly what
     * happened in v1.
     */
    public function test_the_shipped_config_is_serializable(): void
    {
        $config = require __DIR__.'/../../config/command-palette.php';

        array_walk_recursive($config, function ($value, $key): void {
            $this->assertNotInstanceOf(
                \Closure::class,
                $value,
                "The config key [{$key}] holds a closure, which breaks config:cache.",
            );
        });

        $this->assertIsString(var_export($config, true));
    }

    public function test_custom_commands_ships_empty(): void
    {
        $config = require __DIR__.'/../../config/command-palette.php';

        $this->assertSame([], $config['custom_commands']);
    }
}
