<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Feature;

use Filament\Panel;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

use function Livewire\store;

use Usamamuneerchaudhary\CommandPalette\Contracts\CommandProvider;
use Usamamuneerchaudhary\CommandPalette\Contracts\SearchesCommands;
use Usamamuneerchaudhary\CommandPalette\Events\CommandExecuted;
use Usamamuneerchaudhary\CommandPalette\Exceptions\CommandNotAuthorized;
use Usamamuneerchaudhary\CommandPalette\Livewire\CommandPalette;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

beforeEach(function () {

    config()->set('command-palette.include_publish_views_command', false);
    config()->set('command-palette.search_providers', []);
    config()->set('command-palette.cache.ttl', 0);

    $this->actingAsPaletteUser();
});

function useCommands(array $items): void
{
    config()->set('command-palette.providers', [new FeatureProvider($items)]);
}
test('the initial render carries no command payload', function () {
    useCommands([CommandItem::make('Secret page', '/secret')]);

    Livewire::test(CommandPalette::class)
        ->assertDontSee('Secret page')
        ->assertDontSee('/secret');
});

test('commands load on demand', function () {
    useCommands([CommandItem::make('Dashboard', '/dashboard')]);

    $response = Livewire::test(CommandPalette::class)->instance()->loadCommands();

    $this->assertSame('Dashboard', $response['commands'][0]['label']);
    $this->assertArrayHasKey('icons', $response);
});

test('running an unauthorized command is rejected', function () {
    $forbidden = CommandItem::make('Forbidden', '/nope')->authorize(fn () => false);

    useCommands([$forbidden]);

    $this->expectException(CommandNotAuthorized::class);

    Livewire::test(CommandPalette::class)->instance()->runCommand($forbidden->getId());
});

test('running an unknown id is rejected', function () {
    useCommands([CommandItem::make('Dashboard', '/dashboard')]);

    $this->expectException(CommandNotAuthorized::class);

    Livewire::test(CommandPalette::class)->instance()->runCommand('not-a-real-id');
});

test('running a link command redirects', function () {
    $item = CommandItem::make('Dashboard', '/dashboard');

    useCommands([$item]);

    $instance = Livewire::test(CommandPalette::class)->instance();
    $instance->runCommand($item->getId());

    $this->assertSame('/dashboard', store($instance)->get('redirect'));
});

test('an action closure runs on the server', function () {
    $ran = false;

    $item = CommandItem::make('Clear cache')->action(function () use (&$ran) {
        $ran = true;
    });

    useCommands([$item]);

    Livewire::test(CommandPalette::class)->call('runCommand', $item->getId());

    $this->assertTrue($ran);
});

test('an action returning a string redirects', function () {
    $item = CommandItem::make('Sign out')->action(fn () => '/login');

    useCommands([$item]);

    $instance = Livewire::test(CommandPalette::class)->instance();
    $instance->runCommand($item->getId());

    $this->assertSame('/login', store($instance)->get('redirect'));
});

test('it dispatches an event when a command runs', function () {
    Event::fake([CommandExecuted::class]);

    $item = CommandItem::make('Dashboard', '/dashboard');

    useCommands([$item]);

    Livewire::test(CommandPalette::class)->call('runCommand', $item->getId());

    Event::assertDispatched(
        CommandExecuted::class,
        fn (CommandExecuted $event): bool => ($event->command['id'] ?? null) === $item->getId()
            && ($event->command['label'] ?? null) === 'Dashboard',
    );
});

test('the executed event is serializable without closures', function () {
    $payload = CommandItem::make('Clear cache')
        ->action(fn () => null)
        ->authorize(fn () => true)
        ->toArray();

    $serialized = serialize(new CommandExecuted($payload, 'admin', 'cache', 1));

    $this->assertIsString($serialized);
    $this->assertInstanceOf(CommandExecuted::class, unserialize($serialized));
});

test('the panel id cannot be written from the client', function () {
    $this->expectException(CannotUpdateLockedPropertyException::class);

    Livewire::test(CommandPalette::class, ['panelId' => 'admin'])
        ->set('panelId', 'secret');
});

test('a user who cannot access the panel is rejected', function () {
    $user = $this->actingAsPaletteUser();
    $user->allowedPanels = [];

    $this->expectException(CommandNotAuthorized::class);

    Livewire::test(CommandPalette::class)->instance()->loadCommands();
});

test('short queries skip the record search', function () {
    useCommands([]);

    $results = Livewire::test(CommandPalette::class)->instance()->searchRecords('a');

    $this->assertSame([], $results);
});

test('record search honours a one character minimum', function () {
    useCommands([]);
    config()->set('command-palette.record_search.min_characters', 1);
    config()->set('command-palette.search_providers', [
        new FeatureSearchProvider([CommandItem::make('Invoice 1', '/invoices/1')]),
    ]);

    $results = Livewire::test(CommandPalette::class)->instance()->searchRecords('i');

    $this->assertSame('Invoice 1', $results[0]['label']);
});

test('record search truncates overlong queries', function () {
    useCommands([]);
    $spy = new SpySearchProvider;
    config()->set('command-palette.record_search.min_characters', 1);
    config()->set('command-palette.record_search.max_characters', 10);
    config()->set('command-palette.search_providers', [$spy]);

    Livewire::test(CommandPalette::class)->instance()->searchRecords(str_repeat('x', 50));

    $this->assertSame(10, mb_strlen((string) $spy->query));
});

test('record search is rate limited', function () {
    useCommands([]);
    config()->set('command-palette.record_search.min_characters', 1);
    config()->set('command-palette.record_search.max_attempts_per_minute', 1);
    config()->set('command-palette.search_providers', [
        new FeatureSearchProvider([CommandItem::make('Invoice 1', '/invoices/1')]),
    ]);

    $component = Livewire::test(CommandPalette::class)->instance();

    $this->assertSame('Invoice 1', $component->searchRecords('in')[0]['label']);
    $this->assertSame([], $component->searchRecords('in'));
});

test('the rendered palette exposes combobox semantics', function () {
    foreach (CommandPalette::TEMPLATES as $template) {
        config()->set('command-palette.template', $template);

        Livewire::test(CommandPalette::class)
            ->assertSeeHtml('role="combobox"')
            ->assertSeeHtml('aria-activedescendant')
            ->assertSeeHtml('role="listbox"')
            ->assertSeeHtml('is-selected');
    }
});

test('the palette opens without a deferred page table', function () {
    useCommands([CommandItem::make('Dashboard', '/dashboard')]);

    $component = Livewire::test(CommandPalette::class);

    $component->assertSeeHtml('filamentCommandPalette');

    $payload = $component->instance()->loadCommands();

    $this->assertSame('Dashboard', $payload['commands'][0]['label']);
});

test('the rendered palette exposes submenu and chord semantics', function () {
    Livewire::test(CommandPalette::class)
        ->assertSeeHtml('aria-label="'.e(__('command-palette::command-palette.a11y.breadcrumb')).'"')
        ->assertSeeHtml('requestClose()')
        ->assertSeeHtml('fi-cp-chord');
});
class FeatureProvider implements CommandProvider
{
    public function __construct(protected array $items) {}

    public function commands(Panel $panel): iterable
    {
        return $this->items;
    }
}

class FeatureSearchProvider implements SearchesCommands
{
    public function __construct(protected array $items) {}

    public function search(Panel $panel, string $query, int $limit): iterable
    {
        return $this->items;
    }
}

class SpySearchProvider implements SearchesCommands
{
    public ?string $query = null;

    public function search(Panel $panel, string $query, int $limit): iterable
    {
        $this->query = $query;

        return [];
    }
}
