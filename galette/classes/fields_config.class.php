<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Fields config handling
 *
 * PHP version 5
 *
 * Copyright © 2009-2011 The Galette Team
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
 * @copyright 2009-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-26
 */


/** @ignore */
require_once 'adherent.class.php';
require_once 'fields_categories.class.php';

/**
 * Fields config class for galette :
 * defines fields mandatory, order and visibility
 *
 * @category  Classes
 * @name      FieldsConfig
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-26
 */
class FieldsConfig
{
    const HIDDEN = 0;
    const VISIBLE = 1;
    const ADMIN = 2;

    private $_all_required;
    private $_all_visibles;
    private $_all_labels;
    //private $error = array();
    private $_categorized_fields = array();
    private $_table;
    private $_defaults = null;
    private $_all_categories;

    const TABLE = 'fields_config';

    private $_types = array(
        'text',
        'text',
        'boolean',
        'integer',
        'integer',
        'integer'
    );

    /**
    * Default constructor
    *
    * @param string $table    the table for which to get fields configuration
    * @param array  $defaults default values
    */
    function __construct($table, $defaults)
    {
        $this->_table = $table;
        $this->_defaults = $defaults;
        $this->_checkUpdate();
    }

    /**
    * Checks if the required table should be updated
    * since it has not yet happened or the table
    * has been modified.
    *
    * @param boolean $try Just check, when called from $this->init()
    *
    * @return void
    */
    private function _checkUpdate($try = true)
    {
        global $zdb, $log;
        $class = get_class($this);

        try {
            $select = new Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . self::TABLE)
                ->where('table_name = ?', $this->_table)
                ->order(array(FieldsCategories::PK, 'position ASC'));

            $result = $select->query()->fetchAll();
            if ( count($result) == 0 && $try ) {
                $this->init();
            } else {
                $this->_categorized_fields = null;
                foreach ( $result as $k ) {
                    $f = array(
                        'field_id'  =>  $k->field_id,
                        'label'     =>  $this->_defaults[$k->field_id]['label'],
                        'category'  =>  $this->_defaults[$k->field_id]['category'],
                        'visible'   =>  $k->visible,
                        'required'  =>  $k->required
                    );
                    $this->_categorized_fields[$k->id_field_category][] = $f;

                    //array of all required fields
                    if ( $k->required == 1 ) {
                        $this->_all_required[$k->field_id] = $k->required;
                    }

                    //array of all fields visibility
                    $this->_all_visibles[$k->field_id] = $k->visible;

                    //maybe we can delete these ones in the future
                    $this->_all_labels[$k->field_id]
                        = $this->_defaults[$k->field_id]['label'];
                    $this->_all_categories[$k->field_id]
                        = $this->_defaults[$k->field_id]['category'];
                    $this->all_positions[$k->field_id] = $k->position;
                }

                $meta = Adherent::getDbFields();

                if ( count($meta) != count($result) ) {
                    $log->log(
                        '[' . $class . '] Count for `' . $this->_table .
                        '` columns does not match records. Is : ' .
                        count($result) . ' and should be ' .
                        count($meta) . '. Reinit.',
                        PEAR_LOG_INFO
                    );
                    $this->init(true);
                }
            }
        } catch (Exception $e) {
            $log->log(
                '[' . $class . '] An error occured while checking update for ' .
                'fields configuration for table `' . $this->_table . '`. ' .
                $e->getMessage(),
                PEAR_LOG_ERR
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                PEAR_LOG_ERR
            );
            throw $e;
        }
    }

    /**
    * Init data into config table.
    *
    * @param boolean $reinit true if we must first delete all config data for
    * current table.
    * This should occurs when table has been updated. For the first
    * initialisation, value should be false. Defaults to false.
    *
    * @return void
    */
    public function init($reinit=false)
    {
        global $zdb, $log;
        $class = get_class($this);
        $t = new FieldsCategories();
        $init = $t->installInit();

        $log->log(
            '[' . $class . '] Initializing fields configuration for table `' .
            PREFIX_DB . $this->_table . '`',
            PEAR_LOG_DEBUG
        );
        if ( $reinit ) {
            $log->log(
                '[' . $class . '] Reinit mode, we delete config content for ' .
                'table `' . PREFIX_DB . $this->_table . '`',
                PEAR_LOG_DEBUG
            );
            //Delete all entries for current table. Existing entries are
            //already stored, new ones will be added :)
            try {
                $zdb->db->delete(
                    PREFIX_DB . self::TABLE,
                    $zdb->db->quoteInto('table_name = ?', PREFIX_DB . $this->_table)
                );
            } catch (Exception $e) {
                $log->log(
                    'Unable to delete fields configuration for reinitialization' .
                    $e->getMessage(),
                    PEAR_LOG_WARNING
                );
                return false;
            }
        }

        try {
            $fields = array_keys($this->_defaults);

            $stmt = $zdb->db->prepare(
                'INSERT INTO ' . PREFIX_DB . self::TABLE .
                ' (table_name, field_id, required, visible, position, ' .
                FieldsCategories::PK .
                ') VALUES(:table_name, :field_id, :required, :visible, :position, ' .
                ':category)'
            );

            $params = array();
            foreach ( $fields as $key ) {
                $params = array(
                    ':field_id'    => $key,
                    ':table_name'  => $this->_table,
                    ':required'    => (
                                        ($reinit) ?
                                            array_key_exists(
                                                $key,
                                                $this->_all_required
                                            ) :
                                            $this->_defaults[$key]['required'] ?
                                                true :
                                                'false'
                                      ),
                    ':visible'     => (
                                        ($reinit) ?
                                            array_key_exists(
                                                $key,
                                                $this->all_visible
                                            ) :
                                            $this->_defaults[$key]['visible'] ?
                                                true :
                                                'false'
                                      ),
                    ':position'    => (
                                        ($reinit) ?
                                            $this->all_positions[$key] :
                                            $this->_defaults[$key]['position']
                                      ),
                    ':category'    => (
                                        ($reinit) ?
                                            $this->_all_categories[$key] :
                                            $this->_defaults[$key]['category']
                                      ),
                );
                $stmt->execute($params);
            }
            $log->log(
                '[' . $class . '] Initialisation seems successfull, we reload ' .
                'the object',
                PEAR_LOG_DEBUG
            );
            $log->log(
                str_replace(
                    '%s',
                    PREFIX_DB . $this->_table,
                    '[' . $class . '] Fields configuration for table %s '.
                    'initialized successfully.'
                ),
                PEAR_LOG_INFO
            );
            $this->_checkUpdate(false);
        } catch (Exception $e) {
            /** FIXME */
            $log->log(
                '[' . $class . '] An error occured trying to initialize fields ' .
                'configuration for table `' . PREFIX_DB . $this->_table . '`.' .
                $e->getMessage(),
                PEAR_LOG_ERR
            );
            throw $e;
        }
    }


    /**
    * Get required fields
    *
    * @return array of all required fields. Field names = keys
    */
    public function getRequired()
    {
        return $this->_all_required;
    }

    /*public function getLabels(){ return $this->_all_labels; }*/
    /*public function getCategories(){ return $this->_all_categories; }*/
    /*public function getPositions(){ return $this->all_positions; }*/
    /*public function getPosition($field){ return $this->all_positions[$field]; }*/

    /**
    * Get visible fields
    *
    * @return array of all visibles fields
    */
    public function getVisibilities()
    {
        return $this->_all_visibles;
    }

    /**
    * Get visibility for specified field
    *
    * @param string $field The requested field
    *
    * @return boolean
    */
    public function getVisibility($field)
    {
        return $this->_all_visibles[$field];
    }

    /**
    * Get all fields with their categories
    *
    * @return array
    */
    public function getCategorizedFields()
    {
        return $this->_categorized_fields;
    }

    /**
    * Get all fields
    *
    * @return array
    */
    public function getFields()
    {
        return $this->fields;
    }

    /** FIXME: should return _store result */
    /**
    * Set fields
    *
    * @param array $fields categorized fields array
    *
    * @return void
    */
    public function setFields($fields)
    {
        $this->_categorized_fields = $fields;
        $this->_store();
    }

    /**
    * Store config in database
    *
    * @return boolean
    */
    private function _store()
    {
        global $mdb, $log;

        $stmt = $mdb->prepare(
            'UPDATE ' . PREFIX_DB . self::TABLE .
            ' SET required=:required, visible=:visible, position=:position, ' .
            FieldsCategories::PK . '=:category WHERE table_name=\'' .
            $this->_table .'\' AND field_id=:field_id',
            $this->_types,
            MDB2_PREPARE_MANIP
        );

        $params = array();
        foreach ( $this->_categorized_fields as $cat ) {
            foreach ( $cat as $pos=>$field ) {
                $params[] = array(
                    'field_id'    =>    $field['field_id'],
                    'required'    =>    $field['required'],
                    'visible'    =>    $field['visible'],
                    'position'    =>    $pos,
                    'category'    =>    $field['category']
                );
            }
        }

        $mdb->getDb()->loadModule('Extended', null, false);
        $mdb->getDb()->extended->executeMultiple($stmt, $params);

        $class = get_class($this);
        if (MDB2::isError($stmt)) {
            $log->log(
                '[' . $class . '] An error occured while storing fields ' .
                'configuration for table `' . $this->_table . '`.' .
                $stmt->getMessage(),
                PEAR_LOG_ERR
            );
            return false;
        } else {
            $log->log(
                '[' . $class . '] Fields configuration stored successfully! ',
                PEAR_LOG_DEBUG
            );
            $log->log(
                str_replace(
                    '%s',
                    $this->_table,
                    '[' . $class . '] Fields configuration for table %s stored ' .
                    'successfully.'
                ),
                PEAR_LOG_INFO
            );
            $stmt->free();
            return true;
        }
    }
}
?>