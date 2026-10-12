<?php

namespace Usamamuneerchaudhary\CommandPalette\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

class CommandItemTest extends TestCase
{
    public function test_it_builds_a_link_command(): void
    {
        $item = CommandItem::make('Dashboard', '/admin', 'Navigation');

        $this->assertSame('Dashboard', $item->label);
        $this->assertSame('/admin', $item->url);
        $this->assertSame('Navigation', $item->group);
        $this->assertSame(CommandItem::TYPE_LINK, $item->getType());
    }

    public function test_it_defaults_the_group_when_null_is_passed(): void
    {
        $this->assertSame('Navigation', CommandItem::make('X', '/x', null)->group);
    }

    public function test_an_item_with_a_closure_is_an_action(): void
    {
        $item = CommandItem::make('Clear cache')->action(fn () => 'done');

        $this->assertSame(CommandItem::TYPE_ACTION, $item->getType());
        $this->assertNotNull($item->getAction());
    }

    public function test_an_item_with_an_event_is_an_event_command(): void
    {
        $item = CommandItem::make('Dark mode')->dispatch('set-theme', ['theme' => 'dark']);

        $this->assertSame(CommandItem::TYPE_EVENT, $item->getType());
    }

    public function test_ids_are_stable_across_instances(): void
    {
        $a = CommandItem::make('Dashboard', '/admin', 'Navigation');
        $b = CommandItem::make('Dashboard', '/admin', 'Navigation');

        $this->assertSame($a->getId(), $b->getId());
    }

    public function test_ids_differ_when_the_url_differs(): void
    {
        $a = CommandItem::make('Dashboard', '/admin');
        $b = CommandItem::make('Dashboard', '/app');

        $this->assertNotSame($a->getId(), $b->getId());
    }

    public function test_an_explicit_id_wins(): void
    {
        $this->assertSame('custom', CommandItem::make('X', '/x')->id('custom')->getId());
    }

    public function test_visibility_and_authorization_are_evaluated(): void
    {
        $this->assertTrue(CommandItem::make('A', '/a')->isVisible());
        $this->assertFalse(CommandItem::make('B', '/b')->visible(false)->isVisible());
        $this->assertFalse(CommandItem::make('C', '/c')->hidden()->isVisible());
        $this->assertFalse(CommandItem::make('D', '/d')->authorize(fn () => false)->isVisible());
    }

    public function test_an_unauthorized_item_is_never_visible(): void
    {
        $item = CommandItem::make('E', '/e')->visible(true)->authorize(false);

        $this->assertFalse($item->isVisible());
        $this->assertFalse($item->isAuthorized());
    }

    public function test_keywords_are_normalised_from_a_string_or_array(): void
    {
        $this->assertSame(['new'], CommandItem::make('X')->keywords('new')->keywords);
        $this->assertSame(['a', 'b'], CommandItem::make('X')->keywords(['a', 'b'])->keywords);
    }

    public function test_the_payload_never_contains_closures(): void
    {
        $payload = CommandItem::make('Clear cache')
            ->action(fn () => null)
            ->authorize(fn () => true)
            ->visible(fn () => true)
            ->toArray();

        array_walk_recursive($payload, function ($value): void {
            $this->assertNotInstanceOf(\Closure::class, $value);
        });

        $this->assertArrayHasKey('id', $payload);
        $this->assertSame(CommandItem::TYPE_ACTION, $payload['type']);
    }

    public function test_backed_enum_icons_are_flattened_for_the_client(): void
    {
        $item = CommandItem::make('X', '/x')->icon(FakeIcon::Star);

        $this->assertSame('fake-star', $item->toArray()['icon']);
    }
}

enum FakeIcon: string
{
    case Star = 'fake-star';
}
