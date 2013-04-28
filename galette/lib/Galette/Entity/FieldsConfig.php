<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Fields config handling
 *
 * PHP version 5
 *
 * Copyright © 2009-2013 The Galette Team
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
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-26
 */

namespace Galette\Entity;

use Analog\Analog as Analog;

/**
 * Fields config class for galette :
 * defines fields mandatory, order and visibility
 *
 * @category  Entity
 * @name      FieldsConfig
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
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
    //private $error = array();
    private $_categorized_fields = array();
    private $_table;
    private $_defaults = null;

    const TABLE = 'fields_config';

    private $_types = array(
        'text',
        'text',
        'boolean',
        'integer',
        'integer',
        'integer'
    );

    /*
    * Fields that are not visible in the
    * form should not be visible here.
    */
    private $_non_required = array(
        'id_adh',
        'date_echeance',
        'bool_display_info',
        'bool_exempt_adh',
        'bool_admin_adh',
        'activite_adh',
        'date_crea_adh',
        'date_modif_adh',
        //Fields we do not want to be set as required
        'societe_adh',
        'id_statut',
        'pref_lang',
        'sexe_adh'
    );

    /**
     * Default constructor
     *
     * @param string  $table    the table for which to get fields configuration
     * @param array   $defaults default values
     * @param boolean $install  Are we calling from installer?
     */
    function __construct($table, $defaults, $install = false)
    {
        $this->_table = $table;
        $this->_defaults = $defaults;
        $this->_all_required = array();
        $this->_all_visibles = array();
        //prevent check at install time...
        if ( !$install ) {
            $this->load();
            $this->_checkUpdate();
        }
    }

    /**
     * Load current preferences from database.
     *
     * @return boolean
     */
    public function load()
    {
        global $zdb;

        $this->_prefs = array();

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . self::TABLE)
                ->where('table_name = ?', $this->_table)
                ->order(array(FieldsCategories::PK, 'position ASC'));

            $result = $select->query()->fetchAll();

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
            }
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'Fields configuration cannot be loaded!',
                Analog::URGENT
            );
            return false;
        }
    }

    /**
     * Checks if all fields are present in the database.
     *
     * For now, this function only checks if count matches.
     *
     * @return void
     */
    private function _checkUpdate()
    {
        global $zdb;
        $class = get_class($this);

        try {
            $_all_fields = array();
            array_walk(
                $this->_categorized_fields,
                function ($cat) use (&$_all_fields) {
                    $field = null;
                    array_walk(
                        $cat,
                        function ($f) use (&$field) {
                            $field[$f['field_id']] = $f;
                        }
                    );
                    $_all_fields = array_merge($_all_fields, $field);
                }
            );

            if ( count($this->_defaults) != count($_all_fields) ) {
                Analog::log(
                    'Fields configuration count for `' . $this->_table .
                    '` columns does not match records. Is : ' .
                    count($_all_fields) . ' and should be ' . count($this->_defaults),
                    Analog::WARNING
                );

                $params = array();
                foreach ($this->_defaults as $k=>$f) {
                    if ( !isset($_all_fields[$k]) ) {
                        Analog::log(
                            'Missing field configuration for field `' . $k . '`',
                            Analog::INFO
                        );
                        $required = $f['required'];
                        if ( $required === false ) {
                            $required = 'false';
                        }
                        $params[] = array(
                            ':field_id'    => $k,
                            ':table_name'  => $this->_table,
                            ':required'    => $required,
                            ':visible'     => $f['visible'],
                            ':position'    => $f['position'],
                            ':category'    => $f['category'],
                        );
                    }
                }

                if ( count($params) > 0 ) {
                    $stmt = $zdb->db->prepare(
                        'INSERT INTO ' . PREFIX_DB . self::TABLE .
                        ' (table_name, field_id, required, visible, position, ' .
                        FieldsCategories::PK .
                        ') VALUES(:table_name, :field_id, :required, :visible, :position, ' .
                        ':category)'
                    );
                    foreach ( $params as $p ) {
                        $stmt->execute($p);
                    }
                    $this->load();
                }
            }
        } catch (\Exception $e) {
            Analog::log(
                '[' . $class . '] An error occured while checking update for ' .
                'fields configuration for table `' . $this->_table . '`. ' .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Set default fields configuration at install time. All previous
     * existing values will be dropped first, including fields categories.
     *
     * @param Db $zdb Database instance
     *
     * @return boolean|Exception
     */
    public function installInit($zdb)
    {
        try {
            $fields = array_keys($this->_defaults);
            $class = get_class($this);
            $categories = new FieldsCategories();

            //first, we drop all values
            $zdb->db->delete(
                PREFIX_DB . self::TABLE,
                $zdb->db->quoteInto('table_name = ?', $this->_table)
            );
            //take care of fields categories, for db relations
            $categories->installInit($zdb);

            $stmt = $zdb->db->prepare(
                'INSERT INTO ' . PREFIX_DB . self::TABLE .
                ' (table_name, field_id, required, visible, position, ' .
                FieldsCategories::PK .
                ') VALUES(:table_name, :field_id, :required, :visible, :position, ' .
                ':category)'
            );

            $fields = array_keys($this->_defaults);
            foreach ( $fields as $f ) {
                //build default config for each field
                $required = $this->_defaults[$f]['required'];
                if ( $required === false ) {
                    $required = 'false';
                }
                $params = array(
                    ':field_id'    => $f,
                    ':table_name'  => $this->_table,
                    ':required'    => $required,
                    ':visible'     => $this->_defaults[$f]['visible'],
                    ':position'    => $this->_defaults[$f]['position'],
                    ':category'    => $this->_defaults[$f]['category'],
                );
                $stmt->execute($params);
            }

            Analog::log(
                'Default fields configuration were successfully stored.',
                Analog::INFO
            );
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'Unable to initialize default fields configuration.' .
                $e->getMessage(),
                Analog::WARNING
            );
            return $e;
        }
    }

    /**
     * Get non required fields
     *
     * @return array
     */
    public function getNonRequired()
    {
        return $this->_non_required;
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

    /**
     * Set fields
     *
     * @param array $fields categorized fields array
     *
     * @return boolean
     */
    public function setFields($fields)
    {
        $this->_categorized_fields = $fields;
        return $this->_store();
    }

    /**
     * Store config in database
     *
     * @return boolean
     */
    private function _store()
    {
        global $zdb;

        $class = get_class($this);

        try {
            $zdb->db->beginTransaction();
            $sql = 'UPDATE ' . PREFIX_DB . self::TABLE .
                ' SET required=:required, visible=:visible, position=:position, ' .
                FieldsCategories::PK . '=:category WHERE table_name=\'' .
                $this->_table .'\' AND field_id=:field_id';
            $stmt = $zdb->db->prepare($sql);

            $params = null;
            foreach ( $this->_categorized_fields as $cat ) {
                foreach ( $cat as $pos=>$field ) {
                    if ( in_array($field['field_id'], $this->_non_required) ) {
                        $field['required'] = 'false';
                    }
                    $params = array(
                        'field_id'  => $field['field_id'],
                        'required'  => $field['required'],
                        'visible'   => $field['visible'],
                        'position'  => $pos,
                        'category'  => $field['category']
                    );
                    $stmt->execute($params);
                }
            }

            Analog::log(
                '[' . $class . '] Fields configuration stored successfully! ',
                Analog::DEBUG
            );
            Analog::log(
                str_replace(
                    '%s',
                    $this->_table,
                    '[' . $class . '] Fields configuration for table %s stored ' .
                    'successfully.'
                ),
                Analog::INFO
            );

            $zdb->db->commit();
            return true;
        } catch (\Exception $e) {
            $zdb->db->rollBack();
            Analog::log(
                '[' . $class . '] An error occured while storing fields ' .
                'configuration for table `' . $this->_table . '`.' .
                $e->getMessage(),
                Analog::ERROR
            );
            Analog::log(
                $e->getTraceAsString(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Migrate old required fields configuration
     * Only needeed for 0.7.4 upgrade
     * (should have been 0.7.3 - but I missed that.)
     *
     * @param Db $zdb Database instance
     *
     * @return boolean
     */
    public function migrateRequired($zdb)
    {
        $old_required = null;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . 'required');

            $old_required = $select->query()->fetchAll();
        } catch ( \Exception $pe ) {
            Analog::log(
                'Unable to retrieve required fields_config. Maybe ' .
                'the table does not exists?',
                Analog::WARNING
            );
            //not a blocker
            return true;
        }

        $zdb->db->beginTransaction();
        try {
            $sql = 'UPDATE ' . PREFIX_DB . self::TABLE .
                ' SET required=:required WHERE table_name=\'' .
                $this->_table .'\' AND field_id=:field_id';
            $stmt = $zdb->db->prepare($sql);

            foreach ( $old_required as $or ) {
                $params = array(
                    'field_id'  => $or->field_id,
                    'required'  => ($or->required === false) ?  'false' : true
                );
                $stmt->execute($params);
            }

            $class = get_class($this);
            Analog::log(
                str_replace(
                    '%s',
                    $this->_table,
                    '[' . $class . '] Required fields for table %s upgraded ' .
                    'successfully.'
                ),
                Analog::INFO
            );

            $zdb->db->query('DROP TABLE ' . PREFIX_DB . 'required;');

            $zdb->db->commit();
            return true;
        } catch ( \Exception $e ) {
            $zdb->db->rollBack();
            Analog::log(
                'An error occured migrating old required fields. | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }
}
