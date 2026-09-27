<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers\Crud;

use Galette\Tests\GaletteRoutingTestCase;

/**
 * Groups controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class GroupsController extends GaletteRoutingTestCase
{
    protected int $seed = 20260927091500;

    /**
     * Test removal refused by a group.before_remove listener displays its reason
     */
    public function testRemoveBlockedGroup(): void
    {
        global $zdb;
        $zdb = $this->zdb;

        $group = new \Galette\Entity\Group();
        $group->setName('A used group');
        $this->assertTrue($group->store());
        $group_id = $group->getId();

        $emitter = $this->container->get(\League\Event\EventDispatcher::class);
        $emitter->subscribeOnceTo(
            'group.before_remove',
            function (\Galette\Events\GaletteEvent $event): void {
                $event->getObject()->preventRemoval('Group is used by 2 events, it cannot be deleted.');
            }
        );

        $route_name = 'doRemoveGroup';
        $route_arguments = ['id' => (string)$group_id];

        $this->logSuperAdmin();
        $request = $this->createRequest($route_name, $route_arguments, 'POST');
        $request = $request->withParsedBody(['id' => (string)$group_id, 'confirm' => true, 'cascade' => true]);
        $test_response = $this->app->handle($request);
        $this->login->logout();
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('groups')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Group "A used group" cannot be removed: Group is used by 2 events, it cannot be deleted.'
        );
        $this->expectFlashData(['error_detected' => ['Group is used by 2 events, it cannot be deleted.']]);
        $this->assertTrue($group->load($group_id));

        //without listener, group is removed
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->login->logout();
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['success_detected' => ['Successfully deleted!']]);
        $this->assertFalse($group->load($group_id));
    }
}
