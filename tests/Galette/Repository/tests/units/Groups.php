<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Groups repository tests
 *
 * PHP version 5
 *
 * Copyright © 2021 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Repository
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2021-11-10
 */

namespace Galette\Repository\test\units;

use Galette\GaletteTestCase;

/**
 * Groups repository tests
 *
 * @category  Repository
 * @name      Groups
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2021-11-10
 */
class Groups extends GaletteTestCase
{
    private $parents = [];
    private $children = [];
    private $subchildren = [];
    protected $seed = '855224771456';

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown()
    {
        $this->deleteGroups();
    }

    /**
     * Delete groups
     *
     * @return void
     */
    private function deleteGroups()
    {
        $zdb = new \Galette\Core\Db();

        //Clean managers
        $zdb->db->query(
            'TRUNCATE TABLE ' . PREFIX_DB . \Galette\Entity\Group::GROUPSMANAGERS_TABLE,
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );

        $groups = $this->groupsProvider();
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
    protected function groupsProvider(): array
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
    public function testCreateGroups(string $parent_name, array $children)
    {
        $group = new \Galette\Entity\Group();
        $group->setName($parent_name);
        $this->boolean($group->store())->isTrue();
        $parent_id = $group->getId();
        $this->parents[] = $group->getId();

        foreach ($children as $child => $subchildren) {
            $group = new \Galette\Entity\Group();
            $group->setName($child);
            $group->setParentGroup($parent_id);
            $this->boolean($group->store())->isTrue();
            $sub_id = $group->getId();
            $this->children[] = $group->getId();

            foreach ($subchildren as $subchild) {
                $group = new \Galette\Entity\Group();
                $group->setName($subchild);
                $group->setParentGroup($sub_id);
                $this->boolean($group->store())->isTrue();
                $this->subchildren[] = $group->getId();
            }
        }
    }

    /**
     * Test getSimpleList
     *
     * @return void
     */
    public function testGetSimpleList()
    {
        $list = \Galette\Repository\Groups::getSimpleList();
        $this->array($list)->hasSize(17);

        foreach ($list as $group_name) {
            $this->string($group_name)->isNotEmpty();
        }

        $list = \Galette\Repository\Groups::getSimpleList(true);
        $this->array($list)->hasSize(17);
        foreach ($list as $group) {
            $this->object($group)->isInstanceOf(\Galette\Entity\Group::class);
        }
    }

    /**
     * Test getSimpleList
     *
     * @return void
     */
    public function testGetList()
    {
        $this->logSuperAdmin();

        $groups = new \Galette\Repository\Groups($this->zdb, $this->login);

        $parents_list = $groups->getList(false);
        $this->array($parents_list)->hasSize(3);

        $parents_list = $groups->getList(true);
        $this->array($parents_list)->hasSize(17);

        $select = $this->zdb->select(\Galette\Entity\Group::TABLE);
        $select->where(['group_name' => 'Europe']);
        $result = $this->zdb->execute($select)->current();
        $europe = $result->{\Galette\Entity\Group::PK};

        $children_list = $groups->getList(true, $europe);
        $this->array($children_list)->hasSize(4);

        //set manager on one group, impersonate him, and check it gets only one group
        $this->getMemberOne();
        $group = new \Galette\Entity\Group((int)$europe);
        $this->boolean($group->setManagers([$this->adh]))->isTrue();

        $this->login->impersonate($this->adh->id);

        $groups = new \Galette\Repository\Groups($this->zdb, $this->login);
        $parents_list = $groups->getList();
        $this->array($parents_list)->hasSize(1);
    }

    /**
     * Test group name uniqueness
     *
     * @return void
     */
    public function testUniqueness()
    {
        $group = new \Galette\Entity\Group();
        $group->setLogin($this->login);
        $unique_name = 'One group to rule them all';
        $group->setName($unique_name);
        $this->boolean($group->store())->isTrue();
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
        $this->boolean(\Galette\Repository\Groups::isUnique($this->zdb, $unique_name))->isFalse();
        //name does not exist on another level - unique
        $this->boolean(\Galette\Repository\Groups::isUnique($this->zdb, $unique_name, $europe))->isTrue();
        //name is the current one - unique
        $this->boolean(\Galette\Repository\Groups::isUnique($this->zdb, $unique_name, null, $group_id))->isTrue();

        //tests on another level
        $this->boolean(\Galette\Repository\Groups::isUnique($this->zdb, 'Nord', $france))->isFalse();
        $this->boolean(\Galette\Repository\Groups::isUnique($this->zdb, 'Creuse', $france))->isTrue();
    }
}
