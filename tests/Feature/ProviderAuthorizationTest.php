<?php

namespace Usamamuneerchaudhary\CommandPalette\Tests\Feature;

use Filament\Panel;
use Usamamuneerchaudhary\CommandPalette\Support\Providers\PageCommandProvider;
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
}

class TestableResourceCommandProvider extends ResourceCommandProvider
{
    protected function url(string $resource, string $name, mixed $tenant): ?string
    {
        return '/admin/'.class_basename($resource).'/'.$name;
    }
}
