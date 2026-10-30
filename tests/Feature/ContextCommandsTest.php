<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Feature;

use Filament\Facades\Filament;
use Livewire\Livewire;

use function Livewire\store;

use Usamamuneerchaudhary\CommandPalette\Livewire\CommandPalette;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\ContextualToolsPage;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\ContextualUserResource;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\PaletteUser;

beforeEach(function () {

    config()->set('command-palette.include_publish_views_command', false);
    config()->set('command-palette.search_providers', []);
    config()->set('command-palette.cache.ttl', 0);
    config()->set('command-palette.providers', []);

    $this->actingAsPaletteUser();
});

test('page commands are merged for a registered page', function () {
    Filament::getPanel('admin')->pages([ContextualToolsPage::class]);

    $response = Livewire::test(CommandPalette::class)
        ->instance()
        ->loadCommands(ContextualToolsPage::class);

    $this->assertSame('context:pdf', $response['commands'][0]['id']);
    $this->assertTrue($response['commands'][0]['context']);
    $this->assertSame('Current page', $response['commands'][0]['group']);
});

test('unregistered page classes are ignored', function () {
    $response = Livewire::test(CommandPalette::class)
        ->instance()
        ->loadCommands(ContextualToolsPage::class);

    $this->assertSame([], array_filter(
        $response['commands'],
        fn (array $command): bool => ($command['id'] ?? null) === 'context:pdf',
    ));
});

test('resource commands receive the resolved record', function () {
    Filament::getPanel('admin')->resources([ContextualUserResource::class]);

    $user = PaletteUser::query()->create([
        'name' => 'Ada',
        'email' => 'ada@example.com',
    ]);

    $instance = Livewire::test(CommandPalette::class)->instance();
    $payload = $instance->loadCommands(ContextualUserResource::class, $user->getKey());

    $this->assertSame('context:copy-id', $payload['commands'][0]['id']);

    $instance->runCommand('context:copy-id', null, ContextualUserResource::class, $user->getKey());

    $this->assertSame('/copied/'.$user->getKey(), store($instance)->get('redirect'));
});

test('record commands are omitted when the record cannot be resolved', function () {
    Filament::getPanel('admin')->resources([ContextualUserResource::class]);

    $response = Livewire::test(CommandPalette::class)
        ->instance()
        ->loadCommands(ContextualUserResource::class, 999);

    $this->assertSame([], array_filter(
        $response['commands'],
        fn (array $command): bool => ($command['id'] ?? null) === 'context:copy-id',
    ));
});

test('context commands are not stored in the static cache', function () {
    config()->set('command-palette.cache.ttl', 60);
    Filament::getPanel('admin')->pages([ContextualToolsPage::class]);

    $component = Livewire::test(CommandPalette::class)->instance();

    $withContext = $component->loadCommands(ContextualToolsPage::class);
    $withoutContext = $component->loadCommands();

    $this->assertContains('context:pdf', array_column($withContext['commands'], 'id'));
    $this->assertNotContains('context:pdf', array_column($withoutContext['commands'], 'id'));
});
