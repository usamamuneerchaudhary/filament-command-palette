<?php

namespace Usamamuneerchaudhary\CommandPalette\Tests\Feature;

use Filament\Panel;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

use function Livewire\store;

use Usamamuneerchaudhary\CommandPalette\Contracts\CommandProvider;
use Usamamuneerchaudhary\CommandPalette\Events\CommandExecuted;
use Usamamuneerchaudhary\CommandPalette\Exceptions\CommandNotAuthorized;
use Usamamuneerchaudhary\CommandPalette\Livewire\CommandPalette;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;
use Usamamuneerchaudhary\CommandPalette\Tests\TestCase;

class CommandPaletteComponentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('command-palette.include_publish_views_command', false);
        config()->set('command-palette.search_providers', []);
        config()->set('command-palette.cache.ttl', 0);

        $this->actingAsPaletteUser();
    }

    protected function useCommands(array $items): void
    {
        config()->set('command-palette.providers', [new FeatureProvider($items)]);
    }

    public function test_the_initial_render_carries_no_command_payload(): void
    {
        $this->useCommands([CommandItem::make('Secret page', '/secret')]);

        Livewire::test(CommandPalette::class)
            ->assertDontSee('Secret page')
            ->assertDontSee('/secret');
    }

    public function test_commands_load_on_demand(): void
    {
        $this->useCommands([CommandItem::make('Dashboard', '/dashboard')]);

        $response = Livewire::test(CommandPalette::class)->instance()->loadCommands();

        $this->assertSame('Dashboard', $response['commands'][0]['label']);
        $this->assertArrayHasKey('icons', $response);
    }

    public function test_running_an_unauthorized_command_is_rejected(): void
    {
        $forbidden = CommandItem::make('Forbidden', '/nope')->authorize(fn () => false);

        $this->useCommands([$forbidden]);

        $this->expectException(CommandNotAuthorized::class);

        Livewire::test(CommandPalette::class)->instance()->runCommand($forbidden->getId());
    }

    public function test_running_an_unknown_id_is_rejected(): void
    {
        $this->useCommands([CommandItem::make('Dashboard', '/dashboard')]);

        $this->expectException(CommandNotAuthorized::class);

        Livewire::test(CommandPalette::class)->instance()->runCommand('not-a-real-id');
    }

    public function test_running_a_link_command_redirects(): void
    {
        $item = CommandItem::make('Dashboard', '/dashboard');

        $this->useCommands([$item]);

        $instance = Livewire::test(CommandPalette::class)->instance();
        $instance->runCommand($item->getId());

        $this->assertSame('/dashboard', store($instance)->get('redirect'));
    }

    public function test_an_action_closure_runs_on_the_server(): void
    {
        $ran = false;

        $item = CommandItem::make('Clear cache')->action(function () use (&$ran) {
            $ran = true;
        });

        $this->useCommands([$item]);

        Livewire::test(CommandPalette::class)->call('runCommand', $item->getId());

        $this->assertTrue($ran);
    }

    public function test_an_action_returning_a_string_redirects(): void
    {
        $item = CommandItem::make('Sign out')->action(fn () => '/login');

        $this->useCommands([$item]);

        $instance = Livewire::test(CommandPalette::class)->instance();
        $instance->runCommand($item->getId());

        $this->assertSame('/login', store($instance)->get('redirect'));
    }

    public function test_it_dispatches_an_event_when_a_command_runs(): void
    {
        Event::fake([CommandExecuted::class]);

        $item = CommandItem::make('Dashboard', '/dashboard');

        $this->useCommands([$item]);

        Livewire::test(CommandPalette::class)->call('runCommand', $item->getId());

        Event::assertDispatched(CommandExecuted::class);
    }

    public function test_the_panel_id_cannot_be_written_from_the_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(CommandPalette::class, ['panelId' => 'admin'])
            ->set('panelId', 'secret');
    }

    public function test_a_user_who_cannot_access_the_panel_is_rejected(): void
    {
        $user = $this->actingAsPaletteUser();
        $user->allowedPanels = [];

        $this->expectException(CommandNotAuthorized::class);

        Livewire::test(CommandPalette::class)->instance()->loadCommands();
    }

    public function test_short_queries_skip_the_record_search(): void
    {
        $this->useCommands([]);

        $results = Livewire::test(CommandPalette::class)->instance()->searchRecords('a');

        $this->assertSame([], $results);
    }

    public function test_the_rendered_palette_exposes_combobox_semantics(): void
    {
        foreach (CommandPalette::TEMPLATES as $template) {
            config()->set('command-palette.template', $template);

            Livewire::test(CommandPalette::class)
                ->assertSeeHtml('role="combobox"')
                ->assertSeeHtml('aria-activedescendant')
                ->assertSeeHtml('role="listbox"')
                ->assertSeeHtml('is-selected');
        }
    }
}

class FeatureProvider implements CommandProvider
{
    public function __construct(protected array $items) {}

    public function commands(Panel $panel): iterable
    {
        return $this->items;
    }
}
