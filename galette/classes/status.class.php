<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Statuses handling
 *
 * PHP version 5
 *
 * Copyright © 2007-2009 The Galette Team
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
 * @copyright 2007-2009 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-27
 */

/* TODO: Most of the code is duplicated in contribution_types.class.php. Should
 * probably use a superclass for genericity.
 */

/**
 * Members status
 *
 * @category  Classes
 * @name      Status
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2009 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-27
 */
class Status
{
    const DEFAULT_STATUS = 4;
    const TABLE = 'statuts';
    const PK = 'id_statut';
    const ORDER_FIELD = 'priorite_statut';

    private $_error;

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
    * @return boolean
    */
    public function installInit()
    {
        global $mdb, $log;

        //first, we drop all values
        $query = 'DELETE FROM '  . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE);
        $result = $mdb->execute($query);

        if ( MDB2::isError($result) ) {
            /** FIXME: we surely want to return sthing and print_r for debug. */
            print_r($result);
        }

        $stmt = $mdb->prepare(
            'INSERT INTO ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE) .
            ' (' . $mdb->quoteIdentifier('id_statut') . ', ' .
            $mdb->quoteIdentifier('libelle_statut') . ', ' .
            $mdb->quoteIdentifier('priorite_statut') .
            ') VALUES(:id, :libelle, :priority)',
            array('integer', 'text', 'integer'),
            MDB2_PREPARE_MANIP
        );

        $mdb->getDb()->loadModule('Extended', null, false);
        $mdb->getDb()->extended->executeMultiple($stmt, self::$_defaults);

        if ( MDB2::isError($stmt) ) {
            $this->error = $stmt;
            $log->log(
                'Unable to initialize default status.' .
                $stmt->getMessage() . '(' . $stmt->getDebugInfo() . ')',
                PEAR_LOG_WARNING
            );
            return false;
        }

