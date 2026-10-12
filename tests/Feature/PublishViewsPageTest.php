<?php

namespace Usamamuneerchaudhary\CommandPalette\Tests\Feature;

use Usamamuneerchaudhary\CommandPalette\Filament\Pages\PublishCommandPaletteViewsPage;
use Usamamuneerchaudhary\CommandPalette\Tests\TestCase;

class PublishViewsPageTest extends TestCase
{
    public function test_the_publish_views_page_is_denied_outside_local(): void
    {
        config()->set('command-palette.include_publish_views_command', true);
        $this->app['env'] = 'testing';

        $this->assertFalse(PublishCommandPaletteViewsPage::canAccess());
    }

    public function test_the_publish_views_page_is_allowed_in_local_when_enabled(): void
    {
        config()->set('command-palette.include_publish_views_command', true);
        $this->app['env'] = 'local';

        $this->assertTrue(PublishCommandPaletteViewsPage::canAccess());
    }

    public function test_the_publish_views_page_is_denied_when_the_command_is_disabled(): void
    {
        config()->set('command-palette.include_publish_views_command', false);
        $this->app['env'] = 'local';

        $this->assertFalse(PublishCommandPaletteViewsPage::canAccess());
    }
}
