<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Unit;

use Usamamuneerchaudhary\CommandPalette\Support\Install\PluginRegistrar;

test('it injects the plugin into a plugins array', function () {
    $contents = file_get_contents(__DIR__.'/../Fixtures/install/plugins-array.php');
    $result = (new PluginRegistrar)->inject((string) $contents, 'mod+shift+p');

    $this->assertTrue($result['changed']);
    $this->assertStringContainsString('use Usamamuneerchaudhary\\CommandPalette\\FilamentCommandPalettePlugin;', $result['contents']);
    $this->assertStringContainsString('FilamentCommandPalettePlugin::make()', $result['contents']);
    $this->assertStringContainsString("->keyBindings(['mod+shift+p'])", $result['contents']);
});

test('it injects the plugin onto a bare panel return', function () {
    $contents = file_get_contents(__DIR__.'/../Fixtures/install/return-panel.php');
    $result = (new PluginRegistrar)->inject((string) $contents);

    $this->assertTrue($result['changed']);
    $this->assertStringContainsString('->plugin(FilamentCommandPalettePlugin::make()', $result['contents']);
});

test('it does not overwrite a provider that already registers the plugin', function () {
    $contents = file_get_contents(__DIR__.'/../Fixtures/install/already-registered.php');
    $result = (new PluginRegistrar)->inject((string) $contents);

    $this->assertFalse($result['changed']);
    $this->assertSame('already-registered', $result['reason']);
    $this->assertSame(1, substr_count($result['contents'], 'FilamentCommandPalettePlugin::make()'));
});
