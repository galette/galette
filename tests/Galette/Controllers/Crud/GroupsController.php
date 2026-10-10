<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers\Crud;

use Galette\Tests\GaletteRoutingTestCase;

use function Safe\preg_match_all;

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
     * Test group managers cannot change managers nor members from the group page
     */
    public function testGroupManagerEditScope(): void
    {
        $this->preferences->pref_bool_groupsmanagers_edit_groups = true;

        $group = $this->logGroupManager();
        $this->login->logOut();
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();

        //member two is a simple member of the group, so in member one scope
        $this->logSuperAdmin();
        $this->assertTrue($group->setMembers([$member_two]));
        $this->login->logOut();

        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));

        //group form posts current managers and members back: no change, no warning
        $request = $this->createRequest('doEditGroup', ['id' => (string)$group->getId()], 'POST');
        $request = $request->withParsedBody([
            'group_name' => 'Renamed by member one',
            'parent_group' => '',
            'managers' => [$member_one->id],
            'members' => [$member_two->id]
        ]);
        $test_response = $this->app->handle($request);
        $this->assertSame(301, $test_response->getStatusCode());
        $this->flash_data = [];
        $this->assertSame('Renamed by member one', (new \Galette\Entity\Group($group->getId()))->getName());

        //forged request: member two promoted as co-manager, and removed from members
        $request = $this->createRequest('doEditGroup', ['id' => (string)$group->getId()], 'POST');
        $request = $request->withParsedBody([
            'group_name' => 'Renamed by member one',
            'parent_group' => '',
            'managers' => [$member_one->id, $member_two->id],
            'members' => [$member_one->id]
        ]);
        $test_response = $this->app->handle($request);
        $this->assertSame(301, $test_response->getStatusCode());
        $this->flash_data = [];
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Trying to change managers of group ' . $group->getId() . ' without appropriate permissions'
        );
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Trying to change members of group ' . $group->getId() . ' without appropriate permissions'
        );
        $this->login->logOut();

        $group = new \Galette\Entity\Group($group->getId());
        $this->assertSame(
            [(int)$member_one->id],
            array_map(fn($m) => (int)$m->id, $group->getManagers())
        );
        $this->assertSame(
            [(int)$member_two->id],
            array_map(fn($m) => (int)$m->id, $group->getMembers())
        );
        $member_two->loadGroups();
        $this->assertSame([], $member_two->getManagedGroups());

        //staff still manages managers and members from the group page
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertSame(301, $test_response->getStatusCode());
        $this->flash_data = [];
        $this->login->logOut();

        $group = new \Galette\Entity\Group($group->getId());
        $managers = array_map(fn($m) => (int)$m->id, $group->getManagers());
        sort($managers);
        $expected = [(int)$member_one->id, (int)$member_two->id];
        sort($expected);
        $this->assertSame($expected, $managers);
        $this->assertSame(
            [(int)$member_one->id],
            array_map(fn($m) => (int)$m->id, $group->getMembers())
        );

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

    /**
     * Test group managers only see their groups and their ancestors in the list
     */
    public function testListScope(): void
    {
        $group = $this->logGroupManager();
        $this->login->logOut();

        $this->logSuperAdmin();
        $created = [];
        foreach (
            [
                'Scope root' => null,
                'Scope parent' => 'Scope root',
                'Scope sibling' => 'Scope root',
                'Scope child' => $group->getName(),
                'Unrelated root' => null,
                'Unrelated child' => 'Unrelated root'
            ] as $name => $parent
        ) {
            $created[$name] = new \Galette\Entity\Group();
            $created[$name]->setName($name);
            if ($parent !== null) {
                $created[$name]->setParentGroup(($created[$parent] ?? $group)->getId());
            }
            $this->assertTrue($created[$name]->store());
        }
        $group->setParentGroup($created['Scope parent']->getId());
        $this->assertTrue($group->store());

        $listed = function (string $body): array {
            preg_match_all('/<tr data-label="([^"]+)" data-group=/', $body, $matches);
            return $matches[1];
        };

        //staff sees every group
        $test_response = $this->app->handle($this->createRequest('groups'));
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertSame(
            [
                'Scope root',
                'Scope parent',
                $group->getName(),
                'Scope child',
                'Scope sibling',
                'Unrelated root',
                'Unrelated child'
            ],
            $listed($body)
        );
        $this->login->logOut();

        //group manager sees managed group and its ancestors only
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $test_response = $this->app->handle($this->createRequest('groups'));
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertSame(['Scope root', 'Scope parent', $group->getName()], $listed($body));
        $this->assertStringNotContainsString('Scope sibling', $body);
        $this->assertStringNotContainsString('Unrelated', $body);
        $this->assertStringContainsString(
            $this->routeparser->urlFor('editGroup', ['id' => (string)$group->getId()]),
            $body
        );
        $this->assertStringNotContainsString(
            $this->routeparser->urlFor('editGroup', ['id' => (string)$created['Scope parent']->getId()]),
            $body
        );
        $this->login->logOut();
    }
}
