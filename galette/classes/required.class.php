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
    private $_all_required = array();
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
        'ville_adh',
        'email_adh'
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
        global $zdb, $log;        

        try {
            $select = new Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . self::TABLE);

            $required = $select->query()->fetchAll();

            if ( count($required) == 0 && $try ) {
                $this->init();
            } else {
                $this->_fields = null;
                foreach ( $required as $k ) {
                    $this->_fields[] = $k->field_id;
                    if ($k->required == 1) {
                        $this->_all_required[$k->field_id] = $k->required;
                    }
                }

                $meta = Adherent::getDbFields();
                if ( count($required) != count($meta) ) {
                    $log->log(
                        'Members columns count does not match required records.' .
                        ' Is: ' . count($required) . ' and should be ' .
                        count($meta) . '. Reinit.',
                        PEAR_LOG_WARNING
                    );
                    $this->init(true);
                }
            }
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                'Cannot check required fields update | ' . $e->getMessage(),
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
        global $zdb, $log;
        $log->log('Initializing required fiels', PEAR_LOG_DEBUG);
        if ( $reinit ) {
            $log->log('Reinit mode, we delete table\'s content', PEAR_LOG_DEBUG);
            try {
                $zdb->db->query('TRUNCATE ' . PREFIX_DB . self::TABLE);
            } catch (Exception $e) {
                $log->log(
                    'An error has occured deleting current required records | ' .
                    $e->getMessage(),
                    PEAR_LOG_ERR
                );
                $log->log(
                    $e->getTraceAsString(),
                    PEAR_LOG_WARNING
                );
                return false;
            }
        }

        try {
            $fields = Adherent::getDbFields();
            $stmt = $zdb->db->prepare(
                'INSERT INTO ' . PREFIX_DB . self::TABLE .
                ' (' . $zdb->db->quoteIdentifier('field_id') . ', ' .
                $zdb->db->quoteIdentifier('required') . ')' .
                ' VALUES(:id, :required)'
            );

            $params = array();
            foreach ( $fields as $k ) {
                $stmt->bindValue(':id', $k, PDO::PARAM_STR);
                $req = (($reinit)?
                            array_key_exists($k, $this->_all_required)
                            : in_array($k, $this->_defaults)?true:false);
                $stmt->bindValue(':required', $req, PDO::PARAM_BOOL);
                if ( $stmt->execute() ) {
                    $log->log(
                        'Field ' . $k . ' processed.',
                        PEAR_LOG_DEBUG
                    );
                } else {
                    $log->log(
                        'An error occured trying to initialize required fields',
                        PEAR_LOG_ERR
                    );
                    return false;
                }
            }
            $log->log(
                'Required adherents table updated successfully.',
                PEAR_LOG_INFO
            );
            $log->log(
                'Initialisation seems successfull, we reload the object',
                PEAR_LOG_DEBUG
            );
            $this->_checkUpdate(false);
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                'An error occured trying to initialize required fields | ' .
                $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                $e->__toString(),
                PEAR_LOG_ERR
            );
            return false;
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
     * @param array $value Field names that are required. All others will be
     *                     marked as not required.
     *
     * @return boolean
     */
    public function setRequired($value)
    {
        global $zdb, $log;

        try {
            //set required fields
            $zdb->db->update(
                PREFIX_DB . self::TABLE,
                array('required' => true),
                $zdb->db->quoteInto('field_id IN (?)', $value)
            );
            //set not required fields
            $zdb->db->update(
                PREFIX_DB . self::TABLE,
                array('required' => false),
                $zdb->db->quoteInto('field_id NOT IN (?)', $value)
            );
            $this->_checkUpdate();
            return true;
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                'An error has occured updating required fields | ' .
                $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                $e->getTraceAsString(),
                PEAR_LOG_ERR
            );
            return false;
        }
    }
}
?>