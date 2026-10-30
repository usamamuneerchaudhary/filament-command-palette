<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Unit;

use Usamamuneerchaudhary\CommandPalette\Livewire\CommandPalette;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

test('resolve template name defaults invalid to standard', function () {
    $this->assertSame('standard', CommandPalette::resolveTemplateName(null));
    $this->assertSame('standard', CommandPalette::resolveTemplateName('nonexistent'));
    $this->assertSame('standard', CommandPalette::resolveTemplateName(123));
});

test('resolve template name accepts valid templates', function () {
    foreach (CommandPalette::TEMPLATES as $template) {
        $this->assertSame($template, CommandPalette::resolveTemplateName($template));
    }
});

test('command item description and preview serialize', function () {
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
});

test('command item description and preview default to null', function () {
    $item = CommandItem::make('Dashboard', '/dashboard');

    $this->assertNull($item->description);
    $this->assertNull($item->preview);
    $this->assertNull($item->toArray()['description']);
    $this->assertNull($item->toArray()['preview']);
});
