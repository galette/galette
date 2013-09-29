<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Groups entity
 *
 * PHP version 5
 *
 * Copyright © 2011-2013 The Galette Team
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
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-10-25
 */

namespace Galette\Repository;

use Analog\Analog as Analog;
use Galette\Entity\Group as Group;
use Galette\Entity\Adherent as Adherent;

/**
 * Groups entitiy
 *
 * @category  Repository
 * @name      Groups
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-10-25
 */
class Groups
{

    /**
     * Get simple groups list (only id and names)
     *
     * @param boolean $as_groups Retrieve Group[]
     *
     * @return array
     */
    public static function getSimpleList($as_groups = false)
    {
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            if ( $as_groups === false ) {
                $select->from(
                    PREFIX_DB . Group::TABLE,
                    array(Group::PK, 'group_name')
                );
            } else {
                 $select->from(
                    PREFIX_DB . Group::TABLE
                );
            }
            $groups = array();
            $q = $select->__toString();
            $gpk = Group::PK;
            $res = $select->query()->fetchAll();
            foreach ( $res as $row ) {
                if ( $as_groups === false ) {
                    $groups[$row->$gpk] = $row->group_name;
                } else {
                    $groups[$row->$gpk] = new Group($row);
                }
            }
            return $groups;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list groups (simple) | ' . $e->getMessage(),
                Analog::WARNING
            );
            Analog::log(
                'Query was: ' . $select->__toString() . ' ' . $e->getTraceAsString(),
                Analog::ERROR
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
        global $zdb, $login;
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

            $select->group('a.' . Group::PK)
                ->group('a.group_name')
                ->group('a.creation_date')
                ->group('a.parent_group')
                ->order('a.group_name ASC');

            $groups = array();
            $res = $select->query()->fetchAll();
            foreach ( $res as $row ) {
                $groups[] = new Group($row);
            }
            return $groups;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list groups | ' . $e->getMessage(),
                Analog::WARNING
            );
            Analog::log(
                'Query was: ' . $select->__toString() . ' ' . $e->getTraceAsString(),
                Analog::ERROR
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
        global $zdb;
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
            Analog::log(
                'Exectued query: ' . $select->__toString(),
                Analog::DEBUG
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
            Analog::log(
                'Cannot load member groups for id `' . $id . '` | ' .
                $e->getMessage(),
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
     * Add a member to specified groups
     *
     * @param Adherent $adh         Member
     * @param array    $groups      Groups Groups list. Each entry must contain
     *                                the group id, name each value separated
     *                                by a pipe.
     * @param boolean  $manager     Add member as manager, defaults to false
     * @param boolean  $transaction Does a SQL transaction already exists? Defaults
     *                                 to false.
     *
     * @return boolean
     */
    public static function addMemberToGroups($adh, $groups, $manager = false, $transaction = false)
    {
        global $zdb;
        try {
            if ( $transaction === false) {
                $zdb->db->beginTransaction();
            }

            $table = null;
            if ( $manager === true ) {
                $table = Group::GROUPSMANAGERS_TABLE;
            } else {
                $table = Group::GROUPSUSERS_TABLE;
            }

            //first, remove current groups members
            $del = $zdb->db->delete(
                PREFIX_DB . $table,
                Adherent::PK . ' = ' . $adh->id
            );

            $msg = null;
            if ( $manager === true ) {
                $msg = 'Member `' . $adh->sname . '` has been detached from groups he manages';
            } else {
                $msg = 'Member `' . $adh->sname . '` has been detached of its groups';
            }
            Analog::log(
                $msg . ', we can now store new ones.',
                Analog::INFO
            );

            //we proceed, if groups has been specified
            if ( is_array($groups) ) {
                $stmt = $zdb->db->prepare(
                    'INSERT INTO ' . PREFIX_DB . $table .
                    ' (' . $zdb->db->quoteIdentifier(Group::PK) . ', ' .
                    $zdb->db->quoteIdentifier(Adherent::PK) . ')' .
                    ' VALUES(:id, ' . $adh->id . ')'
                );

                foreach ( $groups as $group ) {
                    list($gid, $gname) = explode('|', $group);
                    $stmt->bindValue(':id', $gid, \PDO::PARAM_INT);

                    if ( $stmt->execute() ) {
                        $msg = 'Member `' . $adh->sname . '` attached to group `' .
                            $gname . '` (' . $gid . ')';
                        if ( $manager === true ) {
                            $msg .= ' as a manager';
                        }
                        Analog::log(
                            $msg,
                            Analog::DEBUG
                        );
                    } else {
                        $msg = 'Unable to attach member `' .
                            $adh->sname . '` (' . $adh->id . ') to group `' .
                            $gname . '` (' . $gid . ').';
                        if ( $manager === true ) {
                            $msg .= ' as a manager';
                        }
                        Analog::log(
                            $msg,
                            Analog::ERROR
                        );
                        throw new \Exception($msg);
                    }
                }
            }
            if ( $transaction === false) {
                //commit all changes
                $zdb->db->commit();
            }
            return true;
        } catch (\Exception $e) {
            if ( $transaction === false) {
                $zdb->db->rollBack();
            }
            $msg = 'Unable to add member `' . $adh->sname . '` (' . $adh->id .
                ') to specified groups ' . print_r($groups, true);
            if ( $manager === true ) {
                $msg .= ' as a manager';
            }
            Analog::log(
                $msg . ' |' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Remove member from all his groups
     *
     * @param int $id Member's id
     *
     * @return void
     */
    public static function removeMemberFromGroups($id)
    {
        global $zdb;
        try {
            //first, remove current groups members
            $del = $zdb->db->delete(
                PREFIX_DB . Group::GROUPSUSERS_TABLE,
                Adherent::PK . ' = ' . $id
            );

            //first, remove current groups members
            $del = $zdb->db->delete(
                PREFIX_DB . Group::GROUPSMANAGERS_TABLE,
                Adherent::PK . ' = ' . $id
            );
        } catch ( \Exception $e) {
            Analog::log(
                'Unable to remove member #' . $id . ' from his groups: ' .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
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
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(
                PREFIX_DB . Group::TABLE,
                array('group_name')
            )->where('group_name = ?', $name);
            $res = $select->query()->fetchAll();
            return !(count($res) > 0);
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list groups (simple) | ' . $e->getMessage(),
                Analog::WARNING
            );
            Analog::log(
                'Query was: ' . $select->__toString() . ' ' . $e->getTraceAsString(),
                Analog::ERROR
            );
        }
    }
}

