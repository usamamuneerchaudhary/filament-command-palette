<?php

namespace Usamamuneerchaudhary\CommandPalette\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

class CommandItemTest extends TestCase
{
    public function test_make_creates_item_with_required_label_and_url(): void
    {
        $item = CommandItem::make('Dashboard', '/dashboard');

        $this->assertInstanceOf(CommandItem::class, $item);
        $this->assertSame('Dashboard', $item->label);
        $this->assertSame('/dashboard', $item->url);
        $this->assertSame('Navigation', $item->group);
        $this->assertFalse($item->openInNewTab);
        $this->assertSame(md5('Dashboard' . '/dashboard'), $item->id);
    }

    public function test_make_accepts_group_as_third_argument(): void
    {
        $item = CommandItem::make('Settings', '/settings', 'Custom');

        $this->assertSame('Custom', $item->group);
    }

    public function test_fluent_group_method(): void
    {
        $item = CommandItem::make('Test', '/test')->group('My Group');

        $this->assertSame('My Group', $item->group);
        $this->assertInstanceOf(CommandItem::class, $item);
    }

    public function test_fluent_icon_method(): void
    {
        $item = CommandItem::make('Test', '/test')->icon('heroicon-o-cog');

        $this->assertSame('heroicon-o-cog', $item->icon);
    }

    public function test_fluent_open_in_new_tab_method(): void
    {
        $item = CommandItem::make('Test', '/test')->openInNewTab(true);

        $this->assertTrue($item->openInNewTab);

        $item->openInNewTab(false);
        $this->assertFalse($item->openInNewTab);
    }

    public function test_fluent_methods_can_be_chained(): void
    {
        $item = CommandItem::make('My Action', '/action')
            ->group('Custom')
            ->icon('heroicon-o-bolt')
            ->openInNewTab(true);

        $this->assertSame('My Action', $item->label);
        $this->assertSame('/action', $item->url);
        $this->assertSame('Custom', $item->group);
        $this->assertSame('heroicon-o-bolt', $item->icon);
        $this->assertTrue($item->openInNewTab);
    }

    public function test_to_array_returns_all_properties(): void
    {
        $item = CommandItem::make('Dashboard', '/dashboard', 'Nav');
        $array = $item->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('label', $array);
        $this->assertArrayHasKey('url', $array);
        $this->assertArrayHasKey('group', $array);
        $this->assertArrayHasKey('icon', $array);
        $this->assertArrayHasKey('openInNewTab', $array);
        $this->assertSame('Dashboard', $array['label']);
        $this->assertSame('/dashboard', $array['url']);
        $this->assertSame('Nav', $array['group']);
    }

    public function test_same_label_and_url_produce_same_id(): void
    {
        $a = CommandItem::make('Dashboard', '/dashboard');
        $b = CommandItem::make('Dashboard', '/dashboard');

        $this->assertSame($a->id, $b->id);
    }

    public function test_different_label_or_url_produce_different_id(): void
    {
        $a = CommandItem::make('Dashboard', '/dashboard');
        $b = CommandItem::make('Settings', '/dashboard');
        $c = CommandItem::make('Dashboard', '/settings');

        $this->assertNotSame($a->id, $b->id);
        $this->assertNotSame($a->id, $c->id);
    }
}
