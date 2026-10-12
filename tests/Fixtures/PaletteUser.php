<?php

namespace Usamamuneerchaudhary\CommandPalette\Tests\Fixtures;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;

class PaletteUser extends Authenticatable implements FilamentUser
{
    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;

    /** @var list<string>|null */
    public ?array $allowedPanels = null;

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->allowedPanels === null) {
            return true;
        }

        return in_array($panel->getId(), $this->allowedPanels, true);
    }
}
