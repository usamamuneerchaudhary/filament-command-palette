<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Feature;

use Filament\Panel;
use Livewire\Livewire;

use function Livewire\store;

use Usamamuneerchaudhary\CommandPalette\Contracts\CommandProvider;
use Usamamuneerchaudhary\CommandPalette\Exceptions\CommandNotAuthorized;
use Usamamuneerchaudhary\CommandPalette\Livewire\CommandPalette;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

beforeEach(function () {

    config()->set('command-palette.include_publish_views_command', false);
    config()->set('command-palette.search_providers', []);
    config()->set('command-palette.cache.ttl', 0);

    $this->actingAsPaletteUser();
});

test('children load from the server and leaves execute', function () {
    $parent = CommandItem::make('Assign role')
        ->id('assign-role')
        ->children([
            CommandItem::make('Admin')
                ->id('assign-role:admin')
                ->action(fn (): string => '/assigned-admin'),
        ]);

    nestedUseCommands([$parent]);

    $instance = Livewire::test(CommandPalette::class)->instance();
    $children = $instance->loadChildren('assign-role');

    $this->assertSame('assign-role:admin', $children['commands'][0]['id']);
    $this->assertArrayHasKey('icons', $children);

    $instance->runCommand('assign-role:admin');

    $this->assertSame('/assigned-admin', store($instance)->get('redirect'));
});

test('loading children for an unknown id is rejected', function () {
    nestedUseCommands([CommandItem::make('Dashboard', '/dashboard')]);

    $this->expectException(CommandNotAuthorized::class);

    Livewire::test(CommandPalette::class)->instance()->loadChildren('missing');
});

test('submenu depth is capped', function () {
    $leaf = CommandItem::make('Leaf')->id('depth:9')->action(fn (): string => '/leaf');
    $current = $leaf;

    for ($depth = 8; $depth >= 0; $depth--) {
        $current = CommandItem::make('Level '.$depth)
            ->id('depth:'.$depth)
            ->children([$current]);
    }

    nestedUseCommands([$current]);

    $instance = Livewire::test(CommandPalette::class)->instance();
    $atCap = $instance->loadChildren('depth:7');

    $this->assertSame('depth:8', $atCap['commands'][0]['id']);
    $this->assertFalse($atCap['commands'][0]['hasChildren']);

    $this->expectException(CommandNotAuthorized::class);

    $instance->runCommand('depth:9');
});

function nestedUseCommands(array $items): void
{
    config()->set('command-palette.providers', [new NestedCommandProvider($items)]);
}

class NestedCommandProvider implements CommandProvider
{
    /**
     * @param  array<int, CommandItem>  $items
     */
    public function __construct(protected array $items) {}

    public function commands(Panel $panel): iterable
    {
        return $this->items;
    }
}
