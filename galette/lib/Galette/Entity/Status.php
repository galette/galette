<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Statuses handling
 *
 * PHP version 5
 *
 * Copyright © 2007-2013 The Galette Team
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
 * @copyright 2007-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-27
 */

namespace Galette\Entity;

use Analog\Analog as Analog;

/* TODO: Most of the code is duplicated in Galette\Entity\ContributionsTypes. Should
 * probably use a superclass for genericity.
 */

/**
 * Members status
 *
 * @category  Entity
 * @name      Status
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-27
 */
class Status
{
    const DEFAULT_STATUS = 9;
    const TABLE = 'statuts';
    const PK = 'id_statut';
    const ORDER_FIELD = 'priorite_statut';

    const ID_NOT_EXITS = -1;

    private static $_fields = array(
        'id_statut',
        'libelle_statut',
        'priorite_statut'
    );

    private static $_defaults = array(
        array('id' => 1, 'libelle' => 'President', 'priority' => 0),
        array('id' => 2, 'libelle' => 'Treasurer', 'priority' => 10),
        array('id' => 3, 'libelle' => 'Secretary', 'priority' => 20),
        array('id' => 4, 'libelle' => 'Active member', 'priority' => 30),
        array('id' => 5, 'libelle' => 'Benefactor member', 'priority' => 40),
        array('id' => 6, 'libelle' => 'Founder member', 'priority' => 50),
        array('id' => 7, 'libelle' => 'Old-timer', 'priority' => 60),
        array('id' => 8, 'libelle' => 'Society', 'priority' => 70),
        array('id' => 9, 'libelle' => 'Non-member', 'priority' => 80),
        array('id' => 10, 'libelle' => 'Vice-president', 'priority' => 5)
    );

    /**
    * Default constructor
    *
    * @param ResultSet $args Optionnal existing result set
    */
    public function __construct($args = null)
    {
        if ( is_object($args) ) {
            $this->loadFromRS($args);
        }
    }

