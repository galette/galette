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
    const PK = 'id';
    const USERSGROUPS_PK = 'id_group';

    private $_id;
    private $_group_name;
    private $_owner;
    private $_members;

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
                'a.' . self::PK . '=b.' . self::USERSGROUPS_PK,
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
}

?>
