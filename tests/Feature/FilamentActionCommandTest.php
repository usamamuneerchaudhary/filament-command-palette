<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Feature;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Panel;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Usamamuneerchaudhary\CommandPalette\Contracts\CommandProvider;
use Usamamuneerchaudhary\CommandPalette\Exceptions\CommandNotAuthorized;
use Usamamuneerchaudhary\CommandPalette\Livewire\CommandPalette;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;
use Usamamuneerchaudhary\CommandPalette\Support\Providers\RecordCommandProvider;
use Usamamuneerchaudhary\CommandPalette\Support\RecordReference;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\PaletteUser;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\SearchableUserResource;

beforeEach(function () {
    config()->set('command-palette.include_publish_views_command', false);
    config()->set('command-palette.search_providers', []);
    config()->set('command-palette.cache.ttl', 0);

    $this->actingAsPaletteUser();
});

test('a filament action command mounts on the palette', function () {
    PaletteActionSpy::$ran = false;

    $item = CommandItem::make('Confirm thing')
        ->id('filament:confirm')
        ->filamentAction(fn (): Action => Action::make('confirm')
            ->requiresConfirmation()
            ->action(function (): void {
                PaletteActionSpy::$ran = true;
            }));

    config()->set('command-palette.providers', [new FilamentActionProvider([$item])]);

    $component = Livewire::test(CommandPalette::class);
    $component->call('mountPaletteAction', $item->getId());
    $component->assertActionMounted(CommandPalette::PALETTE_ACTION);
    $component->callMountedAction();

    expect(PaletteActionSpy::$ran)->toBeTrue();
});

test('an unauthorized filament action is rejected', function () {
    $item = CommandItem::make('Forbidden')
        ->id('filament:forbidden')
        ->filamentAction(fn (): Action => Action::make('forbidden')->action(function (): void {}))
        ->authorize(false);

    config()->set('command-palette.providers', [new FilamentActionProvider([$item])]);

    $this->expectException(CommandNotAuthorized::class);

    Livewire::test(CommandPalette::class)->instance()->mountPaletteAction($item->getId());
});

test('a non action command cannot be mounted as a filament action', function () {
    $item = CommandItem::make('Dashboard', '/dashboard')->id('link:dashboard');

    config()->set('command-palette.providers', [new FilamentActionProvider([$item])]);

    $this->expectException(CommandNotAuthorized::class);

    Livewire::test(CommandPalette::class)->instance()->mountPaletteAction($item->getId());
});

test('the palette action id cannot be written from the client', function () {
    $item = CommandItem::make('Confirm thing')
        ->id('filament:confirm')
        ->filamentAction(fn (): Action => Action::make('confirm')->action(function (): void {}));

    config()->set('command-palette.providers', [new FilamentActionProvider([$item])]);

    $this->expectException(CannotUpdateLockedPropertyException::class);

    Livewire::test(CommandPalette::class)
        ->call('mountPaletteAction', $item->getId())
        ->set('paletteActionId', 'filament:other');
});

test('delete from a record submenu mounts a filament action', function () {
    config()->set('command-palette.search_providers', [
        RecordCommandProvider::class,
    ]);
    Filament::getPanel('admin')->resources([SearchableUserResource::class]);

    $user = PaletteUser::query()->create([
        'name' => 'Ada',
        'email' => 'ada@example.com',
    ]);

    $parentId = RecordReference::id(SearchableUserResource::class, $user->getKey());
    $deleteId = RecordReference::childId(SearchableUserResource::class, $user->getKey(), 'delete');

    $component = Livewire::test(CommandPalette::class);
    $component->call('mountPaletteAction', $deleteId, null, null, null, $parentId);
    $component->assertActionMounted(CommandPalette::PALETTE_ACTION);
    $component->callMountedAction();

    expect(PaletteUser::query()->whereKey($user->getKey())->exists())->toBeFalse();
});

class FilamentActionProvider implements CommandProvider
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

class PaletteActionSpy
{
    public static bool $ran = false;
}
