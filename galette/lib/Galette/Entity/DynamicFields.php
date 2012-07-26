<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Dynamic fields handler
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
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-06-20
 */

namespace Galette\Entity;

use Galette\Common\KLogger as KLogger;

/**
 * Dynamic fields handler for Galette
 *
 * @name DynamicFields
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

class DynamicFields
{
    const TABLE = 'dynamic_fields';
    const TYPES_TABLE = 'field_types';
    const TYPES_PK = 'field_id';

    /** Separator field */
    const SEPARATOR = 0;
    /** Simple text field */
    const TEXT = 1;
    /** Line field */
    const LINE = 2;
    /** Choice field (checkbox) */
    const CHOICE = 3;

    const PERM_ALL = 0;
    const PERM_STAFF = 2;
    const PERM_ADM = 1;

    private $_id;
    private $_index;
    private $_name;
    private $_permissions;
    private $_type;
    private $_type_name;
    private $_required;

    private $_fields_types_names;
    private $_perms_names;
    private $_forms_names;
    private $_fields_properties;

    /**
    * Default constructor
    *
    * @param null|int|ResultSet $args Either a ResultSet row, its id or its 
    *                                 login or its mail for to load
    *                                 a specific member, or null to just
    *                                 instanciate object
    */
    public function __construct($args = null)
    {
        global $i18n;

        //Fields types names
        $this->_fields_types_names = array(
            self::SEPARATOR => _T("separator"),
            self::TEXT      => _T("free text"),
            self::LINE      => _T("single line"),
            self::CHOICE    => _T("choice")
        );

        //Permissions names
        $this->_perms_names = array (
            self::PERM_ALL      => _T("all"),
            self::PERM_STAFF    => _T("staff"),
            self::PERM_ADM      => _T("admin")
        );

        //Forms names
        $this->_forms_names = array(
            'adh'       => _T("Members"),
            'contrib'   => _T("Contributions"),
            'trans'     => _T("Transactions")
        );

        //Properties or each field type
        $this->_fields_properties = array(
            self::SEPARATOR => array(
                'no_data'       => true,
                'with_width'    => false,
                'with_height'   => false,
                'with_size'     => false,
                'multi_valued'  => false,
                'fixed_values'  => false
            ),
            self::TEXT => array(
                'no_data'       => false,
                'with_width'    => true,
                'with_height'   => true,
                'with_size'     => false,
                'multi_valued'  => false,
                'fixed_values'  => false
            ),
            self::LINE => array(
                'no_data'       => false,
                'with_width'    => true,
                'with_height'   => false,
                'with_size'     => true,
                'multi_valued'  => true,
                'fixed_values'  => false
            ),
            self::CHOICE => array(
                'no_data'       => false,
                'with_width'    => false,
                'with_height'   => false,
                'with_size'     => false,
                'multi_valued'  => false,
                'fixed_values'  => true
            )
        );
    }

    /**
     * Retrieve fixed values table name
     *
     * @param integer $id Field's id
     *
     * @return string
     */
    public static function getFixedValuesTableName($id)
    {
        return PREFIX_DB . 'field_contents_' . $id;
    }

    /**
    * Returns an array of fixed valued for a field of type 'choice'.
    *
    * @param string $field_id field id
    *
    * @return array
    */
    public function getFixedValues($field_id)
    {
        global $zdb, $log;

        try {
            $val_select = new Zend_Db_Select($zdb->db);

            $val_select->from(
                self::getFixedValuesTableName($field_id),
                'val'
            )->order('id');

            $results = $val_select->query()->fetchAll();
            $fixed_values = array();
            if ( $results ) {
                foreach ( $results as $val ) {
                    $fixed_values[] = $val->val;
                }
            }
            return $fixed_values;
        } catch (Exception $e) {
            $log->log(
                __METHOD__ . ' | ' . $e->getMessage(),
                KLogger::WARN
            );
            $log->log(
                'Query was: ' . $val_select->__toString() . ' ' . $e->__toString(),
                KLogger::INFO
            );
        }
    }

    /**
     * Retrieve permissions names for display
     *
     * @return array
     */
    public function getPermsNames()
    {
        return $this->_perms_names;
    }


    /**
     * Get permission name
     *
     * @param int $i Array index
     *
     * @return string
     */
    public function getPermName($i)
    {
        return $this->_perms_names[$i];
    }

    /**
     * Retrieve fields properties
     *
     * @return array
     */
    public function getFieldsProperties()
    {
        return $this->_fields_properties;
    }

    /**
     * Retrieve forms names
     *
     * @return array
     */
    public function getFormsNames()
    {
        return $this->_forms_names;
    }

    /**
     * Retrieve fields types names
     *
     * @return array
     */
    public function getFieldsTypesNames()
    {
        return $this->_fields_types_names;
    }

    /**
    * Get dynamic fields for one entry
    * It returns an 2d-array with field id as first key
    * and value index as second key.
    *
    * @param string  $form_name Form name in $all_forms
    * @param string  $item_id   Key to find entry values
    * @param boolean $quote     If true, values are quoted for HTML output
    *
    * @return 2d-array with field id as first key and value index as second key.
    */
    function getFields($form_name, $item_id, $quote)
    {
        global $zdb, $log, $field_properties, $dyn_fields;

        try {
            $select = new \Zend_Db_Select($zdb->db);

            $select->from(PREFIX_DB . self::TABLE)
                ->where('item_id = ?', $item_id)
                ->where('field_form = ?', $form_name);

            $result = $select->query()->fetchAll();

            if ( count($result) > 0 ) {
                $dyn_fields = array();
                $types_select = new \Zend_Db_Select($zdb->db);
                $types_select->from(PREFIX_DB . self::TYPES_TABLE, 'field_type')
                    ->where(self::TYPES_PK . ' = :fieldid');
                $stmt = $zdb->db->prepare($types_select);
                foreach ($result as $f) {
                    $value = $f->field_val;
                    if ( $quote ) {
                        $stmt->bindValue(':fieldid', $f->field_id, \PDO::PARAM_INT);
                        if ( $stmt->execute() ) {
                            $field_type = $stmt->fetch()->field_type;
                            if ($field_properties[$field_type]['fixed_values']) {
                                $choices = $dyn_fields->getFixedValues($f->field_id);
                                $value = $choices[$value];
                            }
                        }
                    }
                    $dyn_fields[$f->field_id][$f->val_index] = $value;
                }
                return $dyn_fields;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            $log->log(
                __METHOD__ . ' | ' . $e->getMessage(),
                KLogger::WARN
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                KLogger::INFO
            );
        }
    }
}
?>
