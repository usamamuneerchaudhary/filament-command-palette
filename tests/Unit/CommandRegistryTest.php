<?php

namespace Usamamuneerchaudhary\CommandPalette\Tests\Unit;

use Illuminate\Support\Collection;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;
use Usamamuneerchaudhary\CommandPalette\Support\CommandRegistry;
use Usamamuneerchaudhary\CommandPalette\Tests\TestCase;

class CommandRegistryTest extends TestCase
{
    protected CommandRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new CommandRegistry();
    }

    public function test_get_all_commands_returns_collection(): void
    {
        $commands = $this->registry->getAllCommands();

        $this->assertInstanceOf(Collection::class, $commands);
    }

    public function test_custom_commands_are_merged(): void
    {
        config()->set('command-palette.custom_commands', [
            fn () => [
                CommandItem::make('Custom Action', '/custom'),
            ],
        ]);

        $commands = $this->registry->getAllCommands();

        $labels = $commands->pluck('label')->all();
        $this->assertContains('Custom Action', $labels);
    }

    public function test_custom_commands_fluent_api(): void
    {
        config()->set('command-palette.custom_commands', [
            fn () => [
                CommandItem::make('My Action', '/some-url')->group('Custom'),
            ],
        ]);

        $commands = $this->registry->getAllCommands();

        $custom = $commands->first(fn (CommandItem $c) => $c->label === 'My Action');
        $this->assertNotNull($custom);
        $this->assertSame('Custom', $custom->group);
    }

    public function test_search_filters_by_label(): void
    {
        config()->set('command-palette.custom_commands', [
            fn () => [
                CommandItem::make('Dashboard', '/dashboard'),
                CommandItem::make('Settings', '/settings'),
            ],
        ]);

        $commands = $this->registry->getCommands(search: 'dashboard', limit: false);

        $this->assertCount(1, $commands);
        $this->assertSame('Dashboard', $commands->first()->label);
    }

    public function test_search_filters_by_group(): void
    {
        config()->set('command-palette.custom_commands', [
            fn () => [
                CommandItem::make('Item A', '/a', 'Alpha'),
                CommandItem::make('Item B', '/b', 'Beta'),
            ],
        ]);

        $commands = $this->registry->getCommands(search: 'alpha', limit: false);

        $this->assertCount(1, $commands);
        $this->assertSame('Alpha', $commands->first()->group);
    }

    public function test_search_is_case_insensitive(): void
    {
        config()->set('command-palette.custom_commands', [
            fn () => [
                CommandItem::make('Dashboard', '/dashboard'),
            ],
        ]);

        $commands = $this->registry->getCommands(search: 'DASHBOARD', limit: false);

        $this->assertCount(1, $commands);
    }

    public function test_empty_search_returns_all_commands(): void
    {
        config()->set('command-palette.custom_commands', [
            fn () => [
                CommandItem::make('One', '/one'),
                CommandItem::make('Two', '/two'),
            ],
        ]);

        $commands = $this->registry->getCommands(search: null, limit: false);

        $this->assertGreaterThanOrEqual(2, $commands->count());
    }

    public function test_limit_respects_max_results_config(): void
    {
        config()->set('command-palette.max_results', 2);
        config()->set('command-palette.custom_commands', [
            fn () => [
                CommandItem::make('One', '/one'),
                CommandItem::make('Two', '/two'),
                CommandItem::make('Three', '/three'),
            ],
        ]);

        $commands = $this->registry->getCommands(search: null, limit: true);

        $this->assertCount(2, $commands);
    }

    public function test_duplicate_urls_are_deduplicated(): void
    {
        config()->set('command-palette.custom_commands', [
            fn () => [
                CommandItem::make('Dashboard', '/dashboard', 'Nav'),
                CommandItem::make('Home', '/dashboard', 'Other'),
            ],
        ]);

        $commands = $this->registry->getAllCommands();

        $urls = $commands->pluck('url')->all();
        $this->assertCount(1, array_filter($urls, fn ($u) => $u === '/dashboard'));
    }

    public function test_custom_commands_throwing_are_caught(): void
    {
        config()->set('command-palette.custom_commands', [
            fn () => throw new \RuntimeException('Broken'),
            fn () => [
                CommandItem::make('Valid', '/valid'),
            ],
        ]);

        $commands = $this->registry->getAllCommands();

        $labels = $commands->pluck('label')->all();
        $this->assertContains('Valid', $labels);
    }

    public function test_get_commands_with_panel_null_does_not_throw(): void
    {
        config()->set('command-palette.custom_commands', []);

        $commands = $this->registry->getCommands(search: null, limit: true, panel: null);

        $this->assertInstanceOf(Collection::class, $commands);
    }
}
