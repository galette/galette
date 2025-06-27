<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

namespace Galette\Entity;

use ArrayObject;
use Galette\Repository\Groups;
use Throwable;
use Galette\Core\Login;
use Analog\Analog;
use Laminas\Db\Sql\Expression;

/**
 * Group entity
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
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

    private int $id;
    private string $group_name;
    private ?Group $parent_group = null;
    /** @var array<int,Adherent>|null */
    private ?array $managers = null;
    /** @var array<int,Adherent>|null */
    private ?array $members = null;
    /** @var array<int,Group>|null */
    private ?array $groups = null;
    private string $creation_date;
    private int $count_members;
    private bool $isempty;
    private Login $login;

    /**
     * Default constructor
     *
     * @param null|int|ArrayObject<string,int|string> $args Either a ResultSet row or its id for to load
     *                                                      a specific group, or null to just
     *                                                      instanciate object
     */
    public function __construct(ArrayObject|int|null $args = null)
    {
        if ($args === null || is_int($args)) {
            if (is_int($args) && $args > 0) {
                $this->load($args);
            }
        } elseif ($args instanceof ArrayObject) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Loads a group from its id
     *
     * @param int $id the identifier for the group to load
     *
     * @return bool true if query succeed, false otherwise
     */
    public function load(int $id): bool
    {
        global $zdb;

        try {
            $select = $zdb->select(self::TABLE);
            $select->where([self::PK => $id]);

            $results = $zdb->execute($select);

            if ($results->count() > 0) {
                $this->loadFromRS($results->current());
                return true;
            } else {
                return false;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Cannot load group from id `' . $id . '` | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Load group from its name
     *
     * @param string $group_name Group name
     *
     * @return bool
     */
    public function loadFromName(string $group_name): bool
    {
        global $zdb;

        try {
            $select = $zdb->select(self::TABLE);
            $select->where(['group_name' => $group_name]);

            $results = $zdb->execute($select);

            if ($results->count() > 0) {
                $this->loadFromRS($results->current());
                return true;
            } else {
                return false;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Cannot load group from name `' . $group_name . '` | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ArrayObject<string, int|string> $r the resultset row
     *
     * @return void
     */
    private function loadFromRS(ArrayObject $r): void
    {
        $this->id = (int)$r->id_group;
        $this->group_name = $r->group_name;
        $this->creation_date = $r->creation_date;
        if ($r->parent_group) {
            $this->parent_group = new Group((int)$r->parent_group);
        }
        if (isset($r->members)) {
            //we're from a list, we just want members count
            $this->count_members = (int)$r->members;
        }
    }

    /**
     * Loads members for the current group
     *
     * @param int $type Either self::MEMBER_TYPE or self::MANAGER_TYPE
     *
     * @return void
     */
    private function loadPersons(int $type): void
    {
        global $zdb;

        if (isset($this->id)) {
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
                    ['status' => PREFIX_DB . Status::TABLE],
                    'a.' . Status::PK . '=status.' . Status::PK,
                    ['priorite_statut']
                );
                $select->join(
                    ['g' => $join],
                    'g.' . Adherent::PK . '=a.' . Adherent::PK,
                    []
                )->where([
                    'g.' . self::PK => $this->id
                ])->order(
                    'nom_adh ASC',
                    'prenom_adh ASC'
                );

                $results = $zdb->execute($select);
                $members = [];

                $deps = [
                    'picture'   => false,
                    'groups'    => false,
                    'dues'      => false
                ];

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
    private function loadSubGroups(): void
    {
        global $zdb;

        if (!isset($this->login) || !$this->login->isLogged()) {
            $this->groups = [];
            return;
        }

        try {
            $select = $zdb->select(self::TABLE, 'a');

            if (!$this->login->isAdmin() && !$this->login->isStaff()) {
                $select->join(
                    ['b' => PREFIX_DB . self::GROUPSMANAGERS_TABLE],
                    'a.' . self::PK . '=b.' . self::PK,
                    []
                )->where(['b.' . Adherent::PK => $this->login->id]);
            }

            $select->where(['parent_group' => $this->id])
                ->order('group_name ASC');

            $results = $zdb->execute($select);
            $groups = [];
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
    public function remove(bool $cascade = false): bool
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
            if ($zdb->isForeignKeyException($e)) {
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
    public function isEmpty(): bool
    {
        return $this->isempty;
    }

    /**
     * Detach a group from its parent
     *
     * @return boolean
     */
    public function detach(): bool
    {
        global $zdb, $hist;

        try {
            $update = $zdb->update(self::TABLE);
            $update->set(
                ['parent_group' => new Expression('NULL')]
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
    public function store(): bool
    {
        global $zdb, $hist;

        $parent_group = null;
        if ($this->parent_group) {
            $parent_group = $this->parent_group->getId();
        }
        if (!Groups::isUnique($zdb, $this->getName(), $parent_group, $this->id ?? null)) {
            throw new \RuntimeException(
                _T("The group name you have requested already exists in the database.")
            );
        }

        try {
            $values = [
                'group_name' => $this->group_name
            ];

            if ($this->parent_group) {
                $values['parent_group'] = $parent_group;
            }

            if (!isset($this->id)) {
                //we're inserting a new group
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
                $values[self::PK] = $this->id;
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
    public function isManager(Login $login): bool
    {
        if ($login->isAdmin() || $login->isStaff()) {
            //admins as well as staff members are managers for all groups!
            return true;
        } else {
            //let's check if current logged-in user is part of group managers
            if (!is_array($this->managers)) {
                $this->loadPersons(self::MANAGER_TYPE);
            }

            foreach ($this->managers as $manager) {
                if ($login->login == $manager->login) {
                    return true;
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
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Get Level of the group
     *
     * @return integer
     */
    public function getLevel(): int
    {
        if ($this->parent_group) {
            return $this->parent_group->getLevel() + 1;
        }
        return 0;
    }

    /**
     * Get the full name of the group "foo / bar"
     *
     * @return ?string
     */
    public function getFullName(): ?string
    {
        if ($this->parent_group) {
            return $this->parent_group->getFullName() . ' / ' . $this->group_name;
        }
        return $this->group_name ?? null;
    }

    /**
     * Get parents as an array
     *
     * @return array<int, string>
     */
    public function getParents(): array
    {
        $parents = [];
        $group = $this;
        while ($group = $group->getParentGroup()) {
            array_unshift($parents, $group->getName());
        }
        return $parents;
    }


    /**
     * Get the indented short name of the group "  >> bar"
     *
     * @return ?string
     */
    public function getIndentName(): ?string
    {
        if ($level = $this->getLevel()) {
            return str_repeat("&nbsp;", 3 * $level) . '&raquo; ' . $this->group_name;
        }
        return $this->group_name ?? null;
    }

    /**
     * Get group name
     *
     * @return ?string
     */
    public function getName(): ?string
    {
        return $this->group_name ?? null;
    }

    /**
     * Get group members
     *
     * @return array<int, Adherent>
     */
    public function getMembers(): array
    {
        if (!is_array($this->members)) {
            $this->loadPersons(self::MEMBER_TYPE);
        }
        return $this->members;
    }

    /**
     * Get groups managers
     *
     * @return array<int, Adherent>
     */
    public function getManagers(): array
    {
        if (!is_array($this->managers)) {
            $this->loadPersons(self::MANAGER_TYPE);
        }
        return $this->managers;
    }

    /**
     * Get subgroups
     *
     * @return array<int, Group>
     */
    public function getGroups(): array
    {
        if (!is_array($this->groups)) {
            $this->loadSubGroups();
        }
        return $this->groups;
    }

    /**
     * Get parent group
     *
     * @return Group|null
     */
    public function getParentGroup(): ?Group
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
    public function getCreationDate(bool $formatted = true): string
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
    public function getMemberCount(bool $force = false): int
    {
        if (isset($this->members)) {
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
     * @return self
     */
    public function setName(string $name): self
    {
        $this->group_name = strip_tags($name);
        return $this;
    }

    /**
     * check if can Set parent group
     *
     * @param Group $group Parent group
     *
     * @return boolean
     */
    public function canSetParentGroup(Group $group): bool
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
     * @return self
     */
    public function setParentGroup(int $id): self
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
     * Add member to group
     *
     * @param Adherent $member Member to add
     *
     * @return void
     */
    public function addMember(Adherent $member): void
    {
        global $zdb;

        try {
            $insert = $zdb->insert(self::GROUPSUSERS_TABLE);
            $insert->values(
                [
                    self::PK => $this->getId(),
                    Adherent::PK => $member->id
                ]
            );
            $zdb->execute($insert);
            $this->members[] = $member;
        } catch (\OverflowException $e) {
            //nothing to do, member is already in group
            Analog::log(
                'Member `' . $member->sname . '` already in group `' .
                $this->group_name . '` (' . $this->id . ').',
                Analog::INFO
            );
        } catch (\Throwable $e) {
            Analog::log(
                'Cannot add member to group `' . $this->group_name .
                '` (' . $this->id . ') | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Set members
     *
     * @param Adherent[] $members Members list
     *
     * @return bool
     * @throws Throwable
     */
    public function setMembers(array $members = []): bool
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
                [
                    self::PK        => ':group',
                    Adherent::PK    => ':adh'
                ]
            );

            $stmt = $zdb->sql->prepareStatementForSqlObject($insert);

            foreach ($members as $m) {
                $result = $stmt->execute(
                    [
                        'group' => $this->id,
                        'adh'   => $m->id
                    ]
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

            //commit all changes
            $zdb->connection->commit();

            Analog::log(
                'Group members updated successfully.',
                Analog::INFO
            );

            return true;
        } catch (Throwable $e) {
            $te = new \RuntimeException('Unable to attach members to group', $e->getCode(), $e);
            $zdb->connection->rollBack();
            $messages = [];
            do {
                $messages[] = $e->getMessage();
            } while ($e = $e->getPrevious());
            Analog::log(
                'Unable to attach members to group `' . $this->group_name .
                '` (' . $this->id . ')|' . implode("\n", $messages),
                Analog::ERROR
            );
            throw $te;
        }
    }

    /**
     * Set managers
     *
     * @param Adherent[] $members Managers list
     *
     * @return bool
     * @throws Throwable
     */
    public function setManagers(array $members = []): bool
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
                [
                    self::PK        => ':group',
                    Adherent::PK    => ':adh'
                ]
            );

            $stmt = $zdb->sql->prepareStatementForSqlObject($insert);

            foreach ($members as $m) {
                $result = $stmt->execute(
                    [
                        'group' => $this->id,
                        'adh'   => $m->id
                    ]
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

            //commit all changes
            $zdb->connection->commit();

            Analog::log(
                'Groups managers updated successfully.',
                Analog::INFO
            );

            return true;
        } catch (Throwable $e) {
            $te = clone $e;
            $zdb->connection->rollBack();
            $messages = [];
            do {
                $messages[] = $e->getMessage();
            } while ($e = $e->getPrevious());
            Analog::log(
                'Unable to attach managers to group `' . $this->group_name .
                '` (' . $this->id . ')|' . implode("\n", $messages),
                Analog::ERROR
            );
            throw $te;
        }
    }

    /**
     * Set login instance
     *
     * @param Login $login Login instance
     *
     * @return self
     */
    public function setLogin(Login $login): self
    {
        $this->login = $login;
        return $this;
    }

    /**
     * Can current logged-in user edit group
     *
     * @param Login $login Login instance
     *
     * @return boolean
     */
    public function canEdit(Login $login): bool
    {
        global $preferences;

        //admin and staff users can edit
        if ($login->isAdmin() || $login->isStaff()) {
            return true;
        }

        //group managers can edit groups they manage when pref is on
        if ($preferences->pref_bool_groupsmanagers_edit_groups && $this->isManager($login)) {
            return true;
        }

        return false;
    }
}
