<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Group tests
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

namespace Galette\Entity\test\units;

use atoum;
use Galette\GaletteTestCase;
use Zend\Db\Adapter\Adapter;

/**
 * Group tests
 *
 * @category  Entity
 * @name      Title
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2021-11-10
 */
class Group extends GaletteTestCase
{
    protected $excluded_after_methods = ['testUnicity'];

    /**
     * Tear down tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function afterTestMethod($method)
    {
        $this->deleteGroups();
        parent::afterTestMethod($method);
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
            \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
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
        //$this->boolean($group->isManager($this->login))->isFalse();
        $this->variable($group->getId())->isNull();
        $this->integer($group->getLevel())->isIdenticalTo(0);
        $this->variable($group->getName())->isNull();
        $this->variable($group->getFullName())->isNull();
        $this->variable($group->getIndentName())->isNull();
        $this->variable($group->getMembers())->isNull();
        $this->variable($group->getMembers())->isNull();
        $this->array($group->getGroups())->isEmpty();
        $this->variable($group->getParentGroup())->isNull();
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
        $this->boolean($group->store())->isTrue();

        $this->boolean($group->isManager($this->login))->isFalse();
        $group_id = $group->getId();
        $this->integer($group_id)->isGreaterThan(0);
        $this->integer($group->getLevel())->isIdenticalTo(0);
        $this->string($group->getName())->isIdenticalTo('A group');
        $this->string($group->getFullName())->isIdenticalTo('A group');
        $this->string($group->getIndentName())->isIdenticalTo('A group');
        $this->array($group->getMembers())->isEmpty();
        $this->integer($group->getMemberCount())->isIdenticalTo(0);
        $this->array($group->getManagers())->isEmpty();
        $this->array($group->getGroups())->isEmpty();
        $this->variable($group->getParentGroup())->isNull();

        //edit group
        $group = new \Galette\Entity\Group();
        $this->boolean($group->load($group_id))->isTrue();
        $this->string($group->getName())->isIdenticalTo('A group');

        $group->setName('A group - edited');
        $this->boolean($group->store())->isTrue();

        $group = new \Galette\Entity\Group($group_id);
        $this->string($group->getName())->isIdenticalTo('A group - edited');
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
        $this->boolean($group->store())->isTrue();
        $group_id = $group->getId();

        //Adding another group with same name throws an exception
        $group = new \Galette\Entity\Group();
        $group->setLogin($this->login);

        $this
            ->exception(
                function () use ($group) {
                    $group->setName('A group');
                    $this->boolean($group->store())->isFalse();
                }
            )->hasMessage('Duplicate entry');
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
        $this->boolean($group->store())->isTrue();
        $parent_id = $group->getId();

        $group = new \Galette\Entity\Group();
        $group->setName('A child group');
        $group->setParentGroup($parent_id);
        $this->boolean($group->store())->isTrue();
        $child_id_1 = $group->getId();
        $this->integer($group->getParentGroup()->getId())->isIdenticalTo($parent_id);

        $group = new \Galette\Entity\Group();
        $group->setName('Another child group');
        $this->boolean($group->store())->isTrue();
        $child_id_2 = $group->getId();

        $group->setParentGroup($parent_id);
        $this->boolean($group->store())->isTrue();
        $this->integer($group->getParentGroup()->getId())->isIdenticalTo($parent_id);

        //non-logged-in will not see children groups
        $group = new \Galette\Entity\Group($parent_id);
        $group->setLogin($this->login);
        $children = $group->getGroups();
        $this->array($children)->hasSize(0);

        //admin will not see children groups
        $group = new \Galette\Entity\Group($parent_id);
        $this->logSuperAdmin();
        $group->setLogin($this->login);
        $children = $group->getGroups();
        $this->array($children)->hasSize(2);

        $group = new \Galette\Entity\Group($child_id_1);
        $this->boolean($group->detach())->isTrue();

        $group = new \Galette\Entity\Group($parent_id);
        $this->logSuperAdmin();
        $group->setLogin($this->login);
        $children = $group->getGroups();
        $this->array($children)->hasSize(1);
        $this->string($children[0]->getName())->isIdenticalTo('Another child group');
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
        $this->boolean($group->store())->isTrue();
        $parent_id = $group->getId();

        $group = new \Galette\Entity\Group();
        $group->setName('A child group');
        $group->setParentGroup($parent_id);
        $this->boolean($group->store())->isTrue();
        $child_id_1 = $group->getId();
        $this->integer($group->getParentGroup()->getId())->isIdenticalTo($parent_id);

        $group = new \Galette\Entity\Group($parent_id);
        $this->logSuperAdmin();
        $group->setLogin($this->login);
        $this->boolean($group->remove())->isFalse(); //still have children, not removed
        $this->boolean($group->load($parent_id))->isTrue();
        $this->boolean($group->remove(true))->isTrue(); //cascade removal, all will be removed
        $this->boolean($group->load($parent_id))->isFalse();
    }
}
