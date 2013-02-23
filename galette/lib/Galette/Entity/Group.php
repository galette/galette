<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Group entity
 *
 * PHP version 5
 *
 * Copyright © 2012-2013 The Galette Team
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
 * @copyright 2012-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2012-01-17
 */

namespace Galette\Entity;

use Analog\Analog as Analog;

/**
 * Group entity
 *
 * @category  Entity
 * @name      Group
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2012-01-17
 */
class Group
{
    const TABLE = 'groups';
    const PK = 'id_group';
    //relations tables
    const GROUPSUSERS_TABLE = 'groups_members';
    const GROUPSMANAGERS_TABLE = 'groups_managers';

    const MEMBER_TYPE = 0;
    const MANAGER_TYPE = 1;

    private $_id;
    private $_group_name;
    private $_parent_group;
    private $_managers;
    private $_members;
    private $_groups;
    private $_creation_date;
    private $_count_members;

    /**
     * Default constructor
     *
     * @param null|int|ResultSet $args Either a ResultSet row or its id for to load
     *                                 a specific group, or null to just
     *                                 instanciate object
     */
    public function __construct($args = null)
    {
        if ( $args == null || is_int($args) ) {
            if ( is_int($args) && $args > 0 ) {
                $this->load($args);
            }
        } elseif ( is_object($args) ) {
            $this->_loadFromRS($args);
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
            $select = new \Zend_Db_Select($zdb->db);

            $select->from(PREFIX_DB . self::TABLE)
                ->where(self::PK . '=?', $id);
            $result = $select->query()->fetchObject();
            if ( $result ) {
                $this->_loadFromRS($result);
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            Analog::log(
                'Cannot load group form id `' . $id . '` | ' . $e->getMessage(),
                Analog::WARNING
            );
            Analog::log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ResultSet $r the resultset row
     *
     * @return void
     */
    private function _loadFromRS($r)
    {
        $this->_id = $r->id_group;
        $this->_group_name = $r->group_name;
        $this->_creation_date = $r->creation_date;
        if ( $r->parent_group ) {
            $this->_parent_group = new Group((int)$r->parent_group);
        }
        $adhpk = Adherent::PK;
        if ( isset($r->members) ) {
            //we're from a list, we just want members count
            $this->_count_members = $r->members;
        } else {
            //we're probably from a single group, let's load sub entities
            //$this->_loadPersons(self::MEMBER_TYPE);
            //$this->_loadPersons(self::MANAGER_TYPE);
            //$this->_loadSubGroups();
        }
    }

    /**
     * Loads members for the current group
     *
     * @param int $type Either self::MEMBER_TYPE or self::MANAGER_TYPE
     *
     * @return void
     */
    private function _loadPersons($type)
    {
        global $zdb;

        if ( $this->_id ) {
            try {
                $select = new \Zend_Db_Select($zdb->db);

                $from = null;
                switch ( $type ) {
                case self::MEMBER_TYPE:
                    $from = self::GROUPSUSERS_TABLE;
                    break;
                case self::MANAGER_TYPE:
                    $from = self::GROUPSMANAGERS_TABLE;
                    break;
                }

                $select->from(
                    PREFIX_DB . $from,
                    array(Adherent::PK)
                )->where(self::PK . ' = ?', $this->_id);

                $res = $select->query()->fetchAll();
                $members = array();
                $adhpk = Adherent::PK;

                $deps = array(
                    'picture'   => false,
                    'groups'    => false,
                    'dues'      => false
                );

                foreach ( $res as $m ) {
                    $members[] = new Adherent((int)$m->$adhpk, $deps);
                }

                if ( $type === self::MEMBER_TYPE) {
                    $this->_members = $members;
                } else {
                    $this->_managers = $members;
                }
            } catch (\Exception $e) {
                Analog::log(
                    'Cannot get group persons | ' . $e->getMessage(),
                    Analog::WARNING
                );
                Analog::log(
                    'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                    Analog::ERROR
                );
            }
        }
    }

    /**
     * Load sub-groups
     *
     * @return void
     */
    private function _loadSubGroups()
    {
        global $zdb, $login;

        try {
            $select = new \Zend_Db_Select($zdb->db);

            $select->from(
                array('a' => PREFIX_DB . self::TABLE)
            );

            if ( !$login->isAdmin() && !$login->isStaff() ) {
                $select->join(
                    array('b' => PREFIX_DB . self::GROUPSMANAGERS_TABLE),
                    'a.' . self::PK . '=b.' . self::PK,
                    array()
                )->where('b.' . Adherent::PK . ' = ?', $login->id);
            }

            $select->where('parent_group = ?', $this->_id)
                ->order('group_name ASC');

            $res = $select->query()->fetchAll();
            $groups = array();
            $grppk = self::PK;
            foreach ( $res as $m ) {
                $groups[] = new Group((int)$m->$grppk);
            }
            $this->_groups = $groups;
        } catch ( \Exception $e ) {
            Analog::log(
                'Cannot get subgroup for group ' . $this->_group_name .
                ' (' . $this->_id . ')| ' . $e->getMessage(),
                Analog::WARNING
            );
            Analog::log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                Analog::ERROR
            );
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

        try {
            $zdb->db->beginTransaction();

            if ( $cascade === true ) {
                //delete members
                $del = $zdb->db->delete(
                    PREFIX_DB . self::GROUPSUSERS_TABLE,
                    self::PK . ' = ' . $id
                );

                //delete_managers
                $del = $zdb->db->delete(
                    PREFIX_DB . self::GROUPSMANAGERS_TABLE,
                    self::PK . ' = ' . $id
                );
            }

            //delete group itself
            $del = $zdb->db->delete(
                PREFIX_DB . self::TABLE,
                self::PK . ' = ' . $this->_id
            );

            //commit all changes
            $zdb->db->commit();

            return true;
        } catch (\Exception $e) {
            $zdb->db->rollBack();
            Analog::log(
                'Unable to delete group ' . $this->_group_name .
                ' (' . $this->_id  . ') |' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
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
            $edit = $zdb->db->update(
                PREFIX_DB . self::TABLE,
                array('parent_group' => new \Zend_Db_Expr('NULL')),
                self::PK . '=' . $this->_id
            );
            //edit == 0 does not mean there were an error, but that there
            //were nothing to change
            if ( $edit > 0 ) {
                $this->_parent_group = null;
                $hist->add(
                    _T("Group has been detached from its parent"),
                    strtoupper($this->_group_name)
                );
            }

            return true;
        } catch ( \Exception $e ) {
            Analog::log(
                'Something went wrong detaching group `' . $this->_group_name .
                '` (' . $this->_id . ') from its parent:\'( | ' .
                $e->getMessage() . "\n" .
                $e->getTraceAsString(),
                Analog::ERROR
            );
            throw new \Exception(_T("Unable to detach group :("));
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
                self::PK     => $this->_id,
                'group_name' => $this->_group_name
            );

            if ( $this->_parent_group ) {
                $values['parent_group'] = $this->_parent_group->getId();
            }

            if ( !isset($this->_id) || $this->_id == '') {
                //we're inserting a new group
                unset($values[self::PK]);
                $this->_creation_date = date("Y-m-d H:i:s");
                $values['creation_date'] = $this->_creation_date;
                $add = $zdb->db->insert(PREFIX_DB . self::TABLE, $values);
                if ( $add > 0) {
                    $this->_id = $zdb->db->lastInsertId(
                        PREFIX_DB . self::TABLE,
                        'id'
                    );
                    // logging
                    $hist->add(
                        _T("Group added"),
                        $this->_group_name
                    );
                    return true;
                } else {
                    $hist->add(_T("Fail to add new group."));
                    throw new \Exception(
                        'An error occured inserting new group!'
                    );
                }
            } else {
                //we're editing an existing group
                $edit = $zdb->db->update(
                    PREFIX_DB . self::TABLE,
                    $values,
                    self::PK . '=' . $this->_id
                );
                //edit == 0 does not mean there were an error, but that there
                //were nothing to change
                if ( $edit > 0 ) {
                    $hist->add(
                        _T("Group updated"),
                        strtoupper($this->_group_name)
                    );
                }
                return true;
            }
            /** FIXME: also store members and managers? */
        } catch (\Exception $e) {
            /** FIXME */
            Analog::log(
                'Something went wrong :\'( | ' . $e->getMessage() . "\n" .
                $e->getTraceAsString(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Is current loggedin user manager of the group?
     *
     * @return boolean
     */
    public function isManager()
    {
        global $login;
        if ( $login->isAdmin() || $login->isStaff() ) {
            //admins as well as staff members are managers for all groups!
            return true;
        } else {
            //let's check if current loggedin user is part of group managers
            foreach ($this->_managers as $manager) {
                if ( $login->login == $manager->login ) {
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
        return $this->_id;
    }

    /**
     * Get group name
     *
     * @return string
     */
    public function getName()
    {
        return $this->_group_name;
    }

    /**
     * Get group members
     *
     * @return Adherent[]
     */
    public function getMembers()
    {
        if ( !is_array($this->_members) ) {
            $this->_loadPersons(self::MEMBER_TYPE);
        }
        return $this->_members;
    }

    /**
     * Get groups managers
     *
     * @return Adherent[]
     */
    public function getManagers()
    {
        if ( !is_array($this->_managers) ) {
            $this->_loadPersons(self::MANAGER_TYPE);
        }
        return $this->_managers;
    }

    /**
     * Get subgroups
     *
     * @return Group[]
     */
    public function getGroups()
    {
        if ( !is_array($this->_groups) ) {
            $this->_loadSubGroups();
        }
        return $this->_groups;
    }

    /**
     * Get parent group
     *
     * @return Group
     */
    public function getParentGroup()
    {
        return $this->_parent_group;
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
        if ( $formatted === true ) {
            $date = new \DateTime($this->_creation_date);
            return $date->format(_T("Y-m-d"));
        } else {
            return $this->_creation_date;
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
        if (isset($this->_members) && is_array($this->_members) ) {
            return count($this->_members);
        } else if ( isset($this->_count_members) ) {
            return $this->_count_members;
        } else {
            if ( $force === true ) {
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
     * @return void
     */
    public function setName($name)
    {
        $this->_group_name = $name;
    }

    /**
     * Set all subgroups
     *
     * @param array $groups Groups id
     *
     * @return void
     */
    public function setSubgroups($groups)
    {
        $this->_groups = $groups;
    }

    /**
     * Set parent group
     *
     * @param int $id Parent group identifier
     *
     * @return void
     */
    public function setParentGroup($id)
    {
        if ( $id == $this->_id ) {
            throw new \Exception(_T("A group cannot be set as its own parent!"));
        }
        foreach ( $this->getGroups() as $g ) {
            if ( $id == $g->getId() ) {
                throw new \Exception(
                    preg_replace(
                        array('/%subgroupname/', '/%groupname/'),
                        array($g->getName(), $this->getName()),
                        _T("Group `%subgroupname` is a child of `%groupname`, cnanot be setted as parent!")
                    )
                );
            }
        }
        $this->_parent_group = new Group((int)$id);
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
            $zdb->db->beginTransaction();

            //first, remove current groups members
            $del = $zdb->db->delete(
                PREFIX_DB . self::GROUPSUSERS_TABLE,
                self::PK . ' = ' . $this->_id
            );
            Analog::log(
                'Group members has been removed for `' . $this->_group_name .
                ', we can now store new ones.',
                Analog::INFO
            );

            $stmt = $zdb->db->prepare(
                'INSERT INTO ' . PREFIX_DB . self::GROUPSUSERS_TABLE .
                ' (' . $zdb->db->quoteIdentifier(self::PK) . ', ' .
                $zdb->db->quoteIdentifier(Adherent::PK) . ')' .
                ' VALUES(' . $this->_id . ', :adh)'
            );

            if ( is_array($members) ) {
                foreach ( $members as $m ) {
                    $stmt->bindValue(':adh', $m->id, \PDO::PARAM_INT);

                    if ( $stmt->execute() ) {
                        Analog::log(
                            'Member `' . $m->sname . '` attached to group `' .
                            $this->_group_name . '`.',
                            Analog::DEBUG
                        );
                    } else {
                        Analog::log(
                            'An error occured trying to attach member `' .
                            $m->sname . '` to group `' . $this->_group_name .
                            '` ('  . $this->_id . ').',
                            Analog::ERROR
                        );
                        throw new \Exception(
                            'Unable to attach `' . $m->sname . '` ' .
                            'to ' . $this->_group_name . '(' . $this->_id . ')'
                        );
                    }
                }
            }
            //commit all changes
            $zdb->db->commit();

            Analog::log(
                'Group members updated successfully.',
                Analog::INFO
            );

            return true;
        } catch (\Exception $e) {
            $zdb->db->rollBack();
            Analog::log(
                'Unable to attach members to group `' . $this->_group_name .
                '` (' . $this->_id . ')|' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
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
            $zdb->db->beginTransaction();

            //first, remove current groups managers
            $del = $zdb->db->delete(
                PREFIX_DB . self::GROUPSMANAGERS_TABLE,
                self::PK . ' = ' . $this->_id
            );
            Analog::log(
                'Group managers has been removed for `' . $this->_group_name .
                ', we can now store new ones.',
                Analog::INFO
            );

            $stmt = $zdb->db->prepare(
                'INSERT INTO ' . PREFIX_DB . self::GROUPSMANAGERS_TABLE .
                ' (' . $zdb->db->quoteIdentifier(self::PK) . ', ' .
                $zdb->db->quoteIdentifier(Adherent::PK) . ')' .
                ' VALUES(' . $this->_id . ', :adh)'
            );

            if ( is_array($members) ) {
                foreach ( $members as $m ) {
                    $stmt->bindValue(':adh', $m->id, \PDO::PARAM_INT);

                    if ( $stmt->execute() ) {
                        Analog::log(
                            'Manager `' . $m->sname . '` attached to group `' .
                            $this->_group_name . '`.',
                            Analog::DEBUG
                        );
                    } else {
                        Analog::log(
                            'An error occured trying to attach manager `' .
                            $m->sname . '` to group `' . $this->_group_name .
                            '` ('  . $this->_id . ').',
                            Analog::ERROR
                        );
                        throw new \Exception(
                            'Unable to attach `' . $m->sname . '` ' .
                            'to ' . $this->_group_name . '(' . $this->_id . ')'
                        );
                    }
                }
            }
            //commit all changes
            $zdb->db->commit();

            Analog::log(
                'Groups managers updated successfully.',
                Analog::INFO
            );

            return true;
        } catch (\Exception $e) {
            $zdb->db->rollBack();
            Analog::log(
                'Unable to attach managers to group `' . $this->_group_name .
                '` (' . $this->_id . ')|' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }
}