    /**
    * Set default status at install time
    *
    * @return boolean|Exception
    */
    public function installInit()
    {
        global $zdb;

        try {
            //first, we drop all values
            $zdb->db->delete(PREFIX_DB . self::TABLE);

            $stmt = $zdb->db->prepare(
                'INSERT INTO ' . PREFIX_DB . self::TABLE .
                ' (id_statut, libelle_statut, priorite_statut) ' .
                'VALUES(:id, :libelle, :priority)'
            );

            foreach ( self::$_defaults as $d ) {
                $stmt->bindParam(':id', $d['id']);
                $stmt->bindParam(':libelle', $d['libelle']);
                $stmt->bindParam(':priority', $d['priority']);
                $stmt->execute();
            }

            Analog::log(
                'Default status were successfully stored into database.',
                Analog::INFO
            );
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'Unable to initialize default status.' . $e->getMessage(),
                Analog::WARNING
            );
            return $e;
        }
    }

    /**
    * Get list of statuses
    *
    * @return array $array[id] = label status
    */
    public function getList()
    {
        global $zdb;
        $list = array();

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . self::TABLE)
                ->order(self::ORDER_FIELD, self::PK);
            $statuses = $select->query()->fetchAll();
            if ( count($statuses) == 0 ) {
                Analog::log('No status defined in database.', Analog::INFO);
            } else {
                foreach ( $statuses as $status ) {
                    $list[$status->id_statut] = _T($status->libelle_statut);
                }
            }
            return $list;
        } catch (\Exception $e) {
            /** TODO */
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
            Analog::log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                Analog::ERROR
            );
        }
    }

    /**
    * Complete list of statuses
    *
    * @return array of all statuses if succeed, false otherwise
    */
    public function getCompleteList()
    {
        global $zdb;
        $list = array();

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . self::TABLE)
                ->order(array(self::ORDER_FIELD, self::PK));

            $statuses = $select->query()->fetchAll();

            if ( count($statuses) == 0 ) {
                Analog::log('No status defined in database.', Analog::INFO);
            } else {
                foreach ( $statuses as $status ) {
                    $list[$status->id_statut] = array(
                        _T($status->libelle_statut),
                        $status->priorite_statut
                    );
                }
            }
            return $list;
        } catch (\Exception $e) {
            /** TODO */
            Analog::log(
                'Cannot list statuses | ' . $e->getMessage(),
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
    * Get a status.
    *
    * @param integer $id Status' id
    *
    * @return mixed|false Row if succeed ; false : no such id
    */
    public function get($id)
    {
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(array(PREFIX_DB . self::TABLE));
            $select->where(self::PK . '=' . $id);

            $result = $select->query()->fetch();

            return $result;
        } catch (\Exception $e) {
            /** TODO */
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
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
     * Get a label.
     *
     * @param integer $id         Status' id
     * @param boolean $translated Do we want translated or original status?
     *                            Defaults to true.
     *
     * @return string
     */
    public function getLabel($id, $translated = true)
    {
        $res = $this->get($id);
        return ($translated) ? _T($res->libelle_statut) : $res->libelle_statut;
    }

    /**
    * Get a status ID from a label.
    *
    * @param string $label The label
    *
    * @return int|false Return id if it exists false otherwise
    */
    public function getIdByLabel($label)
    {
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . self::TABLE, self::PK)
                ->where('libelle_statut = ?', $label);
            return $result = $select->query()->fetchColumn();
        } catch (\Exception $e) {
            /** FIXME */
            Analog::log(
                'Unable to retrieve status from label `' . $label . '` | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
    * Add a new status.
    *
    * @param string  $label    The label
    * @param integer $priority Priority
    *
    * @return intager id if success ; -1 : DB error ; -2 : label already exists
    */
    public function add($label, $priority)
    {
        global $zdb;

        // Avoid duplicates.
        $ret = $this->getidByLabel($label);

        if ( $ret !== false ) {
            Analog::log(
                'Status `' . $label . '` already exists',
                Analog::WARNING
            );
            return -2;
        }

        try {
            $values = array(
                'libelle_statut'  => $label,
                'priorite_statut' => $priority
            );

            $ret = $zdb->db->insert(
                PREFIX_DB . self::TABLE,
                $values
            );

            if ( $ret >  0) {
                Analog::log(
                    'New status `' . $label . '` added successfully.',
                    Analog::INFO
                );
                return $zdb->db->lastInsertId(
                    PREFIX_DB . self::TABLE,
                    'id'
                );
            } else {
                throw new \Exception('New status not added.');
            }
        } catch (\Exception $e) {
            /** FIXME */
            Analog::log(
                'Unable to add new status `' . $label . '` | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
    * Update a status.
    *
    * @param integer $id    Contribution's id
    * @param string  $field Field to update
    * @param mixed   $value The value to set
    *
    * @return integer -2 : ID does not exist ; -1 : DB error ; 0 : success.
    */
    public function update($id, $field, $value)
    {
        global $zdb;

        $ret = $this->get($id);
        if ( !$ret ) {
            return self::ID_NOT_EXITS;
        }

        $fieldtype = '';
        if ( $field == self::$_fields[1] ) {
            // label.
            $fieldtype = 'text';
        } elseif ( self::$_fields[2] ) {
            // priority.
            $fieldtype = 'integer';
        }

        Analog::log("Setting field $field to $value for ctype $id", Analog::INFO);

        try {
            $values= array(
                $field => $value
            );

            $zdb->db->update(
                PREFIX_DB . self::TABLE,
                $values,
                self::PK . ' = ' . $id
            );

            Analog::log('Status ' . $id . ' updated successfully.', Analog::INFO);
            return true;
        } catch (\Exception $e) {
            /** FIXME */
            Analog::log(
                'Unable to update status ' . $id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
    * Delete a status.
    *
    * @param integer $id Contribution's id
    *
    * @return integer -2 : ID does not exist ; -1 : DB error ; 0 : success.
    */
    public function delete($id)
    {
        global $zdb;

        if ( (int)$id === self::DEFAULT_STATUS ) {
            throw new \RuntimeException(_T("You cannot delete default status!"));
        }

        $ret = $this->get($id);
        if ( !$ret ) {
            return self::ID_NOT_EXITS;
        }

        try {
            $zdb->db->delete(
                PREFIX_DB . self::TABLE,
                self::PK . ' = ' . $id
            );
            Analog::log(
                'Status #' . $id . ' (' . $ret->libelle_statut
                . ') deleted successfully.',
                Analog::INFO
            );
            return true;
        } catch (\RuntimeException $re) {
            throw $re;
        } catch (\Exception $e) {
            Analog::log(
                'Unable to delete status ' . $id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
    * Check whether this status is used.
    *
    * @param integer $id Status' id
    *
    * @return boolean
    */
    public function isUsed($id)
    {
        global $zdb;

        // Check if it's used.
        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . Adherent::TABLE)
                ->where(self::PK . ' = ?', $id);
            if ( $select->query()->fetch() !== false ) {
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            /** FIXME */
            Analog::log(
                'Unable to check if status `' . $id . '` is used. | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            //in case of error, we consider that status is used, to avoid errors
            return true;
        }
    }
}
