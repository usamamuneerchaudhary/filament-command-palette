<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Unit;

use Filament\Panel;
use Illuminate\Support\Collection;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;
use Usamamuneerchaudhary\CommandPalette\Support\Providers\ActionCommandProvider;

test('enabled tenant switching is a submenu', function () {
    $panel = Panel::make()->id('admin')->path('admin');
    $menu = (new TenantMenuProvider)->menuFor($panel, 'Actions');

    $this->assertInstanceOf(CommandItem::class, $menu);
    $this->assertSame('action:tenants', $menu->getId());
    $this->assertTrue($menu->hasChildren());
    $this->assertSame('action:tenant:1', $menu->resolveChildren()[0]->getId());
});

test('an empty tenant list does not add a parent', function () {
    $panel = Panel::make()->id('admin')->path('admin');

    $this->assertNull((new EmptyTenantMenuProvider)->menuFor($panel, 'Actions'));
});
class TenantMenuProvider extends ActionCommandProvider
{
    public function menuFor(Panel $panel, string $group): ?CommandItem
    {
        return $this->tenantMenu($panel, $group);
    }

    protected function tenantCommands(Panel $panel, string $group): Collection
    {
        return collect([
            CommandItem::make('Acme', '/admin/tenants/1', $group)->id('action:tenant:1'),
        ]);
    }
}

class EmptyTenantMenuProvider extends ActionCommandProvider
{
    public function menuFor(Panel $panel, string $group): ?CommandItem
    {
        return $this->tenantMenu($panel, $group);
    }

    protected function tenantCommands(Panel $panel, string $group): Collection
    {
        return collect();
    }
}
