<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Repository;

use Analog\Analog;
use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Groups repository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Groups extends GaletteTestCase
{
    protected int $seed = 855224771456;

    /**
     * Groups provider
     *
     * @return array<int, array{parent_name: string, children: array<string, array<string>>}>
     */
    public static function groupsProvider(): array
    {
        return [
            [
                'parent_name' => 'Europe',
                'children' => [
                    'France' => [
                        'Nord',
                        'Hérault',
                        'Vaucluse',
                        'Gironde'
                    ],
                    'Belgique' => [
                        'Wallonie',
                        'Flandres'
                    ],
                    'Allemagne' => []
                ]
            ], [
                'parent_name' => 'Afrique',
                'children' => []
            ], [
                'parent_name' => 'Amérique',
                'children' => [
                    'États-unis' => [
                        'Californie',
                        'Ohio',
                        'Massachusetts'
                    ],
                    'Mexique' => []
                ]
            ]
        ];
    }

    /**
     * Create groups for tests
     *
     * @param string                       $parent_name Parent name
     * @param array<string, array<string>> $children    Children
     */
    #[DataProvider('groupsProvider')]
    public function testCreateGroups(string $parent_name, array $children): void
    {
        $group = new \Galette\Entity\Group();
        $group->setName($parent_name);
        $this->assertTrue($group->store());
        $parent_id = $group->getId();

        foreach ($children as $child => $subchildren) {
            $group = new \Galette\Entity\Group();
            $group->setName($child);
            $group->setParentGroup($parent_id);
            $this->assertTrue($group->store());
            $sub_id = $group->getId();

            foreach ($subchildren as $subchild) {
                $group = new \Galette\Entity\Group();
                $group->setName($subchild);
                $group->setParentGroup($sub_id);
                $this->assertTrue($group->store());
            }
        }
    }

    /**
     * Test getSimpleList
     */
    public function testGetSimpleList(): void
    {
        $groups = self::groupsProvider();
        foreach ($groups as $group) {
            $this->testCreateGroups($group['parent_name'], $group['children']);
        }

        $list = \Galette\Repository\Groups::getSimpleList();
        $this->assertCount(17, $list);

        foreach ($list as $group_name) {
            $this->assertNotEmpty($group_name);
        }

        $list = \Galette\Repository\Groups::getSimpleList(true);
        $this->assertCount(17, $list);
        foreach ($list as $group) {
            $this->assertInstanceOf(\Galette\Entity\Group::class, $group);
        }
    }

    /**
     * Test getSimpleList
     */
    public function testGetList(): void
    {
        $this->logSuperAdmin();

        $groups = self::groupsProvider();
        foreach ($groups as $group) {
            $this->testCreateGroups($group['parent_name'], $group['children']);
        }

        $groups = new \Galette\Repository\Groups($this->zdb, $this->login);

        $parents_list = $groups->getList(false);
        $this->assertCount(3, $parents_list);

        $parents_list = $groups->getList(true);
        $this->assertCount(17, $parents_list);

        $select = $this->zdb->select(\Galette\Entity\Group::TABLE);
        $select->where(['group_name' => 'Europe']);
        $result = $this->zdb->execute($select)->current();
        $europe = (int)$result->{\Galette\Entity\Group::PK};

        $children_list = $groups->getList(true, $europe);
        $this->assertCount(4, $children_list);

        //set manager on one group, impersonate him, and check it gets only one group
        $this->getMemberOne();
        $group = new \Galette\Entity\Group((int)$europe);
        $this->assertTrue($group->setManagers([$this->adh]));

        $this->login->impersonate($this->adh->id);

        $groups = new \Galette\Repository\Groups($this->zdb, $this->login);
        $parents_list = $groups->getList();
        $this->assertCount(1, $parents_list);
    }

    /**
     * Test group name uniqueness
     */
    public function testUniqueness(): void
    {
        $groups = self::groupsProvider();
        foreach ($groups as $group) {
            $this->testCreateGroups($group['parent_name'], $group['children']);
        }

        $group = new \Galette\Entity\Group();
        $group->setLogin($this->login);
        $unique_name = 'One group to rule them all';
        $group->setName($unique_name);
        $this->assertTrue($group->store());
        $group_id = $group->getId();

        $select = $this->zdb->select(\Galette\Entity\Group::TABLE);
        $select->where(['group_name' => 'Europe']);
        $result = $this->zdb->execute($select)->current();
        $europe = (int)$result->{\Galette\Entity\Group::PK};

        $select = $this->zdb->select(\Galette\Entity\Group::TABLE);
        $select->where(['group_name' => 'France']);
        $result = $this->zdb->execute($select)->current();
        $france = (int)$result->{\Galette\Entity\Group::PK};

        //name already exists - not unique
        $this->assertFalse(\Galette\Repository\Groups::isUnique($this->zdb, $unique_name));
        //name does not exist on another level - unique
        $this->assertTrue(\Galette\Repository\Groups::isUnique($this->zdb, $unique_name, $europe));
        //name is the current one - unique
        $this->assertTrue(\Galette\Repository\Groups::isUnique(
            zdb: $this->zdb,
            name: $unique_name,
            parent: null,
            current: $group_id
        ));

        //tests on another level
        $this->assertFalse(\Galette\Repository\Groups::isUnique($this->zdb, 'Nord', $france));
        $this->assertTrue(\Galette\Repository\Groups::isUnique($this->zdb, 'Creuse', $france));
    }

    /**
     * Test members/groups
     */
    public function testMembersGroups(): void
    {
        $groups = self::groupsProvider();
        foreach ($groups as $group) {
            $this->testCreateGroups($group['parent_name'], $group['children']);
        }

        $france = new \Galette\Entity\Group();
        $this->assertTrue($france->loadFromName('France'));

        $allemagne = new \Galette\Entity\Group();
        $this->assertTrue($allemagne->loadFromName('Allemagne'));

        $member = $this->getMemberOne();
        $member->loadGroups();
        $this->assertSame([], $member->getManagedGroups());
        $this->assertSame([], $member->getGroups());

        //add member to France and Allemagne groups, as simple member
        $this->assertTrue(
            \Galette\Repository\Groups::addMemberToGroups(
                $member,
                [
                    sprintf('%s|%s', $france->getId(), $france->getName()),
                    sprintf('%s|%s', $allemagne->getId(), $allemagne->getName())
                ]
            )
        );

        $member->loadGroups();
        $this->assertSame([], $member->getManagedGroups());
        $this->assertCount(2, $member->getGroups());

        //Add as manager of France
        $this->assertTrue(
            \Galette\Repository\Groups::addMemberToGroups(
                $member,
                [
                    sprintf('%s|%s', $france->getId(), $france->getName())
                ],
                true
            ),
        );

        $member->loadGroups();
        $this->assertCount(1, $member->getManagedGroups());
        $this->assertCount(2, $member->getGroups());

        $member2 = $this->getMemberTwo();
        //Add as manager of France
        $this->assertTrue(
            \Galette\Repository\Groups::addMemberToGroups(
                $member2,
                [
                    sprintf('%s|%s', $france->getId(), $france->getName())
                ],
                true
            ),
        );

        $member2->loadGroups();
        $this->assertCount(1, $member2->getManagedGroups());
        $this->assertCount(0, $member2->getGroups());

        $this->logSuperAdmin();
        $this->login->impersonate($member2->id);

        $groups = new \Galette\Repository\Groups($this->zdb, $this->login);
        $users = $groups->getManagerUsers([$allemagne->getId()]);
        $this->assertEquals([$member->id], $users);
        $users = $groups->getManagerUsers([$france->getId()]);
        $this->assertEquals([$member->id], $users);

        \Galette\Repository\Groups::removeMemberFromGroups($member->id);
        $member->loadGroups();
        $this->assertSame([], $member->getManagedGroups());
        $this->assertSame([], $member->getGroups());
        //make sure old discouraged way still works
        $this->assertSame([], $member->managed_groups);
        $this->expectLogEntry(Analog::WARNING, 'Calling property "managed_groups" directly is discouraged.');
        $this->assertSame([], $member->groups);
        $this->expectLogEntry(Analog::WARNING, 'Calling property "groups" directly is discouraged.');
    }
}
