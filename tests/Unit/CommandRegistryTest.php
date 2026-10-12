<?php

namespace Usamamuneerchaudhary\CommandPalette\Tests\Unit;

use Filament\Panel;
use Usamamuneerchaudhary\CommandPalette\Contracts\CommandProvider;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;
use Usamamuneerchaudhary\CommandPalette\Support\CommandRegistry;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\PaletteUser;
use Usamamuneerchaudhary\CommandPalette\Tests\TestCase;

class CommandRegistryTest extends TestCase
{
    protected function panel(): Panel
    {
        return Panel::make()->id('testing')->path('testing');
    }

    protected function useProvider(CommandProvider $provider): void
    {
        config()->set('command-palette.providers', [$provider]);
        config()->set('command-palette.search_providers', []);
        config()->set('command-palette.include_publish_views_command', false);
        config()->set('command-palette.cache.ttl', 0);
    }

    public function test_it_collects_commands_from_providers(): void
    {
        $this->useProvider(new StubProvider([
            CommandItem::make('Dashboard', '/dashboard'),
            CommandItem::make('Users', '/users'),
        ]));

        $commands = (new CommandRegistry)->all($this->panel());

        $this->assertCount(2, $commands);
    }

    public function test_it_drops_hidden_and_unauthorized_commands(): void
    {
        $this->useProvider(new StubProvider([
            CommandItem::make('Visible', '/a'),
            CommandItem::make('Hidden', '/b')->visible(false),
            CommandItem::make('Forbidden', '/c')->authorize(fn () => false),
        ]));

        $labels = (new CommandRegistry)->all($this->panel())->pluck('label')->all();

        $this->assertSame(['Visible'], $labels);
    }

    public function test_a_check_that_throws_is_treated_as_a_denial(): void
    {
        $this->useProvider(new StubProvider([
            CommandItem::make('Broken', '/a')->authorize(fn () => throw new \RuntimeException('boom')),
        ]));

        $this->assertCount(0, (new CommandRegistry)->all($this->panel()));
    }

    public function test_a_provider_that_throws_does_not_break_the_palette(): void
    {
        config()->set('command-palette.providers', [
            new ExplodingProvider,
            new StubProvider([CommandItem::make('Survivor', '/a')]),
        ]);
        config()->set('command-palette.include_publish_views_command', false);

        $this->assertCount(1, (new CommandRegistry)->all($this->panel()));
    }

    public function test_duplicate_ids_are_removed(): void
    {
        $this->useProvider(new StubProvider([
            CommandItem::make('Dashboard', '/dashboard'),
            CommandItem::make('Dashboard', '/dashboard'),
        ]));

        $this->assertCount(1, (new CommandRegistry)->all($this->panel()));
    }

    public function test_higher_priority_sorts_first(): void
    {
        $this->useProvider(new StubProvider([
            CommandItem::make('Low', '/a')->priority(-5),
            CommandItem::make('High', '/b')->priority(10),
        ]));

        $labels = (new CommandRegistry)->all($this->panel())->pluck('label')->all();

        $this->assertSame(['High', 'Low'], $labels);
    }

    public function test_find_returns_null_for_an_unauthorized_id(): void
    {
        $forbidden = CommandItem::make('Forbidden', '/c')->authorize(fn () => false);

        $this->useProvider(new StubProvider([$forbidden]));

        $this->assertNull((new CommandRegistry)->find($forbidden->getId(), $this->panel()));
    }

    public function test_find_returns_an_authorized_command(): void
    {
        $allowed = CommandItem::make('Allowed', '/a');

        $this->useProvider(new StubProvider([$allowed]));

        $this->assertInstanceOf(
            CommandItem::class,
            (new CommandRegistry)->find($allowed->getId(), $this->panel()),
        );
    }

    public function test_find_rejects_an_unknown_id(): void
    {
        $this->useProvider(new StubProvider([CommandItem::make('A', '/a')]));

        $this->assertNull((new CommandRegistry)->find('made-up-id', $this->panel()));
    }

    public function test_payload_cache_is_isolated_by_panel(): void
    {
        $this->useProvider(new StubProvider([
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
    }

    public function test_payload_cache_is_isolated_by_user(): void
    {
        $this->useProvider(new StubProvider([
            CommandItem::make('Dashboard', '/dashboard'),
        ]));
        config()->set('command-palette.cache.ttl', 60);

        $registry = new CommandRegistry;
        $panel = $this->panel();

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
    }

    public function test_the_client_payload_builds_commands_once(): void
    {
        $provider = new CountingProvider([CommandItem::make('A', '/a')]);
        $this->useProvider($provider);

        $built = 0;
        (new CommandRegistry)->rememberClient($this->panel(), function ($commands) use (&$built): array {
            $built++;

            return ['commands' => $commands->all(), 'icons' => []];
        });

        $this->assertSame(1, $provider->calls);
        $this->assertSame(1, $built);
    }

    public function test_the_payload_is_plain_serializable_data(): void
    {
        $this->useProvider(new StubProvider([
            CommandItem::make('Run')->action(fn () => null),
        ]));

        $payload = (new CommandRegistry)->payload($this->panel());

        $this->assertIsString(json_encode($payload));
        $this->assertSame('action', $payload[0]['type']);
    }

    public function test_the_global_authorize_gate_is_applied(): void
    {
        $this->useProvider(new StubProvider([
            CommandItem::make('Keep', '/a'),
            CommandItem::make('Drop', '/b'),
        ]));

        config()->set('command-palette.authorize', fn (CommandItem $item): bool => $item->label === 'Keep');

        $labels = (new CommandRegistry)->all($this->panel())->pluck('label')->all();

        $this->assertSame(['Keep'], $labels);
    }
}

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
