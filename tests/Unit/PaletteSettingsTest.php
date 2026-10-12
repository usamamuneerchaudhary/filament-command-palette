<?php

namespace Usamamuneerchaudhary\CommandPalette\Tests\Unit;

use Usamamuneerchaudhary\CommandPalette\Livewire\CommandPalette;
use Usamamuneerchaudhary\CommandPalette\Support\PaletteSettings;
use Usamamuneerchaudhary\CommandPalette\Tests\TestCase;

class PaletteSettingsTest extends TestCase
{
    public function test_it_falls_back_to_the_config_file(): void
    {
        config()->set('command-palette.max_results', 42);

        $this->assertSame(42, (new PaletteSettings)->maxResults());
    }

    public function test_an_unknown_template_falls_back_to_standard(): void
    {
        config()->set('command-palette.template', 'nope');

        $this->assertSame('standard', (new PaletteSettings)->template());
        $this->assertSame('standard', CommandPalette::resolveTemplateName('nope'));
        $this->assertSame('standard', CommandPalette::resolveTemplateName(null));
        $this->assertSame('groups', CommandPalette::resolveTemplateName('groups'));
    }

    public function test_empty_key_bindings_fall_back_to_the_default(): void
    {
        config()->set('command-palette.key_bindings', []);

        $this->assertSame(['mod+k'], (new PaletteSettings)->keyBindings());
    }

    public function test_max_results_is_never_below_one(): void
    {
        config()->set('command-palette.max_results', 0);

        $this->assertSame(1, (new PaletteSettings)->maxResults());
    }

    public function test_dotted_keys_resolve_through_config(): void
    {
        config()->set('command-palette.record_search.enabled', false);

        $this->assertFalse((new PaletteSettings)->bool('record_search.enabled', true));
    }
}
