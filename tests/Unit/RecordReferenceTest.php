<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Unit;

use Filament\Facades\Filament;
use Usamamuneerchaudhary\CommandPalette\Support\RecordReference;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\DeniedRecordResource;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\PaletteUser;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\ScopedUserResource;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\SearchableUserResource;

test('it builds a stable hashed id', function () {
    $id = RecordReference::id(SearchableUserResource::class, 12);

    expect($id)
        ->toStartWith('record:')
        ->toEndWith(':12')
        ->and(RecordReference::hash(SearchableUserResource::class))
        ->toHaveLength(RecordReference::HASH_LENGTH);
});

test('it parses a record id and an action suffix', function () {
    $id = RecordReference::childId(SearchableUserResource::class, 12, 'view');
    $parsed = RecordReference::parse($id);

    expect($parsed)->toMatchArray([
        'hash' => RecordReference::hash(SearchableUserResource::class),
        'key' => '12',
        'suffix' => 'view',
    ]);
});

test('it rejects forged and oversized ids', function (string $id) {
    expect(RecordReference::parse($id))->toBeNull();
})->with([
    'missing prefix' => 'users:1',
    'short hash' => 'record:abc:1',
    'non hex hash' => 'record:zzzzzzzzzzzzzzzz:1',
    'empty key' => 'record:'.str_repeat('a', 16).':',
    'oversized key' => 'record:'.str_repeat('a', 16).':'.str_repeat('k', RecordReference::MAX_KEY_LENGTH + 1),
]);

test('it loads a record through the panel resource', function () {
    $this->actingAsPaletteUser();
    Filament::getPanel('admin')->resources([SearchableUserResource::class]);

    $user = PaletteUser::query()->create([
        'name' => 'Ada',
        'email' => 'ada@example.com',
    ]);

    $resolved = RecordReference::resolve(
        RecordReference::id(SearchableUserResource::class, $user->getKey()),
        Filament::getPanel('admin'),
    );

    expect($resolved)->not->toBeNull()
        ->and($resolved[0])->toBe(SearchableUserResource::class)
        ->and($resolved[1]->is($user))->toBeTrue();
});

test('it does not resolve a record outside the resource query', function () {
    $this->actingAsPaletteUser();
    ScopedUserResource::$visibleIds = [];
    Filament::getPanel('admin')->resources([ScopedUserResource::class]);

    $user = PaletteUser::query()->create([
        'name' => 'Hidden',
        'email' => 'hidden@example.com',
    ]);

    expect(RecordReference::resolve(
        RecordReference::id(ScopedUserResource::class, $user->getKey()),
        Filament::getPanel('admin'),
    ))->toBeNull();
});

test('it does not resolve a record the user cannot list', function () {
    $this->actingAsPaletteUser();
    Filament::getPanel('admin')->resources([DeniedRecordResource::class]);

    $user = PaletteUser::query()->create([
        'name' => 'Secret',
        'email' => 'secret@example.com',
    ]);

    expect(RecordReference::resolve(
        RecordReference::id(DeniedRecordResource::class, $user->getKey()),
        Filament::getPanel('admin'),
    ))->toBeNull();
});
