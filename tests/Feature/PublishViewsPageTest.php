<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Feature;

use Usamamuneerchaudhary\CommandPalette\Filament\Pages\PublishCommandPaletteViewsPage;

test('the publish views page is denied outside local', function () {
    config()->set('command-palette.include_publish_views_command', true);
    $this->app['env'] = 'testing';

    $this->assertFalse(PublishCommandPaletteViewsPage::canAccess());
});

test('the publish views page is allowed in local when enabled', function () {
    config()->set('command-palette.include_publish_views_command', true);
    $this->app['env'] = 'local';

    $this->assertTrue(PublishCommandPaletteViewsPage::canAccess());
});

test('the publish views page is denied when the command is disabled', function () {
    config()->set('command-palette.include_publish_views_command', false);
    $this->app['env'] = 'local';

    $this->assertFalse(PublishCommandPaletteViewsPage::canAccess());
});
