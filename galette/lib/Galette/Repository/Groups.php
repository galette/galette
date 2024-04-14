<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

namespace Galette\Repository;

use ArrayObject;
use Galette\Entity\Status;
use Throwable;
use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\PredicateSet;
use Galette\Entity\Group;
use Galette\Entity\Adherent;
use Galette\Core\Login;
use Galette\Core\Db;

/**
 * Groups entity
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Groups
{
    private Db $zdb;
    private Login $login;

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
     * @return array<int, Group|string>
     */
    public static function getSimpleList(bool $as_groups = false): array
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
        } catch (Throwable $e) {
            Analog::log(
                'Cannot list groups (simple) | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Get groups list
     *
     * @param boolean  $full Return full list or root only
     * @param int|null $id   Group ID to retrieve
     *
     * @return Group[]
     */
    public function getList(bool $full = true, ?int $id = null): array
    {
        try {
            $select = $this->zdb->select(Group::TABLE, 'ggroup');

            if (!$this->login->isAdmin() && !$this->login->isStaff() && $full === true) {
                $select->join(
                    array('gmanagers' => PREFIX_DB . Group::GROUPSMANAGERS_TABLE),
                    'ggroup.' . Group::PK . '=gmanagers.' . Group::PK,
                    array()
                )->where(['gmanagers.' . Adherent::PK => $this->login->id]);
            }

            $select->join(
                array('gusers' => PREFIX_DB . Group::GROUPSUSERS_TABLE),
                'ggroup.' . Group::PK . '=gusers.' . Group::PK,
                array('members' => new Expression('count(gusers.' . Group::PK . ')')),
                $select::JOIN_LEFT
            );

            if ($full !== true) {
                $select->where('ggroup.parent_group IS NULL');
            }

            if ($id !== null) {
                $select->where
                    ->nest()
                        ->equalTo('ggroup.' . Group::PK, $id)
                    ->or
                        ->equalTo('ggroup.parent_group', $id)
                    ->unnest()
                ;
            }

            $select->group('ggroup.' . Group::PK)
                ->group('ggroup.group_name')
                ->group('ggroup.creation_date')
                ->group('ggroup.parent_group')
                ->order('ggroup.group_name ASC');

            $groups = array();

            $results = $this->zdb->execute($select);

            foreach ($results as $row) {
                /** @var ArrayObject<string, int|string> $row */
                $group = new Group($row);
                $group->setLogin($this->login);
                $groups[$group->getFullName()] = $group;
            }
            if ($full) { // Order by tree name instead of name
                ksort($groups);
            }
            return $groups;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot list groups | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Loads managed groups for specific member
     *
     * @param int     $id       Member id
     * @param boolean $as_group Retrieve Group[] or int[]
     *
     * @return array<int, Group|int>
     */
    public static function loadManagedGroups(int $id, bool $as_group = true): array
    {
        return self::loadGroups($id, true, $as_group);
    }

    /**
     * Loads groups for specific member
     *
     * @param int     $id       Member id
     * @param boolean $managed  Retrieve managed groups (defaults to false)
     * @param boolean $as_group Retrieve Group[] or int[]
     *
     * @return array<int, Group|int>
     */
    public static function loadGroups(int $id, bool $managed = false, bool $as_group = true): array
    {
        global $zdb;
        try {
            $join_table = ($managed) ?
                Group::GROUPSMANAGERS_TABLE : Group::GROUPSUSERS_TABLE;

            $select = $zdb->select(Group::TABLE, 'group');
            $select->join(
                array(
                    'b' => PREFIX_DB . $join_table
                ),
                'group.' . Group::PK . '=b.' . Group::PK,
                array()
            )->where(array('b.' . Adherent::PK => $id));

            $results = $zdb->execute($select);

            $groups = array();
            foreach ($results as $r) {
                if ($as_group === true) {
                    $groups[] = new Group($r);
                } else {
                    $gpk = Group::PK;
                    $groups[] = $r->$gpk;
                }
            }
            return $groups;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot load member groups for id `' . $id . '` | ' .
                $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Add a member to specified groups
     *
     * @param Adherent      $adh         Member
     * @param array<string> $groups      Groups Groups list. Each entry must contain
     *                                   the group id, name each value separated
     *                                   by a pipe.
     * @param boolean       $manager     Add member as manager, defaults to false
     * @param boolean       $transaction Does a SQL transaction already exists? Defaults
     *                                   to false.
     *
     * @return boolean
     */
    public static function addMemberToGroups(Adherent $adh, array $groups, bool $manager = false, bool $transaction = false): bool
    {
        global $zdb, $login;

        $managed_groups = [];
        if (!$login->isSuperAdmin() && !$login->isAdmin() && !$login->isStaff()) {
            $managed_groups = $login->getManagedGroups();
        }

        try {
            if ($transaction === false) {
                $zdb->connection->beginTransaction();
            }

            $table = null;
            if ($manager === true) {
                $table = Group::GROUPSMANAGERS_TABLE;
            } else {
                $table = Group::GROUPSUSERS_TABLE;
            }

            //first, remove current groups members
            $delete = $zdb->delete($table);
            $delete->where([Adherent::PK => $adh->id]);
            if (count($managed_groups)) {
                $delete->where->in(Group::PK, $managed_groups);
            }
            $zdb->execute($delete);

            $msg = null;
            if ($manager === true) {
                $msg = 'Member `' . $adh->sname . '` has been detached from groups he manages';
            } else {
                $msg = 'Member `' . $adh->sname . '` has been detached of its groups';
            }
            Analog::log(
                $msg . ', we can now store new ones.',
                Analog::INFO
            );

            //we proceed, if groups has been specified
            if (count($groups)) {
                $insert = $zdb->insert($table);
                $insert->values(
                    array(
                        Group::PK       => ':group',
                        Adherent::PK    => ':adh'
                    )
                );
                $stmt = $zdb->sql->prepareStatementForSqlObject($insert);

                foreach ($groups as $group) {
                    list($gid, $gname) = explode('|', $group);

                    if (count($managed_groups) && !in_array($gid, $managed_groups)) {
                        continue;
                    }

                    $result = $stmt->execute(
                        array(
                            'group' => $gid,
                            'adh'   => $adh->id
                        )
                    );

                    if ($result) {
                        $msg = 'Member `' . $adh->sname . '` attached to group `' .
                            $gname . '` (' . $gid . ')';
                        if ($manager === true) {
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
                        if ($manager === true) {
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
            if ($transaction === false) {
                //commit all changes
                $zdb->connection->commit();
            }
            return true;
        } catch (Throwable $e) {
            $te = clone $e;
            if ($transaction === false) {
                $zdb->connection->rollBack();
            }
            $msg = 'Unable to add member `' . $adh->sname . '` (' . $adh->id .
                ') to specified groups ' . print_r($groups, true);
            if ($manager === true) {
                $msg .= ' as a manager';
            }
            do {
                $messages[] = $e->getMessage();
            } while ($e = $e->getPrevious());
            Analog::log(
                $msg . ' |' . implode("\n", $messages),
                Analog::ERROR
            );
            throw $te;
        }
    }

    /**
     * Remove members from all their groups
     *
     * @param array<int> $ids Members ids
     *
     * @return void
     */
    public static function removeMembersFromGroups(array $ids): void
    {
        global $zdb;

        try {
            $del_qry = $zdb->delete(Group::GROUPSUSERS_TABLE);
            $del_qry->where->in(Adherent::PK, $ids);
            $zdb->execute($del_qry);

            $del_qry = $zdb->delete(Group::GROUPSMANAGERS_TABLE);
            $del_qry->where->in(Adherent::PK, $ids);
            $zdb->execute($del_qry);
        } catch (Throwable $e) {
            Analog::log(
                'Unable to remove member #' . implode(', ', $ids) . ' from his groups: ' .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Remove member from all his groups
     *
     * @param int $id Member's id
     *
     * @return void
     */
    public static function removeMemberFromGroups(int $id): void
    {
        self::removeMembersFromGroups([$id]);
    }

    /**
     * Check if groupname is unique
     *
     * @param Db       $zdb     Database instance
     * @param string   $name    Requested name
     * @param int|null $parent  Parent group (defaults to null)
     * @param int|null $current Current ID to be excluded (defaults to null)
     *
     * @return boolean
     */
    public static function isUnique(Db $zdb, string $name, ?int $parent = null, ?int $current = null): bool
    {
        try {
            $select = $zdb->select(Group::TABLE);
            $select->columns(['group_name'])
                ->where(['group_name'    => $name]);

            if ($parent === null) {
                $select->where('parent_group IS NULL');
            } else {
                $select->where(['parent_group' => $parent]);
            }

            if ($current !== null) {
                $select->where->notEqualTo(Group::PK, $current);
            }

            $results = $zdb->execute($select);
            return !($results->count() > 0);
        } catch (Throwable $e) {
            Analog::log(
                'Cannot check group name uniqueness | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Get managed users id list
     *
     * @param array<int, Group|int> $groups List of managed groups.
     *                                      If empty, Groups::loadManagedGroups() will be called
     *
     * @return array<int>|false
     */
    public function getManagerUsers(array $groups = []): array|false
    {
        if (!$this->login->isGroupManager()) {
            return false;
        }
        if (!count($groups)) {
            $groups = self::loadManagedGroups($this->login->id, false);
        }

        $select = $this->zdb->select(Adherent::TABLE, 'adh');
        $select->columns(
            [Adherent::PK]
        )->join(
            array('status' => PREFIX_DB . Status::TABLE),
            'a.' . Status::PK . '=status.' . Status::PK,
            array('priorite_statut')
        )->join(
            array('b' => PREFIX_DB . Group::GROUPSUSERS_TABLE),
            'adh.' . Adherent::PK . '=b.' . Adherent::PK,
            []
        )->where->in('b.' . Group::PK, $groups);

        $results = $this->zdb->execute($select);

        $ids_adh = array();
        foreach ($results as $r) {
            $ids_adh[] = $r->id_adh;
        }
        return $ids_adh;
    }
}
