<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Group tests
 *
 * PHP version 5
 *
 * Copyright © 2021-2023 The Galette Team
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
 * @copyright 2021-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2021-11-10
 */

namespace Galette\Entity\test\units;

use PHPUnit\Framework\TestCase;
use Galette\GaletteTestCase;
use Laminas\Db\Adapter\Adapter;

/**
 * Group tests
 *
 * @category  Entity
 * @name      Title
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2021-11-10
 */
class Group extends GaletteTestCase
{
    protected array $excluded_after_methods = ['testUnicity'];

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->deleteGroups();
        parent::tearDown();
    }

    /**
     * Delete groups
     *
     * @return void
     */
    private function deleteGroups()
    {
        $delete = $this->zdb->delete(\Galette\Entity\Group::TABLE);
        $delete->where('parent_group IS NOT NULL');
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Group::TABLE);
        $this->zdb->execute($delete);

        //Clean logs
        $this->zdb->db->query(
            'TRUNCATE TABLE ' . PREFIX_DB . \Galette\Core\History::TABLE,
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
    }

    /**
     * Test empty group
     *
     * @return void
     */
    public function testGroup()
    {
        global $zdb;
        $zdb = $this->zdb;

        $group = new \Galette\Entity\Group();
        $this->logSuperAdmin();
        $group->setLogin($this->login);
        //$this->assertFalse($group->isManager($this->login));
        $this->assertNull($group->getId());
        $this->assertSame(0, $group->getLevel());
        $this->assertNull($group->getName());
        $this->assertNull($group->getFullName());
        $this->assertNull($group->getIndentName());
        $this->assertNull($group->getMembers());
        $this->assertNull($group->getMembers());
        $this->assertEmpty($group->getGroups());
        $this->assertNull($group->getParentGroup());
    }

    /**
     * Test single group
     *
     * @return void
     */
    public function testSingleGroup()
    {
        global $zdb;
        $zdb = $this->zdb;

        $group = new \Galette\Entity\Group();
        $group->setLogin($this->login);

        $group->setName('A group');
        $this->assertTrue($group->store());

        $this->assertFalse($group->isManager($this->login));
        $group_id = $group->getId();
        $this->assertGreaterThan(0, $group_id);
        $this->assertSame(0, $group->getLevel());
        $this->assertSame('A group', $group->getName());
        $this->assertSame('A group', $group->getFullName());
        $this->assertSame('A group', $group->getIndentName());
        $this->assertEmpty($group->getMembers());
        $this->assertSame(0, $group->getMemberCount());
        $this->assertEmpty($group->getManagers());
        $this->assertEmpty($group->getGroups());
        $this->assertNull($group->getParentGroup());

        //edit group
        $group = new \Galette\Entity\Group();
        $this->assertTrue($group->load($group_id));
        $this->assertSame('A group', $group->getName());

        $group->setName('A group - edited');
        $this->assertTrue($group->store());

        $group = new \Galette\Entity\Group($group_id);
        $this->assertSame('A group - edited', $group->getName());
    }

    /**
     * Test group name unicity
     *
     * @return void
     */
    public function testUnicity()
    {
        global $zdb;
        $zdb = $this->zdb;

        $group = new \Galette\Entity\Group();
        $group->setLogin($this->login);

        $group->setName('A group');
        $this->assertTrue($group->store());
        $group_id = $group->getId();

        //update without changes should be ok
        $group = new \Galette\Entity\Group($group_id);
        $this->assertTrue($group->store());

        //Adding another group with same name throws an exception
        $group = new \Galette\Entity\Group();
        $group->setLogin($this->login);

        $this->expectExceptionMessage('The group name you have requested already exists in the database.');
        $group->setName('A group');
        $this->assertFalse($group->store());

        //update with changes should be ok
        $group = new \Galette\Entity\Group($group_id);
        $group->setName('A group - edited');
        $this->assertTrue($group->store());

        $group = new \Galette\Entity\Group();
        $group->setName('Unique one');
        $this->assertTrue($group->store());

        //editing using an existing name is not ok
        $this->expectExceptionMessage('The group name you have requested already exists in the database.');
        $group->setName('A group - edited');
        $this->assertFalse($group->store());
    }

    /**
     * Test sub groups
     *
     * @return void
     */
    public function testSubGroup()
    {
        global $zdb;
        $zdb = $this->zdb;

        $group = new \Galette\Entity\Group();
        $group->setName('A parent group');
        $this->assertTrue($group->store());
        $parent_id = $group->getId();

        $group = new \Galette\Entity\Group();
        $group->setName('A child group');
        $group->setParentGroup($parent_id);
        $this->assertTrue($group->store());
        $child_id_1 = $group->getId();
        $this->assertSame($parent_id, $group->getParentGroup()->getId());

        $group = new \Galette\Entity\Group();
        $group->setName('Another child group');
        $this->assertTrue($group->store());
        $child_id_2 = $group->getId();

        $group->setParentGroup($parent_id);
        $this->assertTrue($group->store());
        $this->assertSame($parent_id, $group->getParentGroup()->getId());

        //non-logged-in will not see children groups
        $group = new \Galette\Entity\Group($parent_id);
        $group->setLogin($this->login);
        $children = $group->getGroups();
        $this->assertCount(0, $children);

        //admin will not see children groups
        $group = new \Galette\Entity\Group($parent_id);
        $this->logSuperAdmin();
        $group->setLogin($this->login);
        $children = $group->getGroups();
        $this->assertCount(2, $children);

        $group = new \Galette\Entity\Group($child_id_1);
        $this->assertTrue($group->detach());

        $group = new \Galette\Entity\Group($parent_id);
        $this->logSuperAdmin();
        $group->setLogin($this->login);
        $children = $group->getGroups();
        $this->assertCount(1, $children);
        $this->assertSame('Another child group', $children[0]->getName());

        $group = new \Galette\Entity\Group($child_id_2);
        $this->assertSame(['A parent group'], $group->getParents());

        $group = new \Galette\Entity\Group();
        $group->setName('A second level child group');
        $group->setParentGroup($child_id_2);
        $this->assertTrue($group->store());
        $child_id_3 = $group->getId();
        $this->assertSame($child_id_2, $group->getParentGroup()->getId());

        $group = new \Galette\Entity\Group($child_id_3);
        $this->assertSame(['A parent group', 'Another child group'], $group->getParents());
        $this->assertTrue($group->detach());
    }

    /**
     * Test removal
     *
     * @return void
     */
    public function testRemove()
    {
        global $zdb;
        $zdb = $this->zdb;

        $group = new \Galette\Entity\Group();
        $group->setName('A parent group');
        $this->assertTrue($group->store());
        $parent_id = $group->getId();

        $group = new \Galette\Entity\Group();
        $group->setName('A child group');
        $group->setParentGroup($parent_id);
        $this->assertTrue($group->store());
        $child_id_1 = $group->getId();
        $this->assertSame($parent_id, $group->getParentGroup()->getId());

        $group = new \Galette\Entity\Group($parent_id);
        $this->logSuperAdmin();
        $group->setLogin($this->login);
        $this->assertFalse($group->remove()); //still have children, not removed
        $this->assertTrue($group->load($parent_id));
        $this->assertTrue($group->remove(true)); //cascade removal, all will be removed
        $this->assertFalse($group->load($parent_id));
    }
}
