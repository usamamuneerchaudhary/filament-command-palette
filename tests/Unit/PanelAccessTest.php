<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Unit;

use Filament\Panel;
use Usamamuneerchaudhary\CommandPalette\Support\PanelAccess;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\PaletteUser;

test('guests are denied', function () {
    $this->assertFalse(PanelAccess::check(Panel::make()->id('admin')));
});

test('filament users must be allowed onto the panel', function () {
    $panel = Panel::make()->id('admin');
    $user = new PaletteUser;
    $user->allowedPanels = ['admin'];

    $this->assertTrue(PanelAccess::check($panel, $user));

    $user->allowedPanels = ['app'];

    $this->assertFalse(PanelAccess::check($panel, $user));
});

test('non filament users are allowed', function () {
    $this->assertTrue(PanelAccess::check(Panel::make()->id('admin'), new \stdClass));
});
