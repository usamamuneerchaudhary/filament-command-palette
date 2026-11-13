<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Unit;

use Filament\Actions\Action;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

test('it builds a link command', function () {
    $item = CommandItem::make('Dashboard', '/admin', 'Navigation');

    $this->assertSame('Dashboard', $item->label);
    $this->assertSame('/admin', $item->url);
    $this->assertSame('Navigation', $item->group);
    $this->assertSame(CommandItem::TYPE_LINK, $item->getType());
});

test('it defaults the group when null is passed', function () {
    $this->assertSame('Navigation', CommandItem::make('X', '/x', null)->group);
});

test('an item with a closure is an action', function () {
    $item = CommandItem::make('Clear cache')->action(fn () => 'done');

    $this->assertSame(CommandItem::TYPE_ACTION, $item->getType());
    $this->assertNotNull($item->getAction());
});

test('an item with an event is an event command', function () {
    $item = CommandItem::make('Dark mode')->dispatch('set-theme', ['theme' => 'dark']);

    $this->assertSame(CommandItem::TYPE_EVENT, $item->getType());
});

test('ids are stable across instances', function () {
    $a = CommandItem::make('Dashboard', '/admin', 'Navigation');
    $b = CommandItem::make('Dashboard', '/admin', 'Navigation');

    $this->assertSame($a->getId(), $b->getId());
});

test('ids differ when the url differs', function () {
    $a = CommandItem::make('Dashboard', '/admin');
    $b = CommandItem::make('Dashboard', '/app');

    $this->assertNotSame($a->getId(), $b->getId());
});

test('an explicit id wins', function () {
    $this->assertSame('custom', CommandItem::make('X', '/x')->id('custom')->getId());
});

test('visibility and authorization are evaluated', function () {
    $this->assertTrue(CommandItem::make('A', '/a')->isVisible());
    $this->assertFalse(CommandItem::make('B', '/b')->visible(false)->isVisible());
    $this->assertFalse(CommandItem::make('C', '/c')->hidden()->isVisible());
    $this->assertFalse(CommandItem::make('D', '/d')->authorize(fn () => false)->isVisible());
});

test('an unauthorized item is never visible', function () {
    $item = CommandItem::make('E', '/e')->visible(true)->authorize(false);

    $this->assertFalse($item->isVisible());
    $this->assertFalse($item->isAuthorized());
});

test('keywords are normalised from a string or array', function () {
    $this->assertSame(['new'], CommandItem::make('X')->keywords('new')->keywords);
    $this->assertSame(['a', 'b'], CommandItem::make('X')->keywords(['a', 'b'])->keywords);
});

test('the payload never contains closures', function () {
    $payload = CommandItem::make('Clear cache')
        ->action(fn () => null)
        ->authorize(fn () => true)
        ->visible(fn () => true)
        ->toArray();

    array_walk_recursive($payload, function ($value): void {
        $this->assertNotInstanceOf(\Closure::class, $value);
    });

    $this->assertArrayHasKey('id', $payload);
    $this->assertSame(CommandItem::TYPE_ACTION, $payload['type']);
});

test('backed enum icons are flattened for the client', function () {
    $item = CommandItem::make('X', '/x')->icon(FakeIcon::Star);

    $this->assertSame('fake-star', $item->toArray()['icon']);
});

test('chords normalise to two lowercase keys', function () {
    $this->assertSame('g c', CommandItem::make('Customers')->chord('G+C')->getChord());
    $this->assertNull(CommandItem::make('Customers')->chord('g')->getChord());
    $this->assertNull(CommandItem::make('Customers')->chord('g c i')->getChord());
});

test('drill down does not change the command type', function () {
    $item = CommandItem::make('Users', '/users')
        ->drillDown([
            CommandItem::make('Create user', '/users/create'),
        ]);

    expect($item->getType())->toBe(CommandItem::TYPE_LINK)
        ->and($item->isDrillable())->toBeTrue()
        ->and($item->hasChildren())->toBeFalse()
        ->and($item->toArray()['drillable'])->toBeTrue()
        ->and($item->toArray()['hasChildren'])->toBeFalse();
});

test('a filament action command does not embed the action', function () {
    $payload = CommandItem::make('Invite user')
        ->filamentAction(fn (): Action => Action::make('invite'))
        ->toArray();

    expect($payload['type'])->toBe(CommandItem::TYPE_FILAMENT_ACTION);

    array_walk_recursive($payload, function ($value): void {
        expect($value)->not->toBeInstanceOf(\Closure::class)
            ->and($value)->not->toBeInstanceOf(Action::class);
    });
});

test('a parent command is a submenu', function () {
    $item = CommandItem::make('Assign role')
        ->children([
            CommandItem::make('Admin')->id('role:admin'),
        ]);

    $this->assertTrue($item->hasChildren());
    $this->assertSame(CommandItem::TYPE_SUBMENU, $item->getType());
    $this->assertTrue($item->toArray()['hasChildren']);
    $this->assertCount(1, $item->resolveChildren());
});

test('mount action commands do not embed a closure', function () {
    $payload = CommandItem::make('Create user')->mountAction('create', ['foo' => 'bar'])->toArray();

    $this->assertSame(CommandItem::TYPE_MOUNT_ACTION, $payload['type']);
    $this->assertSame('create', $payload['mountAction']);
    $this->assertSame('bar', $payload['mountActionArguments']->foo);
});

test('preview actions keep site paths and http links only', function () {
    $payload = CommandItem::make('Invoice')
        ->resultActions([
            ['label' => 'Open', 'url' => '/admin/invoices/1'],
            ['label' => 'External', 'url' => 'https://example.com/invoice'],
            ['label' => 'Script', 'url' => 'javascript:alert(1)'],
        ])
        ->toArray();

    $this->assertSame([
        ['label' => 'Open', 'url' => '/admin/invoices/1', 'openInNewTab' => false],
        ['label' => 'External', 'url' => 'https://example.com/invoice', 'openInNewTab' => false],
    ], $payload['resultActions']);
});

test('html labels are sanitized before they reach the client', function () {
    $payload = CommandItem::make('Invoice')
        ->htmlLabel('<span class="fi-badge">Paid</span><script>alert(1)</script>')
        ->toArray();

    $this->assertStringContainsString('Paid', (string) $payload['htmlLabel']);
    $this->assertStringNotContainsString('<script>', (string) $payload['htmlLabel']);
    $this->assertStringNotContainsString('alert(1)', (string) $payload['htmlLabel']);
});
enum FakeIcon: string
{
    case Star = 'fake-star';
}
