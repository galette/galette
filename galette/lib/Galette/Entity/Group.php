<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Group entity
 *
 * PHP version 5
 *
 * Copyright © 2012-2021 The Galette Team
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
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2012-01-17
 */

namespace Galette\Entity;

use Throwable;
use Galette\Core\Login;
use Analog\Analog;
use Laminas\Db\Sql\Expression;

/**
 * Group entity
 *
 * @category  Entity
 * @name      Group
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2012-01-17
 */
class Group
{
    public const TABLE = 'groups';
    public const PK = 'id_group';
    //relations tables
    public const GROUPSUSERS_TABLE = 'groups_members';
    public const GROUPSMANAGERS_TABLE = 'groups_managers';

    public const MEMBER_TYPE = 0;
    public const MANAGER_TYPE = 1;

    private $id;
    private $group_name;
    private $parent_group;
    private $managers;
    private $members;
    private $groups;
    private $creation_date;
    private $count_members;
    private $isempty;

    /**
     * Default constructor
     *
     * @param null|int|ResultSet $args Either a ResultSet row or its id for to load
     *                                 a specific group, or null to just
     *                                 instanciate object
     */
    public function __construct($args = null)
    {
        if ($args == null || is_int($args)) {
            if (is_int($args) && $args > 0) {
                $this->load($args);
            }
        } elseif (is_object($args)) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Loads a group from its id
     *
     * @param int $id the identifiant for the group to load
     *
     * @return bool true if query succeed, false otherwise
     */
    public function load($id)
    {
        global $zdb;

        try {
            $select = $zdb->select(self::TABLE);
            $select->where(array(self::PK => $id));

            $results = $zdb->execute($select);

            if ($results->count() > 0) {
                $this->loadFromRS($results->current());
                return true;
            } else {
                return false;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Cannot load group form id `' . $id . '` | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ResultSet $r the resultset row
     *
     * @return void
     */
    private function loadFromRS($r)
    {
        $this->id = $r->id_group;
        $this->group_name = $r->group_name;
        $this->creation_date = $r->creation_date;
        if ($r->parent_group) {
            $this->parent_group = new Group((int)$r->parent_group);
        }
        $adhpk = Adherent::PK;
        if (isset($r->members)) {
            //we're from a list, we just want members count
            $this->count_members = $r->members;
        } else {
            //we're probably from a single group, let's load sub entities
            //$this->loadPersons(self::MEMBER_TYPE);
            //$this->loadPersons(self::MANAGER_TYPE);
            //$this->loadSubGroups();
        }
    }

    /**
     * Loads members for the current group
     *
     * @param int $type Either self::MEMBER_TYPE or self::MANAGER_TYPE
     *
     * @return void
     */
    private function loadPersons($type)
    {
        global $zdb;

        if ($this->id) {
            try {
                $join = null;
                switch ($type) {
                    case self::MEMBER_TYPE:
                        $join = PREFIX_DB . self::GROUPSUSERS_TABLE;
                        break;
                    case self::MANAGER_TYPE:
                        $join = PREFIX_DB . self::GROUPSMANAGERS_TABLE;
                        break;
                }

                $select = $zdb->select(Adherent::TABLE, 'a');
                $select->join(
                    array('g' => $join),
                    'g.' . Adherent::PK . '=a.' . Adherent::PK,
                    array()
                )->where([
                    'g.' . self::PK => $this->id
                ])->order(
                    'nom_adh ASC',
                    'prenom_adh ASC'
                );

                $results = $zdb->execute($select);
                $members = array();

                $deps = array(
                    'picture'   => false,
                    'groups'    => false,
                    'dues'      => false
                );

                foreach ($results as $m) {
                    $members[] = new Adherent($zdb, $m, $deps);
                }

                if ($type === self::MEMBER_TYPE) {
                    $this->members = $members;
                } else {
                    $this->managers = $members;
                }
            } catch (Throwable $e) {
                Analog::log(
                    'Cannot get group persons | ' . $e->getMessage(),
                    Analog::WARNING
                );
                throw $e;
            }
        }
    }

    /**
     * Load sub-groups
     *
     * @return void
     */
    private function loadSubGroups()
    {
        global $zdb;

        try {
            $select = $zdb->select(self::TABLE, 'a');

            if (!$this->login->isAdmin() && !$this->login->isStaff()) {
                $select->join(
                    array('b' => PREFIX_DB . self::GROUPSMANAGERS_TABLE),
                    'a.' . self::PK . '=b.' . self::PK,
                    array()
                )->where(['b.' . Adherent::PK => $this->login->id]);
            }

            $select->where(['parent_group' => $this->id])
                ->order('group_name ASC');

            $results = $zdb->execute($select);
            $groups = array();
            $grppk = self::PK;
            foreach ($results as $m) {
                $group = new Group((int)$m->$grppk);
                $group->setLogin($this->login);
                $groups[] = $group;
            }
            $this->groups = $groups;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot get subgroup for group ' . $this->group_name .
                ' (' . $this->id . ')| ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Remove specified group
     *
     * @param boolean $cascade Also remove members and managers
     *
     * @return boolean
     */
    public function remove($cascade = false)
    {
        global $zdb;
        $transaction = false;

        try {
            if (!$zdb->connection->inTransaction()) {
                $zdb->connection->beginTransaction();
                $transaction = true;
            }

            if ($cascade === true) {
                $subgroups = $this->getGroups();
                if (count($subgroups) > 0) {
                    Analog::log(
                        'Cascading remove ' . $this->group_name .
                        '. Subgroups, their members and managers will be detached.',
                        Analog::INFO
                    );
                    foreach ($subgroups as $subgroup) {
                        $subgroup->remove(true);
                    }
                }

                Analog::log(
                    'Cascading remove ' . $this->group_name .
                    '. Members and managers will be detached.',
                    Analog::INFO
                );

                //delete members
                $delete = $zdb->delete(self::GROUPSUSERS_TABLE);
                $delete->where([self::PK => $this->id]);
                $zdb->execute($delete);

                //delete managers
                $delete = $zdb->delete(self::GROUPSMANAGERS_TABLE);
                $delete->where([self::PK => $this->id]);
                $zdb->execute($delete);
            }

            //delete group itself
            $delete = $zdb->delete(self::TABLE);
            $delete->where([self::PK => $this->id]);
            $zdb->execute($delete);

            //commit all changes
            if ($transaction) {
                $zdb->connection->commit();
            }

            return true;
        } catch (Throwable $e) {
            if ($transaction) {
                $zdb->connection->rollBack();
            }
            if ($e->getCode() == 23000) {
                Analog::log(
                    str_replace(
                        '%group',
                        $this->group_name,
                        'Group "%group" still have members!'
                    ),
                    Analog::WARNING
                );
                $this->isempty = false;
            } else {
                Analog::log(
                    'Unable to delete group ' . $this->group_name .
                    ' (' . $this->id . ') |' . $e->getMessage(),
                    Analog::ERROR
                );
                throw $e;
            }
            return false;
        }
    }

    /**
     * Is group empty? (after first deletion try)
     *
     * @return boolean
     */
    public function isEmpty()
    {
        return $this->isempty;
    }

    /**
     * Detach a group from its parent
     *
     * @return boolean
     */
    public function detach()
    {
        global $zdb, $hist;

        try {
            $update = $zdb->update(self::TABLE);
            $update->set(
                array('parent_group' => new Expression('NULL'))
            )->where(
                [self::PK => $this->id]
            );

            $edit = $zdb->execute($update);

            //edit == 0 does not mean there were an error, but that there
            //were nothing to change
            if ($edit->count() > 0) {
                $this->parent_group = null;
                $hist->add(
                    _T("Group has been detached from its parent"),
                    $this->group_name
                );
            }

            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Something went wrong detaching group `' . $this->group_name .
                '` (' . $this->id . ') from its parent:\'( | ' .
                $e->getMessage() . "\n" .
                $e->getTraceAsString(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Store the group
     *
     * @return boolean
     */
    public function store()
    {
        global $zdb, $hist;

        try {
            $values = array(
                self::PK     => $this->id,
                'group_name' => $this->group_name
            );

            if ($this->parent_group) {
                $values['parent_group'] = $this->parent_group->getId();
            }

            if (!isset($this->id) || $this->id == '') {
                //we're inserting a new group
                unset($values[self::PK]);
                $this->creation_date = date("Y-m-d H:i:s");
                $values['creation_date'] = $this->creation_date;

                $insert = $zdb->insert(self::TABLE);
                $insert->values($values);
                $add = $zdb->execute($insert);
                if ($add->count() > 0) {
                    $this->id = $zdb->getLastGeneratedValue($this);

                    // logging
                    $hist->add(
                        _T("Group added"),
                        $this->group_name
                    );
                    return true;
                } else {
                    $hist->add(_T("Fail to add new group."));
                    throw new \Exception(
                        'An error occurred inserting new group!'
                    );
                }
            } else {
                //we're editing an existing group
                $update = $zdb->update(self::TABLE);
                $update
                    ->set($values)
                    ->where([self::PK => $this->id]);

                $edit = $zdb->execute($update);

                //edit == 0 does not mean there were an error, but that there
                //were nothing to change
                if ($edit->count() > 0) {
                    $hist->add(
                        _T("Group updated"),
                        $this->group_name
                    );
                }
                return true;
            }
            /** FIXME: also store members and managers? */
        } catch (Throwable $e) {
            Analog::log(
                'Something went wrong :\'( | ' . $e->getMessage() . "\n" .
                $e->getTraceAsString(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Is current logged-in user manager of the group?
     *
     * @param Login $login Login instance
     *
     * @return boolean
     */
    public function isManager(Login $login)
    {
        if ($login->isAdmin() || $login->isStaff()) {
            //admins as well as staff members are managers for all groups!
            return true;
        } else {
            //let's check if current logged-in user is part of group managers
            foreach ($this->managers as $manager) {
                if ($login->login == $manager->login) {
                    return true;
                    break;
                }
            }
            return false;
        }
    }

    /**
     * Get group id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get Level of the group
     *
     * @return integer
     */
    public function getLevel()
    {
        if ($this->parent_group) {
            return $this->parent_group->getLevel() + 1;
        }
        return 0;
    }

    /**
     * Get the full name of the group "foo / bar"
     *
     * @return string
     */
    public function getFullName()
    {
        if ($this->parent_group) {
            return $this->parent_group->getFullName() . ' / ' . $this->group_name;
        }
        return $this->group_name;
    }

    /**
     * Get the indented short name of the group "  >> bar"
     *
     * @return string
     */
    public function getIndentName()
    {
        if (($level = $this->getLevel())) {
            return str_repeat("&nbsp;", 3 * $level) . '&raquo; ' . $this->group_name;
        }
        return $this->group_name;
    }

    /**
     * Get group name
     *
     * @return string
     */
    public function getName()
    {
        return $this->group_name;
    }

    /**
     * Get group members
     *
     * @return Adherent[]
     */
    public function getMembers()
    {
        if (!is_array($this->members)) {
            $this->loadPersons(self::MEMBER_TYPE);
        }
        return $this->members;
    }

    /**
     * Get groups managers
     *
     * @return Adherent[]
     */
    public function getManagers()
    {
        if (!is_array($this->managers)) {
            $this->loadPersons(self::MANAGER_TYPE);
        }
        return $this->managers;
    }

    /**
     * Get subgroups
     *
     * @return Group[]
     */
    public function getGroups()
    {
        if (!is_array($this->groups)) {
            $this->loadSubGroups();
        }
        return $this->groups;
    }

    /**
     * Get parent group
     *
     * @return Group
     */
    public function getParentGroup()
    {
        return $this->parent_group;
    }

    /**
     * Get group creation date
     *
     * @param boolean $formatted Return date formatted, raw if false
     *
     * @return string
     */
    public function getCreationDate($formatted = true)
    {
        if ($formatted === true) {
            $date = new \DateTime($this->creation_date);
            return $date->format(__("Y-m-d"));
        } else {
            return $this->creation_date;
        }
    }

    /**
     * Get member count
     *
     * @param boolean $force Force members load, defaults to false
     *
     * @return int
     */
    public function getMemberCount($force = false)
    {
        if (isset($this->members) && is_array($this->members)) {
            return count($this->members);
        } elseif (isset($this->count_members)) {
            return $this->count_members;
        } else {
            if ($force === true) {
                return count($this->getMembers());
            } else {
                return 0;
            }
        }
    }

    /**
     * Set name
     *
     * @param string $name Group name
     *
     * @return Group
     */
    public function setName($name)
    {
        $this->group_name = $name;
        return $this;
    }

    /**
     * Set all subgroups
     *
     * @param array $groups Groups id
     *
     * @return Group
     */
    public function setSubgroups($groups)
    {
        $this->groups = $groups;
        return $this;
    }

    /**
     * check if can Set parent group
     *
     * @param Group $group Parent group
     *
     * @return boolean
     */
    public function canSetParentGroup(Group $group)
    {
        do {
            if ($group->getId() == $this->getId()) {
                return false;
            }
        } while ($group = $group->getParentGroup());

        return true;
    }

    /**
     * Set parent group
     *
     * @param int $id Parent group identifier
     *
     * @return Group
     */
    public function setParentGroup($id)
    {
        $group = new Group((int)$id);

        if (!$this->canSetParentGroup($group)) {
            //does not seem to work :/
            throw new \Exception(
                sprintf(
                    _T('Group `%1$s` cannot be set as parent!'),
                    $group->getName()
                )
            );
        }

        $this->parent_group = $group;
        return $this;
    }

    /**
     * Set members
     *
     * @param Adherent[] $members Members list
     *
     * @return void
     */
    public function setMembers($members)
    {
        global $zdb;

        try {
            $zdb->connection->beginTransaction();

            //first, remove current groups members
            $delete = $zdb->delete(self::GROUPSUSERS_TABLE);
            $delete->where([self::PK => $this->id]);
            $zdb->execute($delete);

            Analog::log(
                'Group members has been removed for `' . $this->group_name .
                '`, we can now store new ones.',
                Analog::INFO
            );

            $insert = $zdb->insert(self::GROUPSUSERS_TABLE);
            $insert->values(
                array(
                    self::PK        => ':group',
                    Adherent::PK    => ':adh'
                )
            );

            $stmt = $zdb->sql->prepareStatementForSqlObject($insert);

            if (is_array($members)) {
                foreach ($members as $m) {
                    $result = $stmt->execute(
                        array(
                            'group' => $this->id,
                            'adh'   => $m->id
                        )
                    );

                    if ($result) {
                        Analog::log(
                            'Member `' . $m->sname . '` attached to group `' .
                            $this->group_name . '`.',
                            Analog::DEBUG
                        );
                    } else {
                        Analog::log(
                            'An error occurred trying to attach member `' .
                            $m->sname . '` to group `' . $this->group_name .
                            '` (' . $this->id . ').',
                            Analog::ERROR
                        );
                        throw new \Exception(
                            'Unable to attach `' . $m->sname . '` ' .
                            'to ' . $this->group_name . '(' . $this->id . ')'
                        );
                    }
                }
            }
            //commit all changes
            $zdb->connection->commit();

            Analog::log(
                'Group members updated successfully.',
                Analog::INFO
            );

            return true;
        } catch (Throwable $e) {
            $zdb->connection->rollBack();
            $messages = array();
            do {
                $messages[] = $e->getMessage();
            } while ($e = $e->getPrevious());
            Analog::log(
                'Unable to attach members to group `' . $this->group_name .
                '` (' . $this->id . ')|' . implode("\n", $messages),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Set managers
     *
     * @param Adherent[] $members Managers list
     *
     * @return boolean
     */
    public function setManagers($members)
    {
        global $zdb;

        try {
            $zdb->connection->beginTransaction();

            //first, remove current groups managers
            $delete = $zdb->delete(self::GROUPSMANAGERS_TABLE);
            $delete->where([self::PK => $this->id]);
            $zdb->execute($delete);

            Analog::log(
                'Group managers has been removed for `' . $this->group_name .
                '`, we can now store new ones.',
                Analog::INFO
            );

            $insert = $zdb->insert(self::GROUPSMANAGERS_TABLE);
            $insert->values(
                array(
                    self::PK        => ':group',
                    Adherent::PK    => ':adh'
                )
            );

            $stmt = $zdb->sql->prepareStatementForSqlObject($insert);

            if (is_array($members)) {
                foreach ($members as $m) {
                    $result = $stmt->execute(
                        array(
                            'group' => $this->id,
                            'adh'   => $m->id
                        )
                    );

                    if ($result) {
                        Analog::log(
                            'Manager `' . $m->sname . '` attached to group `' .
                            $this->group_name . '`.',
                            Analog::DEBUG
                        );
                    } else {
                        Analog::log(
                            'An error occurred trying to attach manager `' .
                            $m->sname . '` to group `' . $this->group_name .
                            '` (' . $this->id . ').',
                            Analog::ERROR
                        );
                        throw new \Exception(
                            'Unable to attach `' . $m->sname . '` ' .
                            'to ' . $this->group_name . '(' . $this->id . ')'
                        );
                    }
                }
            }
            //commit all changes
            $zdb->connection->commit();

            Analog::log(
                'Groups managers updated successfully.',
                Analog::INFO
            );

            return true;
        } catch (Throwable $e) {
            $zdb->connection->rollBack();
            $messages = array();
            do {
                $messages[] = $e->getMessage();
            } while ($e = $e->getPrevious());
            Analog::log(
                'Unable to attach managers to group `' . $this->group_name .
                '` (' . $this->id . ')|' . implode("\n", $messages),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Set login instance
     *
     * @param Login $login Login instance
     *
     * @return Group
     */
    public function setLogin(Login $login)
    {
        $this->login = $login;
        return $this;
    }
}
