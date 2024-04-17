<?php

/**
 * Copyright © 2003-2024 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Galette\Repository\test\units;

use Galette\GaletteTestCase;

/**
 * Groups repository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Groups extends GaletteTestCase
{
    private array $parents = [];
    private array $children = [];
    private array $subchildren = [];
    protected int $seed = 855224771456;

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->deleteGroups();
    }

    /**
     * Delete groups
     *
     * @return void
     */
    private function deleteGroups(): void
    {
        $zdb = new \Galette\Core\Db();

        //Clean managers
        $zdb->db->query(
            'TRUNCATE TABLE ' . PREFIX_DB . \Galette\Entity\Group::GROUPSMANAGERS_TABLE,
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );

        $zdb->db->query(
            'TRUNCATE TABLE ' . PREFIX_DB . \Galette\Entity\Group::GROUPSUSERS_TABLE,
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );

        $groups = self::groupsProvider();
        foreach ($groups as $group) {
            foreach ($group['children'] as $child) {
                $delete = $zdb->delete(\Galette\Entity\Group::TABLE);
                $delete->where->in('group_name', $child);
                $zdb->execute($delete);
            }
            $delete = $zdb->delete(\Galette\Entity\Group::TABLE);
            $delete->where->in('group_name', array_keys($group['children']));
            $zdb->execute($delete);
        }

        $delete = $zdb->delete(\Galette\Entity\Group::TABLE);
        $zdb->execute($delete);

        $delete = $zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $zdb->execute($delete);

        //Clean logs
        $zdb->db->query(
            'TRUNCATE TABLE ' . PREFIX_DB . \Galette\Core\History::TABLE,
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
    }

    /**
     * Groups provider
     *
     * @return array[]
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
     * @param string $parent_name Parent name
     * @param array  $children    Children
     *
     * @dataProvider groupsProvider
     *
     * @return void
     */
    public function testCreateGroups(string $parent_name, array $children): void
    {
        $group = new \Galette\Entity\Group();
        $group->setName($parent_name);
        $this->assertTrue($group->store());
        $parent_id = $group->getId();
        $this->parents[] = $group->getId();

        foreach ($children as $child => $subchildren) {
            $group = new \Galette\Entity\Group();
            $group->setName($child);
            $group->setParentGroup($parent_id);
            $this->assertTrue($group->store());
            $sub_id = $group->getId();
            $this->children[] = $group->getId();

            foreach ($subchildren as $subchild) {
                $group = new \Galette\Entity\Group();
                $group->setName($subchild);
                $group->setParentGroup($sub_id);
                $this->assertTrue($group->store());
                $this->subchildren[] = $group->getId();
            }
        }
    }

    /**
     * Test getSimpleList
     *
     * @return void
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
     *
     * @return void
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
        $europe = $result->{\Galette\Entity\Group::PK};

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
     *
     * @return void
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
        $europe = $result->{\Galette\Entity\Group::PK};

        $select = $this->zdb->select(\Galette\Entity\Group::TABLE);
        $select->where(['group_name' => 'France']);
        $result = $this->zdb->execute($select)->current();
        $france = $result->{\Galette\Entity\Group::PK};

        //name already exists - not unique
        $this->assertFalse(\Galette\Repository\Groups::isUnique($this->zdb, $unique_name));
        //name does not exist on another level - unique
        $this->assertTrue(\Galette\Repository\Groups::isUnique($this->zdb, $unique_name, $europe));
        //name is the current one - unique
        $this->assertTrue(\Galette\Repository\Groups::isUnique($this->zdb, $unique_name, null, $group_id));

        //tests on another level
        $this->assertFalse(\Galette\Repository\Groups::isUnique($this->zdb, 'Nord', $france));
        $this->assertTrue(\Galette\Repository\Groups::isUnique($this->zdb, 'Creuse', $france));
    }

    /**
     * Test members/groups
     *
     * @return void
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
        $this->assertSame([], $member->managed_groups);
        $this->assertSame([], $member->groups);

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
        $this->assertSame([], $member->managed_groups);
        $this->assertCount(2, $member->groups);

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
        $this->assertCount(1, $member->managed_groups);
        $this->assertCount(2, $member->groups);

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
        $this->assertCount(1, $member2->managed_groups);
        $this->assertCount(0, $member2->groups);

        $this->logSuperAdmin();
        $this->login->impersonate($member2->id);

        $groups = new \Galette\Repository\Groups($this->zdb, $this->login);
        $users = $groups->getManagerUsers([$allemagne->getId()]);
        $this->assertSame([$member->id], $users);
        $users = $groups->getManagerUsers([$france->getId()]);
        $this->assertSame([$member->id], $users);

        \Galette\Repository\Groups::removeMemberFromGroups($member->id);
        $member->loadGroups();
        $this->assertSame([], $member->managed_groups);
        $this->assertSame([], $member->groups);
    }
}
