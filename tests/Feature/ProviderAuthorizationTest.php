<?php

namespace Usamamuneerchaudhary\CommandPalette\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Collection;
use Usamamuneerchaudhary\CommandPalette\Support\Providers\ActionCommandProvider;
use Usamamuneerchaudhary\CommandPalette\Support\Providers\PageCommandProvider;
use Usamamuneerchaudhary\CommandPalette\Support\Providers\RecordCommandProvider;
use Usamamuneerchaudhary\CommandPalette\Support\Providers\ResourceCommandProvider;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\CreateDeniedPostResource;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\HiddenToolsPage;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\InaccessiblePostResource;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\VisiblePostResource;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\VisibleToolsPage;
use Usamamuneerchaudhary\CommandPalette\Tests\TestCase;

class ProviderAuthorizationTest extends TestCase
{
    public function test_resource_commands_respect_view_any_and_create(): void
    {
        $panel = Panel::make()
            ->id('admin')
            ->path('admin')
            ->resources([
                VisiblePostResource::class,
                CreateDeniedPostResource::class,
                InaccessiblePostResource::class,
            ]);

        $labels = collect((new TestableResourceCommandProvider)->commands($panel))
            ->pluck('label')
            ->all();

        $this->assertContains('List Posts', $labels);
        $this->assertContains('Create Post', $labels);
        $this->assertContains('List Articles', $labels);
        $this->assertNotContains('Create Article', $labels);
        $this->assertNotContains('List Secrets', $labels);
        $this->assertNotContains('Create Secret', $labels);
    }

    public function test_page_commands_respect_can_access(): void
    {
        $panel = Panel::make()
            ->id('admin')
            ->path('admin')
            ->pages([
                VisibleToolsPage::class,
                HiddenToolsPage::class,
            ]);

        $labels = collect((new PageCommandProvider)->commands($panel))
            ->pluck('label')
            ->all();

        $this->assertContains('Visible tools', $labels);
        $this->assertNotContains('Hidden tools', $labels);
    }

    public function test_panel_switch_commands_omit_panels_the_user_cannot_access(): void
    {
        $user = $this->actingAsPaletteUser();
        $user->allowedPanels = ['admin'];

        $secret = Panel::make()->id('secret')->path('secret');
        Filament::registerPanel($secret);

        $admin = Filament::getPanel('admin');
        $ids = (new TestableActionCommandProvider)
            ->panelCommandsFor($admin, 'Actions')
            ->map(fn ($item) => $item->getId())
            ->all();

        $this->assertNotContains('action:panel:secret', $ids);
    }

    public function test_record_search_respects_the_configured_minimum_characters(): void
    {
        $provider = new CountingRecordCommandProvider;
        $panel = Panel::make()
            ->id('admin')
            ->path('admin')
            ->resources([VisiblePostResource::class]);

        config()->set('command-palette.record_search.min_characters', 1);
        iterator_to_array($provider->search($panel, 'a', 5));
        $this->assertSame(1, $provider->lookups);

        $provider->lookups = 0;
        config()->set('command-palette.record_search.min_characters', 3);
        iterator_to_array($provider->search($panel, 'ab', 5));
        $this->assertSame(0, $provider->lookups);
    }
}

class TestableResourceCommandProvider extends ResourceCommandProvider
{
    protected function url(string $resource, string $name, mixed $tenant): ?string
    {
        return '/admin/'.class_basename($resource).'/'.$name;
    }
}

class TestableActionCommandProvider extends ActionCommandProvider
{
    public function panelCommandsFor(Panel $current, string $group): Collection
    {
        return $this->panelCommands($current, $group);
    }
}

class CountingRecordCommandProvider extends RecordCommandProvider
{
    public int $lookups = 0;

    protected function isSearchable(string $resource): bool
    {
        $this->lookups++;

        return false;
    }
}
