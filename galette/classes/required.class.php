<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Required fields
 *
 * PHP version 5
 *
 * Copyright © 2007-2011 The Galette Team
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
 * @copyright 2007-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-07-06
 */

/** @ignore */
require_once 'adherent.class.php';

/**
 * Required class for galette :
 * defines which fields are mandatory and which are not.
 *
 * @category  Classes
 * @name      Required
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-07-06
 */
class Required
{
    private $_all_required;
    private $_fields = array();
    const TABLE = 'required';

    private $_types = array(
        'text',
        'boolean'
    );

    private $_defaults = array(
        'titre_adh',
        'nom_adh',
        'login_adh',
        'mdp_adh',
        'adresse_adh',
        'cp_adh',
        'ville_adh'
    );

    /**
    * Default constructor
    */
    function __construct()
    {
        $this->_checkUpdate();
    }

    /**
    * Checks if the required table should be updated
    * since it has not yet appened or adherents table
    * has been modified.
    *
    * @param boolean $try TO DOCUMENT
    *
    * @return void
    */
    private function _checkUpdate($try = true)
    {
        global $mdb, $log;
        if ( $mdb->getOption('result_buffering') ) {
            $requete = 'SELECT * FROM ' . PREFIX_DB . Adherent::TABLE;
            $mdb->getDb()->setLimit(1);

            $result2 = $mdb->query($requete);
            if ( MDB2::isError($result2) ) {
                $log->log(
                    'An error has occured retrieving members rows for required ' .
                    'fields | ' . $result2->getMessage() . '(' .
                    $result2->getDebugInfo() . ')',
                    PEAR_LOG_ERR
                );
                /** FIXME: should return false */
                return -1;
            }

            $requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE;

            $result = $mdb->query($requete);
            if ( MDB2::isError($result) ) {
                $log->log(
                    'An error has occured retrieving current required records | ' .
                    $result->getMessage() . '(' . $result->getDebugInfo() . ')',
                    PEAR_LOG_ERR
                );
                /** FIXME: should return false */
                return -1;
            }

            $result->setResultTypes($this->_types);

            if ( $result->numRows() == 0 && $try ) {
                $this->init();
            } else {
                $required = $result->fetchAll();
                $this->_fields = null;
                foreach ( $required as $k ) {
                    $this->_fields[] = $k->field_id;
                    if ($k->required == 1) {
                        $this->_all_required[$k->field_id] = $k->required;
                    }
                }
                if ( $result2->numCols() != $result->numRows() ) {
                    $log->log(
                        'Members columns count does not match required records.' .
                        ' Is: ' . $result->numRows() . ' and should be ' .
                        $result2->numCols() . '. Reinit.',
                        PEAR_LOG_DEBUG
                    );
                    $this->init(true);
                }
            }
        } else {
            $log->log(
                'An error occured whule checking for required fields update.',
                PEAR_LOG_ERROR
            );
        }
    }

    /**
    * Init data into required table.
    *
    * @param boolean $reinit true if we must first delete all data on required table.
    * This should occurs when adherents table has been updated. For the first
    * initialisation, value should be off.
    *
    * @return false if error
    */
    function init($reinit=false)
    {
        global $mdb, $log;
        $log->log('Initializing required fiels', PEAR_LOG_DEBUG);
        if ( $reinit ) {
            $log->log('Reinit mode, we delete table\'s content', PEAR_LOG_DEBUG);
            $requetesup = 'DELETE FROM ' . PREFIX_DB . self::TABLE;

            $init_result = $mdb->execute($requetesup);
            if ( MDB2::isError($init_result) ) {
                $log->log(
                    'An error has occured deleting current required records | ' .
                    $init_result->getMessage() . '(' .
                    $init_result->getDebugInfo() . ')',
                    PEAR_LOG_ERR
                );
                /** FIXME: should return false */
                return -1;
            }
        }

        $requete = 'SELECT * FROM ' . PREFIX_DB . Adherent::TABLE;
        $mdb->getDb()->setLimit(1);

        $result = $mdb->query($requete);
        if ( MDB2::isError($result) ) {
            $log->log(
                'An error occured retrieving members rows for required fields | ' .
                $result->getMessage() . '(' . $result->getDebugInfo() . ')',
                PEAR_LOG_ERR
            );
            /** FIXME: should return false */
            return -1;
        }

        $fields = $result->getColumnNames();

        $stmt = $mdb->prepare(
            'INSERT INTO ' . PREFIX_DB . self::TABLE .
            ' (field_id, required) VALUES(:id, :required)',
            $this->_types,
            MDB2_PREPARE_MANIP
        );

        $params = array();
        foreach ( $fields as $k=>$v ) {
            $params[] = array(
                'id'        =>    $k,
                'required'    =>    (($reinit)?
                                        array_key_exists($k, $this->_all_required)
                                        : in_array($k, $this->_defaults)?true:false)
            );
        }

        $mdb->getDb()->loadModule('Extended', null, false);
        $mdb->getDb()->extended->executeMultiple($stmt, $params);
        /** /FIXME */

        if ( MDB2::isError($stmt) ) {
            $log->log(
                'An error occured trying to initialize required fields | ' .
                $stmt->getMessage() . '(' . $stmt->getDebugInfo() . ')',
                PEAR_LOG_ERR
            );
            /** FIXME: do we want to return something? */
        } else {
            $log->log(
                'Initialisation seems successfull, we reload the object',
                PEAR_LOG_DEBUG
            );
            $log->log(
                'Required adherents table updated successfully.',
                PEAR_LOG_INFO
            );
            $stmt->free();
            $this->_checkUpdate(false);
        }
    }


    /**
    * Get required fields
    *
    * @return array all required fields. Field names = keys
    */
    public function getRequired()
    {
        return $this->_all_required;
    }

    /**
    * Get fields
    *
    * @return array all fields
    */
    public function getFields()
    {
        return $this->_fields;
    }

    /**
    * Set required fields
    *
    * @param string $value Field name to set to required state
    *
    * @return boolean
    */
    public function setRequired($value)
    {
        global $mdb, $log;

        /** FIXME: use a statement and executeMultiple to avoid executing
        * two queries here */

        //set required fields
        $requete = 'UPDATE ' . PREFIX_DB . self::TABLE . ' SET required=' .
            $mdb->quote(true) . ' WHERE field_id=\'';
        $requete .= implode('\' OR field_id=\'', $value);
        $requete .= '\'';

        $result = $mdb->query($requete);
        if ( MDB2::isError($result) ) {
            $log->log(
                'An error has occured updating required=true fields | ' .
                $result->getMessage() . '(' . $result->getDebugInfo() . ')',
                PEAR_LOG_ERR
            );
            /** FIXME: should return false */
            return -1;
        }

        //set not required fields (ie. all others...)
        $not_required = array_diff($this->_fields, $value);
        $requete2 = 'UPDATE ' . PREFIX_DB . self::TABLE . ' SET required=' .
            $mdb->quote(false) . ' WHERE field_id=\'';
        $requete2 .= implode('\' OR field_id=\'', $not_required);
        $requete2 .= '\'';

        $result = $mdb->query($requete2);
        if ( MDB2::isError($result) ) {
            $log->log(
                'An error has occured updating required=false fields | ' .
                $result->getMessage() . '(' . $result->getDebugInfo() . ')',
                PEAR_LOG_ERR
            );
            /** FIXME: should return false */
            return -1;
        }

        $this->_checkUpdate();
    }
}
?>