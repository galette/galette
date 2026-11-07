<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Navigation;

use Galette\Navigation\BatchAction;
use Galette\Navigation\Menu;
use Galette\Navigation\MenuEntry;
use Galette\Navigation\RouteTarget;
use PHPUnit\Framework\TestCase;

/**
 * Navigation data objects tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MenuEntryTest extends TestCase
{
    /**
     * Entries are built from plugins arrays
     */
    public function testFromArray(): void
    {
        $entry = MenuEntry::fromArray([
            'label' => 'Events',
            'title' => 'Manage events',
            'route' => [
                'name' => 'events_events',
                'args' => ['option' => 'page', 2 => 'two'],
                'aliases' => ['events_event_edit', 42]
            ],
            'icon' => 'calendar',
            'badge' => 3,
            'extra_class' => 'delete',
            'unknown' => 'ignored'
        ]);

        $this->assertEquals(
            new MenuEntry(
                label: 'Events',
                route: new RouteTarget('events_events', ['option' => 'page'], ['events_event_edit']),
                title: 'Manage events',
                icon: 'calendar',
                badge: 3,
                extraClass: 'delete'
            ),
            $entry
        );
        $this->assertSame(['events_events', 'events_event_edit'], $entry->getRouteNames());
    }

    /**
     * Entries grouping children have no route
     */
    public function testChildren(): void
    {
        $entry = MenuEntry::fromArray([
            'label' => 'Legal notices',
            'icon' => 'balance scale',
            'children' => [
                ['label' => 'Terms', 'route' => ['name' => 'terms']],
                ['label' => 'Privacy', 'route' => ['name' => 'privacy', 'aliases' => ['privacyEdit']]],
                'not an entry'
            ]
        ]);

        $this->assertNull($entry->route);
        $this->assertCount(2, $entry->children);
        $this->assertSame(['terms', 'privacy', 'privacyEdit'], $entry->getRouteNames());
    }

    /**
     * An entry must lead somewhere
     */
    public function testLeadsNowhere(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Navigation entry "Nowhere" leads nowhere');
        MenuEntry::fromArray(['label' => 'Nowhere']);
    }

    /**
     * An entry must have a label
     */
    public function testMissingLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A navigation entry must have a label');
        MenuEntry::fromArray(['route' => ['name' => 'slash']]);
    }

    /**
     * A route must have a name
     */
    public function testMissingRouteName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A navigation route must have a name');
        MenuEntry::fromArray(['label' => 'Home', 'route' => ['args' => []]]);
    }

    /**
     * Menus get more entries without being changed
     */
    public function testMenuWithItems(): void
    {
        $menu = new Menu('Members', 'users', [new MenuEntry('List', new RouteTarget('members', aliases: ['member']))]);
        $more = $menu->withItems(
            ...MenuEntry::listFromArray([['label' => 'Map', 'route' => ['name' => 'maps_map']], null])
        );

        $this->assertCount(1, $menu->items);
        $this->assertSame('Members', $more->title);
        $this->assertSame('users', $more->icon);
        $this->assertSame(['List', 'Map'], array_column($more->items, 'label'));
        $this->assertSame(['members', 'member', 'maps_map'], $more->getRouteNames());
    }

    /**
     * Batch actions are built from plugins arrays
     */
    public function testBatchActions(): void
    {
        $this->assertEquals(
            [
                new BatchAction('labels', 'Labels', 'address card'),
                new BatchAction(
                    name: 'pdf',
                    label: 'PDF',
                    icon: 'file pdf',
                    title: 'Export as PDF'
                )
            ],
            BatchAction::listFromArray([
                ['name' => 'labels', 'label' => 'Labels', 'icon' => 'address card'],
                ['name' => 'pdf', 'label' => 'PDF', 'icon' => 'file pdf', 'title' => 'Export as PDF'],
                'not an action'
            ])
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A batch action must have a name, a label and an icon');
        BatchAction::listFromArray([['name' => 'nope', 'label' => 'Nope']]);
    }
}
