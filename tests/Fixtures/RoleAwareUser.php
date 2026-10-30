<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Fixtures;

use Illuminate\Support\Collection;

class RoleAwareUser extends PaletteUser
{
    /** @var list<string> */
    public array $roleNames = [];

    /**
     * @return Collection<int, string>
     */
    public function getRoleNames(): Collection
    {
        return collect($this->roleNames);
    }
}
