<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Feature;

use Filament\Panel;
use Livewire\Livewire;
use Usamamuneerchaudhary\CommandPalette\Contracts\CommandProvider;
use Usamamuneerchaudhary\CommandPalette\Livewire\CommandPalette;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\ClearCacheCommand;

beforeEach(function () {

    config()->set('command-palette.include_publish_views_command', false);
    config()->set('command-palette.search_providers', []);
    config()->set('command-palette.cache.ttl', 0);

    $this->actingAsPaletteUser();
});

test('an authorized mount action is returned to the client', function () {
    $item = CommandItem::make('Create user')
        ->id('mount:create')
        ->mountAction('create', ['source' => 'palette']);

    config()->set('command-palette.providers', [new MountActionProvider([$item])]);

    $result = Livewire::test(CommandPalette::class)
        ->instance()
        ->runCommand($item->getId());

    $this->assertSame('create', $result['mountAction']);
    $this->assertSame('palette', $result['arguments']['source']);
});

test('invokable command classes run on the server', function () {
    ClearCacheCommand::$ran = false;

    $item = CommandItem::make('Clear cache')
        ->id('invoke:cache')
        ->action(ClearCacheCommand::class);

    config()->set('command-palette.providers', [new MountActionProvider([$item])]);

    $instance = Livewire::test(CommandPalette::class)->instance();
    $instance->runCommand($item->getId());

    $this->assertTrue(ClearCacheCommand::$ran);
    $this->assertSame('/cache-cleared', \Livewire\store($instance)->get('redirect'));
});
class MountActionProvider implements CommandProvider
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
