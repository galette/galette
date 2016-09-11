<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Groups entity
 *
 * PHP version 5
 *
 * Copyright © 2011-2014 The Galette Team
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
 * @copyright 2011-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-10-25
 */

namespace Galette\Repository;

use Analog\Analog;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\PredicateSet;
use Galette\Entity\Group;
use Galette\Entity\Adherent;
use Galette\Core\Login;
use Galette\Core\Db;

/**
 * Groups entitiy
 *
 * @category  Repository
 * @name      Groups
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-10-25
 */
class Groups
{

    /**
     * Constructor
     *
     * @param Db    $zdb   Database instance
     * @param Login $login Login instance
     */
    public function __construct(Db $zdb, Login $login)
    {
        $this->zdb = $zdb;
        $this->login = $login;
    }

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
            $select = $zdb->select(Group::TABLE);
            if ($as_groups === false) {
                $select->columns(
                    array(Group::PK, 'group_name')
                );
            }
            $groups = array();
            $gpk = Group::PK;

            $results = $zdb->execute($select);

            foreach ($results as $row) {
                if ($as_groups === false) {
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
        }
    }

    /**
     * Get groups list
     *
     * @param boolean $full Return full list or root only
     * @param int     $id   Group ID to retrieve
     *
     * @return Group[]
     */
    public function getList($full = true, $id = null)
    {
        try {
            $select = $this->zdb->select(Group::TABLE, 'a');
            $select->join(
                array('b' => PREFIX_DB . Group::GROUPSUSERS_TABLE),
                'a.' . Group::PK . '=b.' . Group::PK,
                array('members' => new Expression('count(b.' . Group::PK . ')')),
                $select::JOIN_LEFT
            );

            if (!$this->login->isAdmin() && !$this->login->isStaff() && $full === true) {
                $select->join(
                    array('c' => PREFIX_DB . Group::GROUPSMANAGERS_TABLE),
                    'a.' . Group::PK . '=c.' . Group::PK,
                    array()
                )->where('c.' . Adherent::PK . ' = ' . $this->login->id);
            }

            if ($full !== true) {
                $select->where('parent_group IS NULL');
            }

            if ($id !== null) {
                $select->where(
                    array(
                        'a.' . Group::PK => $id,
                        'a.parent_group' => $id
                    ),
                    PredicateSet::OP_OR
                );
            }

            $select->group('a.' . Group::PK)
                ->group('a.group_name')
                ->group('a.creation_date')
                ->group('a.parent_group')
                ->order('a.group_name ASC');

            $groups = array();

            $results = $this->zdb->execute($select);

            foreach ($results as $row) {
                $group = new Group($row);
                $group->setLogin($this->login);
                $groups[$group->getFullName()] = $group;
            }
            if ($full) { // Order by tree name instead of name
                ksort($groups);
                Analog::log(
                    'SORTED:' . print_r(array_keys($groups), true),
                    Analog::WARNING
                );
            }
            return $groups;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list groups | ' . $e->getMessage(),
                Analog::WARNING
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

            $select = $zdb->select(Group::TABLE, 'a');
            $select->join(
                array(
                    'b' => PREFIX_DB . $join_table
                ),
                'a.' . Group::PK . '=b.' . Group::PK,
                array()
            )->where(array('b.' . Adherent::PK => $id));

            $results = $zdb->execute($select);

            $groups = array();
            foreach ( $results as $r ) {
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
                $zdb->connection->beginTransaction();
            }

            $table = null;
            if ( $manager === true ) {
                $table = Group::GROUPSMANAGERS_TABLE;
            } else {
                $table = Group::GROUPSUSERS_TABLE;
            }

            //first, remove current groups members
            $delete = $zdb->delete($table);
            $delete->where(
                Adherent::PK . ' = ' . $adh->id
            );
            $zdb->execute($delete);

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
                $insert = $zdb->insert($table);
                $insert->values(
                    array(
                        Group::PK       => ':group',
                        Adherent::PK    => ':adh'
                    )
                );
                $stmt = $zdb->sql->prepareStatementForSqlObject($insert);

                foreach ( $groups as $group ) {
                    list($gid, $gname) = explode('|', $group);

                    $result = $stmt->execute(
                        array(
                            Group::PK       => $gid,
                            Adherent::PK    => $adh->id
                        )
                    );

                    if ( $result ) {
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
                $zdb->connection->commit();
            }
            return true;
        } catch (\Exception $e) {
            if ( $transaction === false) {
                $zdb->connection->rollBack();
            }
            $msg = 'Unable to add member `' . $adh->sname . '` (' . $adh->id .
                ') to specified groups ' . print_r($groups, true);
            if ( $manager === true ) {
                $msg .= ' as a manager';
            }
            do {
                $messages[] = $e->getMessage();
            } while ($e = $e->getPrevious());
            Analog::log(
                $msg . ' |' . implode("\n", $messages),
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
            $del_qry = $zdb->delete(Group::GROUPSUSERS_TABLE);
            $del_qry->where(
                Adherent::PK . ' = ' . $id
            );
            $zdb->execute($del_qry);

            //first, remove current groups members
            $del_qry = $zdb->delete(Group::GROUPSMANAGERS_TABLE);
            $del_qry->where(
                Adherent::PK . ' = ' . $id
            );
            $zdb->execute($del_qry);
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
     * @param Db     $zdb  Database instance
     * @param string $name Requested name
     *
     * @return boolean
     */
    public static function isUnique(Db $zdb, $name)
    {
        try {
            $select = $zdb->select(Group::TABLE);
            $select->columns(
                array('group_name')
            )->where(array('group_name' => $name));
            $results = $zdb->execute($select);
            return !($results->count() > 0);
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list groups (simple) | ' . $e->getMessage(),
                Analog::WARNING
            );
        }
    }
}
