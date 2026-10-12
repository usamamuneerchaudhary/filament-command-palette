<?php

namespace Usamamuneerchaudhary\CommandPalette\Tests\Unit;

use Filament\Panel;
use Usamamuneerchaudhary\CommandPalette\Support\PanelAccess;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\PaletteUser;
use Usamamuneerchaudhary\CommandPalette\Tests\TestCase;

class PanelAccessTest extends TestCase
{
    public function test_guests_are_denied(): void
    {
        $this->assertFalse(PanelAccess::check(Panel::make()->id('admin')));
    }

    public function test_filament_users_must_be_allowed_onto_the_panel(): void
    {
        $panel = Panel::make()->id('admin');
        $user = new PaletteUser;
        $user->allowedPanels = ['admin'];

        $this->assertTrue(PanelAccess::check($panel, $user));

        $user->allowedPanels = ['app'];

        $this->assertFalse(PanelAccess::check($panel, $user));
    }

    public function test_non_filament_users_are_allowed(): void
    {
        $this->assertTrue(PanelAccess::check(Panel::make()->id('admin'), new \stdClass));
    }
}
