<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Unit;

use Filament\Panel;
use Usamamuneerchaudhary\CommandPalette\Contracts\CommandProvider;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;
use Usamamuneerchaudhary\CommandPalette\Support\CommandRegistry;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\PaletteUser;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\RoleAwareUser;

function registryPanel(): Panel
{
    return Panel::make()->id('testing')->path('testing');
}

function registryUseProvider(CommandProvider $provider): void
{
    config()->set('command-palette.providers', [$provider]);
    config()->set('command-palette.search_providers', []);
    config()->set('command-palette.include_publish_views_command', false);
    config()->set('command-palette.cache.ttl', 0);
}
test('it collects commands from providers', function () {
    registryUseProvider(new StubProvider([
        CommandItem::make('Dashboard', '/dashboard'),
        CommandItem::make('Users', '/users'),
    ]));

    $commands = (new CommandRegistry)->all(registryPanel());

    $this->assertCount(2, $commands);
});

test('it drops hidden and unauthorized commands', function () {
    registryUseProvider(new StubProvider([
        CommandItem::make('Visible', '/a'),
        CommandItem::make('Hidden', '/b')->visible(false),
        CommandItem::make('Forbidden', '/c')->authorize(fn () => false),
    ]));

    $labels = (new CommandRegistry)->all(registryPanel())->pluck('label')->all();

    $this->assertSame(['Visible'], $labels);
});

test('a check that throws is treated as a denial', function () {
    registryUseProvider(new StubProvider([
        CommandItem::make('Broken', '/a')->authorize(fn () => throw new \RuntimeException('boom')),
    ]));

    $this->assertCount(0, (new CommandRegistry)->all(registryPanel()));
});

test('a provider that throws does not break the palette', function () {
    config()->set('command-palette.providers', [
        new ExplodingProvider,
        new StubProvider([CommandItem::make('Survivor', '/a')]),
    ]);
    config()->set('command-palette.include_publish_views_command', false);

    $this->assertCount(1, (new CommandRegistry)->all(registryPanel()));
});

test('duplicate ids are removed', function () {
    registryUseProvider(new StubProvider([
        CommandItem::make('Dashboard', '/dashboard'),
        CommandItem::make('Dashboard', '/dashboard'),
    ]));

    $this->assertCount(1, (new CommandRegistry)->all(registryPanel()));
});

test('higher priority sorts first', function () {
    registryUseProvider(new StubProvider([
        CommandItem::make('Low', '/a')->priority(-5),
        CommandItem::make('High', '/b')->priority(10),
    ]));

    $labels = (new CommandRegistry)->all(registryPanel())->pluck('label')->all();

    $this->assertSame(['High', 'Low'], $labels);
});

test('find returns null for an unauthorized id', function () {
    $forbidden = CommandItem::make('Forbidden', '/c')->authorize(fn () => false);

    registryUseProvider(new StubProvider([$forbidden]));

    $this->assertNull((new CommandRegistry)->find($forbidden->getId(), registryPanel()));
});

test('find returns an authorized command', function () {
    $allowed = CommandItem::make('Allowed', '/a');

    registryUseProvider(new StubProvider([$allowed]));

    $this->assertInstanceOf(
        CommandItem::class,
        (new CommandRegistry)->find($allowed->getId(), registryPanel()),
    );
});

test('find rejects an unknown id', function () {
    registryUseProvider(new StubProvider([CommandItem::make('A', '/a')]));

    $this->assertNull((new CommandRegistry)->find('made-up-id', registryPanel()));
});

test('payload cache is isolated by panel', function () {
    registryUseProvider(new StubProvider([
        CommandItem::make('Dashboard', '/dashboard'),
    ]));
    config()->set('command-palette.cache.ttl', 60);

    $registry = new CommandRegistry;
    $admin = Panel::make()->id('cached-admin')->path('cached-admin');
    $app = Panel::make()->id('cached-app')->path('cached-app');

    $registry->payload($admin);

    config()->set('command-palette.providers', [
        new StubProvider([CommandItem::make('Users', '/users')]),
    ]);

    $this->assertSame('Dashboard', $registry->payload($admin)[0]['label']);
    $this->assertSame('Users', $registry->payload($app)[0]['label']);
});

test('payload cache is isolated by user', function () {
    registryUseProvider(new StubProvider([
        CommandItem::make('Dashboard', '/dashboard'),
    ]));
    config()->set('command-palette.cache.ttl', 60);

    $registry = new CommandRegistry;
    $panel = registryPanel();

    $this->actingAsPaletteUser(PaletteUser::query()->create([
        'name' => 'Ann',
        'email' => 'ann@example.com',
    ]));
    $registry->payload($panel);

    config()->set('command-palette.providers', [
        new StubProvider([CommandItem::make('Users', '/users')]),
    ]);
    $this->actingAsPaletteUser(PaletteUser::query()->create([
        'name' => 'Ben',
        'email' => 'ben@example.com',
    ]));

    $this->assertSame('Users', $registry->payload($panel)[0]['label']);
});

test('the client payload builds commands once', function () {
    $provider = new CountingProvider([CommandItem::make('A', '/a')]);
    registryUseProvider($provider);

    $built = 0;
    (new CommandRegistry)->rememberClient(registryPanel(), function ($commands) use (&$built): array {
        $built++;

        return ['commands' => $commands->all(), 'icons' => []];
    });

    $this->assertSame(1, $provider->calls);
    $this->assertSame(1, $built);
});

test('the payload is plain serializable data', function () {
    registryUseProvider(new StubProvider([
        CommandItem::make('Run')->action(fn () => null),
    ]));

    $payload = (new CommandRegistry)->payload(registryPanel());

    $this->assertIsString(json_encode($payload));
    $this->assertSame('action', $payload[0]['type']);
});

test('payload cache is isolated by role names', function () {
    registryUseProvider(new StubProvider([
        CommandItem::make('Dashboard', '/dashboard'),
    ]));
    config()->set('command-palette.cache.ttl', 60);

    $registry = new CommandRegistry;
    $panel = registryPanel();
    $user = RoleAwareUser::query()->create([
        'name' => 'Riley',
        'email' => 'riley@example.com',
    ]);
    $user->roleNames = ['admin'];
    $this->actingAsPaletteUser($user);
    $registry->payload($panel);

    config()->set('command-palette.providers', [
        new StubProvider([CommandItem::make('Users', '/users')]),
    ]);
    $user->roleNames = ['guest'];

    $this->assertSame('Users', $registry->payload($panel)[0]['label']);
});

test('the global authorize gate is applied', function () {
    registryUseProvider(new StubProvider([
        CommandItem::make('Keep', '/a'),
        CommandItem::make('Drop', '/b'),
    ]));

    config()->set('command-palette.authorize', fn (CommandItem $item): bool => $item->label === 'Keep');

    $labels = (new CommandRegistry)->all(registryPanel())->pluck('label')->all();

    $this->assertSame(['Keep'], $labels);
});
class StubProvider implements CommandProvider
{
    public function __construct(protected array $items) {}

    public function commands(Panel $panel): iterable
    {
        return $this->items;
    }
}

class ExplodingProvider implements CommandProvider
{
    public function commands(Panel $panel): iterable
    {
        throw new \RuntimeException('provider failed');
    }
}

class CountingProvider implements CommandProvider
{
    public int $calls = 0;

    public function __construct(protected array $items) {}

    public function commands(Panel $panel): iterable
    {
        $this->calls++;

        return $this->items;
    }
}
