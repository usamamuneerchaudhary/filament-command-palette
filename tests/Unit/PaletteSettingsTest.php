<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Unit;

use Usamamuneerchaudhary\CommandPalette\Livewire\CommandPalette;
use Usamamuneerchaudhary\CommandPalette\Support\PaletteSettings;

test('it falls back to the config file', function () {
    config()->set('command-palette.max_results', 42);

    $this->assertSame(42, (new PaletteSettings)->maxResults());
});

test('an unknown template falls back to standard', function () {
    config()->set('command-palette.template', 'nope');

    $this->assertSame('standard', (new PaletteSettings)->template());
    $this->assertSame('standard', CommandPalette::resolveTemplateName('nope'));
    $this->assertSame('standard', CommandPalette::resolveTemplateName(null));
    $this->assertSame('groups', CommandPalette::resolveTemplateName('groups'));
});

test('empty key bindings fall back to the default', function () {
    config()->set('command-palette.key_bindings', []);

    $this->assertSame(['mod+k'], (new PaletteSettings)->keyBindings());
});

test('max results is never below one', function () {
    config()->set('command-palette.max_results', 0);

    $this->assertSame(1, (new PaletteSettings)->maxResults());
});

test('dotted keys resolve through config', function () {
    config()->set('command-palette.record_search.enabled', false);

    $this->assertFalse((new PaletteSettings)->bool('record_search.enabled', true));
});

test('record search character bounds never go below one', function () {
    config()->set('command-palette.record_search.min_characters', 0);
    config()->set('command-palette.record_search.max_characters', 1);

    $settings = new PaletteSettings;

    $this->assertSame(1, $settings->recordSearchMinCharacters());
    $this->assertSame(1, $settings->recordSearchMaxCharacters());
});
