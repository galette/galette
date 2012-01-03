<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Groups managment
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

/** @ignore */
require_once 'adherent.class.php';

/**
 * This class handles groups, their owners and members.
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
    const TABLE = 'groups';
    const USERSGROUPS_TABLE = 'groups_users';
    const PK = 'id_group';

    private $_id;
    private $_group_name;
    private $_owner;
    private $_members;
    private $_creation_date;
    private $_count_members;
    private $_managers;

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
        $this->_id = $r->id_group;
        $this->_group_name = $r->group_name;
        $this->_creation_date = $r->creation_date;
        $adhpk = Adherent::PK;
        $this->_owner = new Adherent((int)$r->$adhpk);
        if ( isset($r->members) ) {
            //we're from a list, we just want members count
            $this->_count_members = $r->members;
        } else {
            //we're probably from a single group, let's load members list
            $this->_loadMembers();
        }
    }

    /**
     * Loads members for the current group
     */
    private function _loadMembers()
    {
        global $zdb, $log;

        try {
            $select = new Zend_Db_Select($zdb->db);
            $select->from(
                PREFIX_DB . self::USERSGROUPS_TABLE,
                array(Adherent::PK, 'manager')
            )->where(self::PK . ' = ?', $this->_id);
            $res = $select->query()->fetchAll();
            $members = array();
            $adhpk = Adherent::PK;
            foreach ( $res as $m ) {
                $members[] = new Adherent((int)$m->$adhpk);
                //put managers in an array
                if ( $m->manager == 1) {
                    $this->_managers[] = (int)$m->$adhpk;
                }
            }
            $this->_members = $members;
        } catch (Exception $e) {
            $log->log(
                'Cannot get group members | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                PEAR_LOG_ERR
            );
        }
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
            )->joinLeft(
                array('b' => PREFIX_DB . self::USERSGROUPS_TABLE),
                'a.' . self::PK . '=b.' .self::PK,
                array('members' => new Zend_Db_Expr('count(b.' . self::PK . ')'))
            )->group('a.' . self::PK);
            $groups = array();
            $q = $select->__toString();
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
                'Query was: ' . $select->__toString() . ' ' . $select->__toString(),
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
    public function removeGroups($ids)
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
            )->where('b.' . Adherent::PK . ' = ?', $id);
            $result = $select->query()->fetchAll();
            $log->log(
                'Exectued query: ' . $select->__toString(),
                PEAR_LOG_DEBUG
            );
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
     * Store the group
     *
     * @return boolean
     */
    public function store()
    {
        global $zdb, $log, $hist;

        try {
            $values = array(
                self::PK     => $this->_id,
                'group_name' => $this->_group_name,
                Adherent::PK => $this->_owner->id
            );

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
                    throw new Exception(
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
            //DEBUG
            return false;
        } catch (Exception $e) {
            /** FIXME */
            $log->log(
                'Something went wrong :\'( | ' . $e->getMessage() . "\n" .
                $e->getTraceAsString(),
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

    public function getMemberCount()
    {
        if (isset($this->_members) && is_array($this->_members) ) {
            return count($this->_members);
        } else if ( isset($this->_count_members) ) {
            return $this->_count_members;
        } else {
            return 0;
        }
    }

    /**
     * Set name
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->_group_name = $name;
    }

    /**
     * Set owner
     *
     * @param int $id Owner id
     */
    public function setOwner($id)
    {
        $this->_owner = new Adherent((int)$id);
    }

    /**
     * Add a member to specified groups
     *
     * @param Adherent $adh    Member
     * @param array    $groups Groups Groups list. Each entry must contain
     *                                the group id, name and 0 or 1 for manager
     *                                each value separated by a pipe.
     *
     * @return boolean
     */
    public static function addMemberToGroups($adh, $groups)
    {
        global $zdb, $log;
        try {
            $zdb->db->beginTransaction();

            //first, remove current groups members (as we only have current members at this point)
            $del = $zdb->db->delete(
                PREFIX_DB . self::USERSGROUPS_TABLE,
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
                    'INSERT INTO ' . PREFIX_DB . self::USERSGROUPS_TABLE .
                    ' (' . $zdb->db->quoteIdentifier(self::PK) . ', ' .
                    $zdb->db->quoteIdentifier(Adherent::PK) . ', ' .
                    $zdb->db->quoteIdentifier('manager') . ')' .
                    ' VALUES(:id, ' . $adh->id . ', :manager)'
                );

                foreach ( $groups as $group ) {
                    list($gid, $gname, $manager) = explode('|', $group);
                    $stmt->bindValue(':id', $gid, PDO::PARAM_INT);
                    $stmt->bindValue(':manager', $manager, PDO::PARAM_BOOL);

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
        } catch (Exception $e) {
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
     * Set members
     *
     * @param Adherent[] $members
     */
    public function setMembers($members)
    {
        global $zdb, $log;

        try {
            $zdb->db->beginTransaction();

            //first, remove current groups members (as we only have current members at this point)
            $del = $zdb->db->delete(
                PREFIX_DB . self::USERSGROUPS_TABLE,
                self::PK . ' = ' . $this->_id
            );
            $log->log(
                'Group members has been removed for `' . $this->_group_name .
                ', we can now store new ones.',
                PEAR_LOG_INFO
            );

            $stmt = $zdb->db->prepare(
                'INSERT INTO ' . PREFIX_DB . self::USERSGROUPS_TABLE .
                ' (' . $zdb->db->quoteIdentifier(self::PK) . ', ' .
                $zdb->db->quoteIdentifier(Adherent::PK) . ', ' .
                $zdb->db->quoteIdentifier('manager') . ')' .
                ' VALUES(' . $this->_id . ', :adh, :manager)'
            );

            foreach ( $members as $m ) {
                $stmt->bindValue(':adh', $m->id, PDO::PARAM_INT);
                //at the moment, the interface does not permit to manage managers
                //so we keep an eye on existing ones, and set them without changes
                $stmt->bindValue(
                    ':manager',
                    (in_array($m->id, $this->_managers) ? true : false),
                    PDO::PARAM_BOOL
                );

                if ( $stmt->execute() ) {
                    $log->log(
                        'Member `' . $m->sname . '` attached to group `' .
                        $this->_group_name . '`.',
                        PEAR_LOG_DEBUG
                    );
                } else {
                    $log->log(
                        'An error occured trying to attach member `' .
                        $m->sname . '` to group `' . $this->_group_name . '`.',
                        PEAR_LOG_ERR
                    );
                    throw new Exception(
                        'Unable to attach `' . $m->sname . '` ' .
                        'to ' . $this->_group_name
                    );
                }
            }
            //commit all changes
            $zdb->db->commit();

            $log->log(
                'Required adherents table updated successfully.',
                PEAR_LOG_INFO
            );

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
    }
}

?>
