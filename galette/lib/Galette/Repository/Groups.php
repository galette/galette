<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Groups entity
 *
 * PHP version 5
 *
 * Copyright © 2011-2012 The Galette Team
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
 * @copyright 2011-2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-10-25
 */

namespace Galette\Repository;

use Galette\Entity\Group as Group;
use Galette\Entity\Adherent as Adherent;

/**
 * Groups entitiy
 *
 * @category  Classes
 * @name      Groups
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-10-25
 */
class Groups
{

    /**
     * Get simple groups list (only id and names)
     *
     * @return array
     */
    public static function getSimpleList()
    {
        global $zdb, $log;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(
                PREFIX_DB . Group::TABLE,
                array(Group::PK, 'group_name')
            );
            $groups = array();
            $q = $select->__toString();
            $gpk = Group::PK;
            foreach ( $select->query()->fetchAll() as $row ) {
                $groups[$row->$gpk] = $row->group_name;
            }
            return $groups;
        } catch (\Exception $e) {
            $log->log(
                'Cannot list groups (simple) | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->getTraceAsString(),
                PEAR_LOG_ERR
            );

        }
    }

    /**
     * Get groups list
     *
     * @param boolean $full Return full list or root only
     *
     * @return Zend_Db_RowSet
     */
    public function getList($full = true)
    {
        global $zdb, $log, $login;
        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(
                array('a' => PREFIX_DB . Group::TABLE)
            )->joinLeft(
                array('b' => PREFIX_DB . Group::GROUPSUSERS_TABLE),
                'a.' . Group::PK . '=b.' . Group::PK,
                array('members' => new \Zend_Db_Expr('count(b.' . Group::PK . ')'))
            );

            if ( !$login->isAdmin() && !$login->isStaff() && $full === true ) {
                $select->join(
                    array('c' => PREFIX_DB . Group::GROUPSMANAGERS_TABLE),
                    'a.' . Group::PK . '=c.' . Group::PK,
                    array()
                )->where('c.' . Adherent::PK . ' = ?', $login->id);
            }

            if ( $full !== true ) {
                $select->where('parent_group IS NULL');
            }

            $select->group('a.' . Group::PK)->order('group_name ASC');

            $groups = array();
            foreach ( $select->query()->fetchAll() as $row ) {
                $groups[] = new Group($row);
            }
            return $groups;
        } catch (\Exception $e) {
            $log->log(
                'Cannot list groups | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->getTraceAsString(),
                PEAR_LOG_ERR
            );
        }
    }

    /**
     * Loads managed groups for specific member
     *
     * @param int     $id       Memebr id
     * @param boolean $as_group Retrieve Group[] or int[]
     *
     * @return array
     */
    public static function loadManagedGroups($id, $as_group = true)
    {
        return self::loadGroups($id, true, $as_group);
    }

    /**
     * Loads groups for specific member
     *
     * @param int     $id       Memebr id
     * @param boolean $managed  Retrieve managed groups (defaults to false)
     * @param boolean $as_group Retrieve Group[] or int[]
     *
     * @return array
     */
    public static function loadGroups($id, $managed = false, $as_group = true)
    {
        global $zdb, $log;
        try {
            $join_table = ($managed) ?
                Group::GROUPSMANAGERS_TABLE :
                Group::GROUPSUSERS_TABLE;
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(
                array(
                    'a' => PREFIX_DB . Group::TABLE
                )
            )->join(
                array(
                    'b' => PREFIX_DB . $join_table
                ),
                'a.' . Group::PK . '=b.' . Group::PK,
                array()
            )->where('b.' . Adherent::PK . ' = ?', $id);
            $result = $select->query()->fetchAll();
            $log->log(
                'Exectued query: ' . $select->__toString(),
                PEAR_LOG_DEBUG
            );
            $groups = array();
            foreach ( $result as $r ) {
                if ( $as_group === true ) {
                    $groups[] = new Group($r);
                } else {
                    $gpk = Group::PK;
                    $groups[] = $r->$gpk;
                }
            }
            return $groups;
        } catch (\Exception $e) {
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
     * Add a member to specified groups
     *
     * @param Adherent $adh    Member
     * @param array    $groups Groups Groups list. Each entry must contain
     *                                the group id, name each value separated
     *                                by a pipe.
     *
     * @return boolean
     */
    public static function addMemberToGroups($adh, $groups)
    {
        global $zdb, $log;
        try {
            $zdb->db->beginTransaction();

            //first, remove current groups members
            $del = $zdb->db->delete(
                PREFIX_DB . Group::GROUPSUSERS_TABLE,
                Adherent::PK . ' = ' . $adh->id
            );
            $log->log(
                'Member `' . $adh->sname . '` has been detached of its groups' .
                ', we can now store new ones.',
                PEAR_LOG_INFO
            );

            //we proceed, if grousp has been specified
            if ( is_array($groups) ) {
                $stmt = $zdb->db->prepare(
                    'INSERT INTO ' . PREFIX_DB . Group::GROUPSUSERS_TABLE .
                    ' (' . $zdb->db->quoteIdentifier(Group::PK) . ', ' .
                    $zdb->db->quoteIdentifier(Adherent::PK) . ')' .
                    ' VALUES(:id, ' . $adh->id . ')'
                );

                foreach ( $groups as $group ) {
                    list($gid, $gname) = explode('|', $group);
                    $stmt->bindValue(':id', $gid, PDO::PARAM_INT);

                    if ( $stmt->execute() ) {
                        $log->log(
                            'Member `' . $adh->sname . '` attached to group `' .
                            $gname . '` (' . $gid . ').',
                            PEAR_LOG_DEBUG
                        );
                    } else {
                        $log->log(
                            'An error occured trying to attach member `' .
                            $adh->sname . '` (' . $adh->id . ') to group `' .
                            $gname . '` (' . $gid . ').',
                            PEAR_LOG_ERR
                        );
                        throw new Exception(
                            'Unable to attach `' . $adh->sname . '` (' . $adh->id .
                            ') to `' . $gname . '` (' . $gid . ')'
                        );
                    }
                }
            }
            //commit all changes
            $zdb->db->commit();
            return true;
        } catch (\Exception $e) {
            $zdb->db->rollBack();
            $log->log(
                'Unable to add member `' . $adh->sname . '` (' . $adh->id .
                ') to specified groups |' .
                $e->getMessage(),
                PEAR_LOG_ERR
            );
            return false;
        }
    }

    /**
     * Check if groupname is unique
     *
     * @param string $name Requested name
     *
     * @return boolean
     */
    public static function isUnique($name)
    {
        global $zdb, $log;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(
                PREFIX_DB . Group::TABLE,
                array('group_name')
            )->where('group_name = ?', $name);
            $res = $select->query()->fetchAll();
            return !(count($res) > 0);
        } catch (\Exception $e) {
            $log->log(
                'Cannot list groups (simple) | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->getTraceAsString(),
                PEAR_LOG_ERR
            );
        }
    }
}

?>