        $stmt->free();
        $log->log(
            'Default status were successfully stored into database.',
            PEAR_LOG_INFO
        );
        return true;
    }

    /**
    * Get list of statuses
    *
    * @return MDB2::Error or $array[id] = label status
    */
    public function getList()
    {
        global $mdb, $log;
        $list = array();

        $requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE . ' ORDER BY ' .
            self::ORDER_FIELD . ', ' . self::PK;

        $result = $mdb->query($requete);
        if ( MDB2::isError($result) ) {
            $this->_error = $result;
            return false;
        }

        if ( $result->numRows() == 0 ) {
            $log->log('No status defined in database.', PEAR_LOG_INFO);
            return(-10);
        } else {
            $r = $result->fetchAll();
            $array = array();
            foreach ( $r as $status ) {
                $list[$status->id_statut] = _T($status->libelle_statut);
            }
            return $list;
        }

    }

    /**
    * TODO: replace with a static function ?
    */
    /**
    * Complete list of statuses
    *
    * @return array of all statuses if succeed, false otherwise
    */
    public function getCompleteList()
    {
        global $mdb, $log;
        $list = array();

        $requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE . ' ORDER BY ' .
            self::ORDER_FIELD . ', ' . self::PK;

        $result = $mdb->query($requete);
        if ( MDB2::isError($result) ) {
            $this->error = $result;
            return false;
        }

        if ( $result->numRows() == 0 ) {
            $log->log('No status defined in database.', PEAR_LOG_INFO);
            return(-10);
        } else {
            /** TODO: an array of Objects would be more relevant here
            (see members and adherent class) */
            /*foreach ( $result->fetchAll() as $row ) {
                $list[] = new Status($row);
            }*/
            /** END TODO */
            $r = $result->fetchAll();
            foreach ( $r as $status ) {
                $list[$status->id_statut] = array(
                    _T($status->libelle_statut),
                    $status->priorite_statut
                );
            }
            return $list;
        }
    }

    /**
    * Get a status.
    *
    * @param integer $id Status' id
    *
    * @return ResultSet Row if succeed ; null : no such id
    *   MDB2::Error object : DB error.
    */
    public function get($id)
    {
        global $mdb, $log;

        $requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE . ' WHERE ' .
            self::PK .'=' . $id;

        $result = $mdb->query($requete);
        if ( MDB2::isError($result) ) {
            $this->_error = $result;
            return $result;
        }

        if ($result->numRows() == 0) {
            $this->_error = $result;
            $log->log(
                'Status `' . $id . '` does not exist.',
                PEAR_LOG_WARNING
            );
            return null;
        }

        return $result->fetchRow();
    }

    /**
    * Get a label.
    *
    * @param integer $id Status' id
    *
    * @return integer translated label if succeed, -2 : ID does not exist ;
    *   -1 : DB error.
    */
    public static function getLabel($id)
    {
        $res = self::get($id);
        if ( !$res || MDB2::isError($res) ) {
            return $res;
        }

        return _T($res->libelle_statut);
    }

    /**
    * Get a status ID from a label.
    *
    * @param string $label The label
    *
    * @return null : ID does not exist ; MDB2::Error : DB error ;
    *   ResultSetRow on success
    */
    public function getIdByLabel($label)
    {
        global $mdb, $log;

        $stmt = $mdb->prepare(
            'SELECT '. self::PK .' FROM ' . PREFIX_DB . self::TABLE .
            ' WHERE ' . $mdb->quoteIdentifier('libelle_statut') . '= :libelle',
            array('text'),
            MDB2_PREPARE_MANIP
        );
        $result = $stmt->execute(array('libelle' => $label));

        if ( MDB2::isError($result) ) {
            $this->_error = $result;
            return $result;
        }

        if ( $result == 0 || $result->numRows() == 0 ) {
            return null;
        }

        return $result->fetchOne();
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
        global $mdb, $log;

        // Avoid duplicates.
        $ret = $this->getidByLabel($label);
        if ( MDB2::isError($ret) ) {
            return -1;
        }
        if ( $ret != null ) {
            $log->log(
                'Status `' . $label . '` already exists',
                PEAR_LOG_WARNING
            );
            return -2;
        }

        $stmt = $mdb->prepare(
            'INSERT INTO ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE) .
            ' (' . $mdb->quoteIdentifier('libelle_statut') .
            ', ' . $mdb->quoteIdentifier('priorite_statut') .
            ') VALUES(:libelle, :priorite)',
            array('text', 'integer'),
            MDB2_PREPARE_MANIP
        );
        $stmt->execute(
            array(
                'libelle'  => $label,
                'priorite' => $priority
            )
        );

        if ( MDB2::isError($stmt) ) {
            $this->error = $stmt;
            $log->log(
                'Unable to add new status `' . $label . '` | ' .
                $stmt->getMessage() . '(' . $stmt->getDebugInfo() . ')',
                PEAR_LOG_WARNING
            );
            return -1;
        }

        $stmt->free();
        $log->log(
            'New status `' . $label . '` added successfully.',
            PEAR_LOG_INFO
        );
        return $mdb->getDb()->lastInsertId(
            PREFIX_DB . self::TABLE,
            'libelle_statut'
        );
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
        global $mdb, $log;

        $ret = $this->get($id);
        if ( !$ret || MDB2::isError($ret) ) {
            /* get() already logged and set $this->error. */
            return ($ret ? -1 : -2);
        }

        $fieldtype = '';
        if ( $field == self::$_fields[1] ) {
            // label.
            $fieldtype = 'text';
        } elseif ( self::$_fields[2] ) {
            // priority.
            $fieldtype = 'integer';
        }

        $log->log("Setting field $field to $value for ctype $id", PEAR_LOG_INFO);

        $stmt = $mdb->prepare(
            'UPDATE ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE) . ' SET ' .
            $mdb->quoteIdentifier($field) . ' = :field ' .
            'WHERE ' . self::PK . ' = '.$id,
            array($fieldtype),
            MDB2_PREPARE_MANIP
        );
        $stmt->execute(array('field'  => $value));

        if (MDB2::isError($stmt)) {
            $this->error = $stmt;
            $log->log(
                'Unable to update status ' . $id . ' | ' . $stmt->getMessage() .
                '(' . $stmt->getDebugInfo() . ')',
                PEAR_LOG_WARNING
            );
            return -1;
        }

        $stmt->free();
        $log->log('Status ' . $id . ' updated successfully.', PEAR_LOG_INFO);
        return 0;
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
        global $mdb, $log;

        $ret = $this->get($id);
        if ( !$ret || MDB2::isError($ret) ) {
            /* get() already logged and set $this->_error. */
            return ($ret ? -1 : -2);
        }

        $query = 'DELETE FROM ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE) .
            ' WHERE ' . self::PK . ' = ' . $id;
        $result = $mdb->execute($query);

        if ( MDB2::isError($result) ) {
            $this->error = $result;
            $log->log(
                'Unable to delete status ' . $id . ' | ' . $result->getMessage() .
                '(' . $result->getDebugInfo() . ')',
                PEAR_LOG_WARNING
            );
            return -1;
        }

        $log->log('Status ' . $id . ' deleted successfully.', PEAR_LOG_INFO);
        return 0;
    }

    /**
    * Check whether this status is used.
    *
    * @param integer $id Contribution's id
    *
    * @return integer -1 : DB error ; 0 : not used ; 1 : used.
    */
    public function isUsed($id)
    {
        global $mdb, $log;

        // Check if it's used.
        $query = 'SELECT * FROM ' . $mdb->quoteIdentifier(PREFIX_DB . 'adherents') .
            ' WHERE ' . $mdb->quoteIdentifier('id_statut') . ' = ' . $id;
        $result = $mdb->query($query);
        if ( MDB2::isError($result) ) {
            $this->_error = $result;
            return -1;
        }

        return ($result->numRows() == 0) ? 0 : 1;
    }

    /**
    * Has an error occured ?
    *
    * @return boolean
    */
    public function inError()
    {
        if ( MDB2::isError($this->_error) ) {
            return true;
        } else {
            return false;
        }
    }

    /**
    * Get main MDB2 error message
    *
    * @return string MDB2::Error's message
    */
    public function getErrorMessage()
    {
        return $this->_error->getMessage();
    }

    /**
    * Get additionnal informations about the error
    *
    * @return string MDB2::Error's debuginfos
    */
    public function getErrorDetails()
    {
        return $this->_error->getDebugInfo();
    }
}
?>