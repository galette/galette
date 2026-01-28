<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

declare(strict_types=1);

namespace Galette\Tests\Entity;

use Galette\Tests\GaletteTestCase;

/**
 * Group tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Group extends GaletteTestCase
{
    /**
     * Test empty group
     */
    public function testGroup(): void
    {
        global $zdb;
        $zdb = $this->zdb;

        $group = new \Galette\Entity\Group();
        $this->logSuperAdmin();
        $group->setLogin($this->login);
        $this->assertNull($group->getId());
        $this->assertSame(0, $group->getLevel());
        $this->assertNull($group->getName());
        $this->assertNull($group->getFullName());
        $this->assertNull($group->getIndentName());
        $this->assertNull($group->getParentGroup());
    }

    /**
     * Test single group
     */
    public function testSingleGroup(): void
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
     * Test group name uniqueness when adding
     */
    public function testAddUnicity(): void
    {
        global $zdb;
        $zdb = $this->zdb;

        $group = new \Galette\Entity\Group();
        $group->setLogin($this->login);

        $group->setName('A group');
        $this->assertTrue($group->store());
        $group->getId();

        //Adding another group with same name throws an exception
        $group = new \Galette\Entity\Group();
        $group->setLogin($this->login);

        $this->expectExceptionMessage('The group name you have requested already exists in the database.');
        $group->setName('A group');
        $this->assertFalse($group->store());
    }

    /**
     * Test group name uniqueness when editing
     */
    public function testEditUnicity(): void
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
     */
    public function testSubGroup(): void
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
     */
    public function testRemove(): void
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
        $this->assertSame($parent_id, $group->getParentGroup()->getId());

        $group = new \Galette\Entity\Group($parent_id);
        $this->logSuperAdmin();
        $group->setLogin($this->login);
        $this->assertFalse($group->remove()); //still have children, not removed

        $this->expectLogEntry(
            \Analog::WARNING,
            'Group "A parent group" still have members!'
        );
        $this->expectLogEntry(
            \Analog::ERROR,
            'Query error: DELETE FROM ' . ($this->zdb->isPostgres() ? '"galette_groups"' : '`galette_groups`')
        );
        $warning = new \ArrayObject([
            'Level' => 'Error',
            'Code'  => '1451',
            'Message' => "Cannot delete or update a parent row: a foreign key constraint fails (`galette_tests`.`galette_groups`, CONSTRAINT `galette_groups_ibfk_1` FOREIGN KEY (`parent_group`) REFERENCES `galette_groups` (`id_group`) ON UPDATE CASCADE)"
        ]);
        $this->expected_mysql_warnings[] = $warning;
    }

    /**
     * Test cascade removal
     */
    public function testCascadeRemove(): void
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
        $this->assertSame($parent_id, $group->getParentGroup()->getId());

        $group = new \Galette\Entity\Group($parent_id);
        $this->logSuperAdmin();
        $group->setLogin($this->login);
        $this->assertTrue($group->remove(true)); //cascade removal, all will be removed
        $this->assertFalse($group->load($parent_id));
    }
}
