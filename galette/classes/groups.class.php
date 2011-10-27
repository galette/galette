<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Groups managment
 *
 * PHP version 5
 *
 * Copyright © 2011 The Galette Team
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
 * @category  Classes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-10-25
 */

/**
 * This class handles groups, their owners and members.
 *
 * @category  Classes
 * @name      Groups
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-10-25
 */
class Groups
{
    const TABLE = 'groups';
    const USERSGROUPS_TABLE = 'groups_users';
    const PK = 'id_group';

    private $_id;
    private $_group_name;
    private $_owner;
    private $_members;
    private $_creation_date;

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
        global $zdb, $log;

        try {
            $select = new Zend_Db_Select($zdb->db);

            $select->from(PREFIX_DB . self::TABLE)
                ->where(self::PK . '=?', $id);
            $result = $select->query()->fetchObject();
            $this->_loadFromRS($result);
            return true;
        } catch (Exception $e) {
            $log->log(
                'Cannot load group form id `' . $id . '` | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                PEAR_LOG_ERR
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
        $this->_id = $r->id_groupe;
        $this->_group_name = $r->group_name;
        $this->_creation_date = $r->creation_date;
        $adhpk = Adherent::PK;
        $this->_owner = new Adherent((int)$r->$adhpk);
    }

    /**
     * Get groups list
     *
     * @return Zend_Db_RowSet
     */
    public function getList()
    {
        global $zdb, $log;
        try {
            $select = new Zend_Db_Select($zdb->db);
            $select->from(
                array('a' => PREFIX_DB . self::TABLE)
            );
            $groups = array();
            foreach ( $select->query()->fetchAll() as $row ) {
                $groups[] = new Groups($row);
            }
            return $groups;
        } catch (Exception $e) {
            $log->log(
                'Cannot list groups | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                PEAR_LOG_ERR
            );
        }
    }

    /**
     * Remove specified groups
     *
     * @param integer|array $ids Group(s) identifier(s)
     *
     * @return boolean
     */
    public function removeEntries($ids)
    {
        global $zdb, $log;

        $list = array();
        if ( is_numeric($ids) ) {
            //we've got only one identifier
            $list[] = $ids;
        } else {
            $list = $ids;
        }

        if ( is_array($list) ) {
            try {
                $zdb->db->beginTransaction();

                //delete members
                $del = $zdb->db->delete(
                    PREFIX_DB . self::TABLE,
                    self::PK . ' IN (' . implode(',', $list) . ')'
                );

                //commit all changes
                $zdb->db->commit();

                return true;
            } catch (Exception $e) {
                $zdb->db->rollBack();
                $log->log(
                    'Unable to delete selected groups |' .
                    $e->getMessage(),
                    PEAR_LOG_ERR
                );
                return false;
            }
        } else {
            //not numeric and not an array: incorrect.
            $log->log(
                'Asking to remove groups, but without providing an array or a single numeric value.',
                PEAR_LOG_WARNING
            );
            return false;
        }
    }

    /**
     * Loads groups for specific member
     *
     * @param int $id Memebr id
     * @return array
     */
    public static function loadGroups($id)
    {
        global $zdb, $log;
        try {
            $select = new Zend_Db_Select($zdb->db);
            $select->from(
                array(
                    'a' => PREFIX_DB . self::TABLE
                )
            )->join(
                array(
                    'b' => PREFIX_DB . self::USERSGROUPS_TABLE
                ),
                'a.' . self::PK . '=b.' . self::PK,
                array('manager')
            )->where(Adherent::PK . ' = ?', $id);
            $result = $select->query()->fetchAll();
            $groups = array();
            foreach ( $result as $r ) {
                $groups[$r->group_name] = $r->manager;
            }
            return $groups;
        } catch (Exception $e) {
            $log->log(
                'Cannot load member groups for id `' . $id . '` | ' .
                $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                PEAR_LOG_ERR
            );
            return false;
        }
    }

    /**
     * Is current loggedin user owner of the group?
     *
     * @return boolean
     */
    public function isOwner()
    {
        global $login;
        if ( $login->isAdmin() || $login->isStaff() ) {
            //admins are groups owners, as well as staff members!
            return true;
        } else {
            //let's check if current uloggedin user is group owner
            return $this->_owner->login == $login->login;
        }
    }

    /**
     * Can currently loggedin user manage group?
     *
     * @return boolean
     */
    public function canManage()
    {
        global $login;
        if ( $this->isOwner() ) {
            return true;
        } else {
            /** TODO: check if current loggedin member can manage group */
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
     * Get group owner
     *
     * @return Adherent
     */
    public function getOwner()
    {
        return $this->_owner;
    }

    /**
     * Get group members
     *
     * @return Adherent[]
     */
    public function getMembers()
    {
        return $this->_members;
    }

    /**
     * Get group creation date
     *
     * @return string
     */
    public function getCreationDate()
    {
        return $this->_creation_date;
    }
}

?>
