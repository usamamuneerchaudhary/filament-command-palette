<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Feature;

use Filament\Facades\Filament;
use Livewire\Livewire;
use Usamamuneerchaudhary\CommandPalette\Exceptions\CommandNotAuthorized;
use Usamamuneerchaudhary\CommandPalette\Livewire\CommandPalette;
use Usamamuneerchaudhary\CommandPalette\Support\RecordReference;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\DeniedRecordResource;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\PaletteRecordActionsResource;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\PaletteUser;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\ScopedUserResource;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\SearchableUserResource;

beforeEach(function () {
    config()->set('command-palette.include_publish_views_command', false);
    config()->set('command-palette.cache.ttl', 0);
    config()->set('command-palette.record_search.min_characters', 1);

    $this->actingAsPaletteUser();
});

test('a resource list command is drillable and lists create', function () {
    Filament::getPanel('admin')->resources([SearchableUserResource::class]);

    $payload = Livewire::test(CommandPalette::class)->instance()->loadCommands();
    $list = collect($payload['commands'])->firstWhere('id', 'resource:index:'.SearchableUserResource::class);

    expect($list)->not->toBeNull()
        ->and($list['drillable'])->toBeTrue()
        ->and($list['resourceScope'])->toBe(RecordReference::hash(SearchableUserResource::class));

    $children = Livewire::test(CommandPalette::class)
        ->instance()
        ->loadChildren('resource:index:'.SearchableUserResource::class);

    expect(collect($children['commands'])->pluck('id')->all())
        ->toContain('resource:create:'.SearchableUserResource::class);
});

test('record search returns a drillable command with a resolvable id', function () {
    Filament::getPanel('admin')->resources([SearchableUserResource::class]);

    $user = PaletteUser::query()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);

    $results = Livewire::test(CommandPalette::class)->instance()->searchRecords('Ada');
    $match = collect($results)->firstWhere('label', 'Ada Lovelace');

    expect($match)->not->toBeNull()
        ->and($match['id'])->toBe(RecordReference::id(SearchableUserResource::class, $user->getKey()))
        ->and($match['drillable'])->toBeTrue()
        ->and($match['type'])->toBe('link');
});

test('stepping into a record lists view edit and delete', function () {
    Filament::getPanel('admin')->resources([SearchableUserResource::class]);

    $user = PaletteUser::query()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);

    $children = Livewire::test(CommandPalette::class)
        ->instance()
        ->loadChildren(RecordReference::id(SearchableUserResource::class, $user->getKey()));

    $ids = collect($children['commands'])->pluck('id')->all();

    expect($ids)->toContain(RecordReference::childId(SearchableUserResource::class, $user->getKey(), 'view'))
        ->and($ids)->toContain(RecordReference::childId(SearchableUserResource::class, $user->getKey(), 'edit'))
        ->and($ids)->toContain(RecordReference::childId(SearchableUserResource::class, $user->getKey(), 'delete'));
});

test('protected records omit delete', function () {
    Filament::getPanel('admin')->resources([SearchableUserResource::class]);

    $user = PaletteUser::query()->create([
        'name' => 'Protected',
        'email' => 'protected@example.com',
    ]);

    $children = Livewire::test(CommandPalette::class)
        ->instance()
        ->loadChildren(RecordReference::id(SearchableUserResource::class, $user->getKey()));

    expect(collect($children['commands'])->pluck('id')->all())
        ->not->toContain(RecordReference::childId(SearchableUserResource::class, $user->getKey(), 'delete'));
});

test('custom record actions are included after stepping in', function () {
    Filament::getPanel('admin')->resources([PaletteRecordActionsResource::class]);

    $user = PaletteUser::query()->create([
        'name' => 'Ada',
        'email' => 'ada@example.com',
    ]);

    $children = Livewire::test(CommandPalette::class)
        ->instance()
        ->loadChildren(RecordReference::id(PaletteRecordActionsResource::class, $user->getKey()));

    expect(collect($children['commands'])->pluck('id')->all())
        ->toContain('record-custom:impersonate:'.$user->getKey());
});

test('a forged record id cannot load children', function () {
    Filament::getPanel('admin')->resources([SearchableUserResource::class]);

    $this->expectException(CommandNotAuthorized::class);

    Livewire::test(CommandPalette::class)->instance()->loadChildren(
        RecordReference::id(SearchableUserResource::class, 99999),
    );
});

test('a record outside the resource query cannot load children', function () {
    $user = PaletteUser::query()->create([
        'name' => 'Other tenant',
        'email' => 'other@example.com',
    ]);
    ScopedUserResource::$visibleIds = [];
    Filament::getPanel('admin')->resources([ScopedUserResource::class]);

    $this->expectException(CommandNotAuthorized::class);

    Livewire::test(CommandPalette::class)->instance()->loadChildren(
        RecordReference::id(ScopedUserResource::class, $user->getKey()),
    );
});

test('a denied resource hash is ignored for scoped search', function () {
    Filament::getPanel('admin')->resources([DeniedRecordResource::class]);

    PaletteUser::query()->create([
        'name' => 'Ada',
        'email' => 'ada@example.com',
    ]);

    $results = Livewire::test(CommandPalette::class)
        ->instance()
        ->searchRecords('Ada', RecordReference::hash(DeniedRecordResource::class));

    expect($results)->toBe([]);
});

test('an invalid search scope is ignored', function () {
    Filament::getPanel('admin')->resources([SearchableUserResource::class]);

    PaletteUser::query()->create([
        'name' => 'Ada',
        'email' => 'ada@example.com',
    ]);

    $results = Livewire::test(CommandPalette::class)
        ->instance()
        ->searchRecords('Ada', 'not-a-hash-value');

    expect($results)->not->toBeEmpty();
});

test('scoped search only returns records from that resource', function () {
    Filament::getPanel('admin')->resources([SearchableUserResource::class]);

    PaletteUser::query()->create([
        'name' => 'Ada',
        'email' => 'ada@example.com',
    ]);

    $results = Livewire::test(CommandPalette::class)
        ->instance()
        ->searchRecords('Ada', RecordReference::hash(SearchableUserResource::class));

    expect($results)->not->toBeEmpty()
        ->and($results[0]['resourceScope'] ?? null)->toBeNull()
        ->and($results[0]['drillable'])->toBeTrue();
});
