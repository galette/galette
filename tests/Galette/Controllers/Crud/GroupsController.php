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

    /**
     * Test group managers cannot add members out of their scope
     */
    public function testGroupManagerEditScope(): void
    {
        $this->preferences->pref_bool_groupsmanagers_edit_groups = true;

        $group = $this->logGroupManager();
        $this->login->logOut();
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();

        //member two is a co-manager, out of member one scope
        $this->logSuperAdmin();
        $this->assertTrue($group->setManagers([$member_one, $member_two]));
        $this->login->logOut();

        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));

        $request = $this->createRequest('doEditGroup', ['id' => (string)$group->getId()], 'POST');
        $request = $request->withParsedBody([
            'group_name' => $group->getName(),
            'parent_group' => '',
            'managers' => [$member_one->id, $member_two->id],
            'members' => [$member_two->id]
        ]);
        $test_response = $this->app->handle($request);
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog\Analog::WARNING, 'requested 1 member(s), only 0 are accessible or exist.');
        $this->login->logOut();

        $group = new \Galette\Entity\Group($group->getId());
        $managers = array_map(fn($m) => (int)$m->id, $group->getManagers());
        sort($managers);
        $expected = [(int)$member_one->id, (int)$member_two->id];
        sort($expected);
        //existing co-manager is kept...
        $this->assertSame($expected, $managers);
        //...but out of scope member has not been added
        $this->assertCount(0, $group->getMembers());

        $this->preferences->pref_bool_groupsmanagers_edit_groups = false;
    }

    /**
     * Test groups are reordered only by those who can edit them
     */
    public function testReorderPermissions(): void
    {
        $group = $this->logGroupManager();
        $this->login->logOut();

        $other = new \Galette\Entity\Group();
        $other->setName('Not managed');
        $this->assertTrue($other->store());

        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($group->getId()));
        $this->assertFalse($this->login->isGroupManager($other->getId()));

        $edit_groups = $this->preferences->pref_bool_groupsmanagers_edit_groups;
        foreach ([false, true] as $allowed) {
            $this->preferences->pref_bool_groupsmanagers_edit_groups = $allowed;

            //group not managed cannot be moved
            $request = $this->createRequest('reorderGroups', method: 'POST')
                ->withParsedBody(['reordered' => [$other->getId() . '|' . $group->getId()]]);
            $test_response = $this->app->handle($request);
            $this->assertSame(301, $test_response->getStatusCode());
            $this->flash_data = [];
            $this->expectLogEntry(
                \Analog\Analog::WARNING,
                'Trying to reorder group ' . $other->getId() . ' without appropriate permissions'
            );
            $this->assertNull((new \Galette\Entity\Group($other->getId()))->getParentGroup());

            //managed group can be moved when preferences allow it
            $request = $this->createRequest('reorderGroups', method: 'POST')
                ->withParsedBody(['reordered' => [$group->getId() . '|' . $other->getId()]]);
            $test_response = $this->app->handle($request);
            $this->assertSame(301, $test_response->getStatusCode());
            $this->flash_data = [];
            $parent = (new \Galette\Entity\Group($group->getId()))->getParentGroup();
            $this->assertSame($allowed ? $other->getId() : null, $parent?->getId());
            if (!$allowed) {
                $this->expectLogEntry(
                    \Analog\Analog::WARNING,
                    'Trying to reorder group ' . $group->getId() . ' without appropriate permissions'
                );
            }
        }
        $this->preferences->pref_bool_groupsmanagers_edit_groups = $edit_groups;
        $this->login->logOut();
    }
}
