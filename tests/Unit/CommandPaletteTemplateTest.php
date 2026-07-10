<?php

namespace Usamamuneerchaudhary\CommandPalette\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Usamamuneerchaudhary\CommandPalette\Livewire\CommandPalette;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

class CommandPaletteTemplateTest extends TestCase
{
    public function test_resolve_template_name_defaults_invalid_to_standard(): void
    {
        $this->assertSame('standard', CommandPalette::resolveTemplateName(null));
        $this->assertSame('standard', CommandPalette::resolveTemplateName('nonexistent'));
        $this->assertSame('standard', CommandPalette::resolveTemplateName(123));
    }

    public function test_resolve_template_name_accepts_valid_templates(): void
    {
        foreach (CommandPalette::TEMPLATES as $template) {
            $this->assertSame($template, CommandPalette::resolveTemplateName($template));
        }
    }

    public function test_command_item_description_and_preview_serialize(): void
    {
        $item = CommandItem::make('Edit User', '/admin/users/1/edit')
            ->group('Users')
            ->description('Administration')
            ->preview(['Email' => 'user@example.com', 'Role' => 'Admin']);

        $array = $item->toArray();

        $this->assertSame('Administration', $array['description']);
        $this->assertSame([
            'Email' => 'user@example.com',
            'Role' => 'Admin',
        ], $array['preview']);
    }

    public function test_command_item_description_and_preview_default_to_null(): void
    {
        $item = CommandItem::make('Dashboard', '/dashboard');

        $this->assertNull($item->description);
        $this->assertNull($item->preview);
        $this->assertNull($item->toArray()['description']);
        $this->assertNull($item->toArray()['preview']);
    }
}
