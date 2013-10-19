<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Dynamic fields handler
 *
 * PHP version 5
 *
 * Copyright © 2011-2013 The Galette Team
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
 * @copyright 2011-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-06-20
 */

namespace Galette\Entity;

use Analog\Analog as Analog;
use Galette\DynamicFieldsTypes\Separator as Separator;
use Galette\DynamicFieldsTypes\Text as Text;
use Galette\DynamicFieldsTypes\Line as Line;
use Galette\DynamicFieldsTypes\Choice as Choice;
use Galette\DynamicFieldsTypes\Date as Date;
use Galette\DynamicFieldsTypes\Boolean as Boolean;
use Galette\DynamicFieldsTypes\DynamicFieldType as DynamicFieldType;

/**
 * Dynamic fields handler for Galette
 *
 * @name DynamicFields
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

class DynamicFields
{
    const TABLE = 'dynamic_fields';

    /** Separator field */
    const SEPARATOR = 0;
    /** Simple text field */
    const TEXT = 1;
    /** Line field */
    const LINE = 2;
    /** Choice field (listbox) */
    const CHOICE = 3;
    /** Date field */
    const DATE = 4;
    /** Boolean field (checkbox) */
    const BOOLEAN = 5;

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
        //Fields types names
        $this->_fields_types_names = array(
            self::SEPARATOR => _T("separator"),
            self::TEXT      => _T("free text"),
            self::LINE      => _T("single line"),
            self::CHOICE    => _T("choice"),
            self::DATE      => _T("date"),
            self::BOOLEAN   => _T("boolean")
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
        global $zdb;

        try {
            $val_select = new \Zend_Db_Select($zdb->db);

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
        } catch (\Exception $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
            Analog::log(
                'Query was: ' . $val_select->__toString() . ' ' . $e->__toString(),
                Analog::INFO
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
    public function getFields($form_name, $item_id, $quote)
    {
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);

            $select->from(
                array('a' => PREFIX_DB . self::TABLE)
            )->join(
                array('b' => PREFIX_DB . DynamicFieldType::TABLE),
                'a.' . DynamicFieldType::PK . '=b.' . DynamicFieldType::PK,
                array('field_type')
            )
                ->where('item_id = ?', $item_id)
                ->where('a.field_form = ?', $form_name);

            $result = $select->query()->fetchAll();

            if ( count($result) > 0 ) {
                $dfields = array();

                foreach ($result as $f) {
                    $df = $this->getFieldType($f->field_type);

                    $value = $f->field_val;
                    if ( $quote ) {
                        if ( $df->hasFixedValues() ) {
                            $choices = $this->getFixedValues($f->field_id);
                            $value = $choices[$value];
                        }
                    }
                    $array_index = 1;
                    if ( isset($dfields[$f->field_id]) ) {
                        $array_index = count($dfields[$f->field_id]) + 1;
                    }
                    $dfields[$f->field_id][$array_index] = $value;
                }
                return $dfields;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
            Analog::log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                Analog::INFO
            );
        }
    }

    /**
     * Returns an array of all kind of fields to display.
     *
     * @param string  $form_name  Form name in $all_forms
     * @param array   $all_values Values as returned by
     *                            self::extractPosted
     * @param array   $disabled   Array that will be filled with fields
     *                            that are discarded as key
     * @param boolean $edit       Must be true if prepared for edition
     *
     * @return array
     */
    public function prepareForDisplay(
        $form_name, $all_values, $disabled, $edit
    ) {
        global $zdb, $login;

        try {
            $select = new \Zend_Db_Select($zdb->db);

            $select->from(PREFIX_DB . DynamicFieldType::TABLE)
                ->where('field_form = ?', $form_name)
                ->order('field_index');

            $result = $select->query(\Zend_DB::FETCH_ASSOC)->fetchAll();

            $dfields = array();
            if ( $result ) {
                $extra = $edit ? 1 : 0;

                foreach ( $result as $r ) {
                    $df = $this->getFieldType($r['field_type']);
                    if ( (int)$r['field_type'] === self::CHOICE
                        || (int)$r['field_type'] === self::TEXT
                        || (int)$r['field_type'] === self::DATE
                        || (int)$r['field_type'] === self::BOOLEAN
                    ) {
                        $r['field_repeat'] = 1;
                    }
                    $field_id = $r['field_id'];
                    $r['field_name'] = _T($r['field_name']);
                    //store field repetition config as field_repeat may change
                    $r['config_field_repeat'] = $r['field_repeat'];

                    if ( $df->isMultiValued() ) {
                         // Infinite multi-valued field
                        if ( $r['field_repeat'] == 0 ) {
                            if ( isset($all_values[$r['field_id']]) ) {
                                $nb_values = count($all_values[$r['field_id']]);
                            } else {
                                $nb_values = 0;
                            }
                            if ( isset($all_values) ) {
                                $r['field_repeat'] = $nb_values + $extra;
                            } else {
                                $r['field_repeat'] = 1 + $extra;
                            }
                        }
                    } else {
                        $r['field_repeat'] = 1;
                        if ( $df->hasFixedValues() ) {
                            $r['choices'] = $this->getFixedValues($field_id);
                        }
                    }

                    //Disable field depending on ACLs
                    if ( !$login->isAdmin()
                        && !$login->isStaff()
                        && ($r['field_perm'] == self::PERM_ADM
                        || $r['field_perm'] == self::PERM_STAFF)
                    ) {
                        $disabled[$field_id] = 'disabled';
                    }

                    $dfields[] = $r;
                }
                return $dfields;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            /** TODO */
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
            Analog::log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                Analog::INFO
            );
        }
    }

    /**
     * Get fields descriptions
     *
     * @param string $form_name Form name
     *
     * @return array
     */
    public function getFieldsDescription($form_name)
    {
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);

            $select->from(PREFIX_DB . DynamicFieldType::TABLE)
                ->where('field_form = ?', $form_name)
                ->order('field_id');

            $result = $select->query(\Zend_DB::FETCH_ASSOC)->fetchAll();

            $dfields = array();
            if ( $result ) {
                foreach ( $result as $r ) {
                    $dfields[$r['field_id']] = $r;
                }
                return $dfields;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            /** TODO */
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
            Analog::log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                Analog::INFO
            );
        }
    }

    /**
     * Extract posted values for dynamic fields
     *
     * @param array $post     Array containing the posted values
     * @param array $disabled Array with fields that are discarded as key
     *
     * @return array
     */
    public function extractPosted($post, $disabled)
    {
        if ( $post != null ) {
            $dfields = array();

            // initialize all boolean fields to 0
            $descriptions = $this->getFieldsDescription('adh');
            while (list ($field_id, $description) = each($descriptions)) {
                if ((int) $description['field_type'] == self::BOOLEAN) {
                    $dfields[$field_id][1] = 0;
                }
            }

            while ( list($key, $value) = each($post) ) {
                // if the field is enabled, check it
                if ( !isset($disabled[$key]) ) {
                    if (substr($key, 0, 11) == 'info_field_') {
                        list($field_id, $val_index) = explode('_', substr($key, 11));
                        if ( is_numeric($field_id)
                            && is_numeric($val_index)
                        ) {
                            $dfields[$field_id][$val_index] = $value;
                        }
                    }
                }
            }
            return $dfields;
        }
    }

    /**
     * Set dynamic fields for a given entry
     *
     * @param string $form_name Form name in $all_forms
     * @param string $item_id   Key to find entry values
     * @param string $field_id  Id assign to the field on creation
     * @param string $val_index For multi-valued fields, it is the rank
     *                          of this particular value
     * @param string $field_val The value itself
     *
     * @return boolean
     */
    private function _setField(
        $form_name, $item_id, $field_id, $val_index, $field_val
    ) {
        global $zdb;
        $ret = false;

        try {
            $zdb->db->beginTransaction();

            $select = new \Zend_Db_Select($zdb->db);
            $select->from(
                PREFIX_DB . self::TABLE,
                array('cnt' => 'count(*)')
            )->where('item_id = ?', $item_id)
                ->where('field_form = ?', $form_name)
                ->where('field_id = ?', $field_id)
                ->where('val_index = ?', $val_index);

            $count = $select->query()->fetchColumn();

            if ( $count > 0 ) {
                //cleanup WHERE array so it can be sent back to update
                //and delete methods
                $where = array();
                $owhere = $select->getPart(\Zend_Db_Select::WHERE);
                foreach ( $owhere as $c ) {
                    $where[] = preg_replace('/^AND /', '', $c);
                }

                if ( trim($field_val) == '' ) {
                    Analog::log(
                        'Field ' . $field_id . ' is empty (index:' .
                        $val_index . ')',
                        Analog::DEBUG
                    );
                    $zdb->db->delete(
                        PREFIX_DB . self::TABLE,
                        $where
                    );
                } else {
                    Analog::log(
                        'Field ' . $field_id . ' will be set to value: ' .
                        $field_val . ' (index: ' . $val_index . ')',
                        Analog::DEBUG
                    );
                    $zdb->db->update(
                        PREFIX_DB . self::TABLE,
                        array('field_val' => $field_val),
                        $where
                    );
                }
            } else {
                if ( $field_val !== '' ) {
                    $values = array(
                        'item_id'    => $item_id,
                        'field_form' => $form_name,
                        'field_id'   => $field_id,
                        'val_index'  => $val_index,
                        'field_val'  => $field_val
                    );

                    $zdb->db->insert(
                        PREFIX_DB . self::TABLE,
                        $values
                    );
                }
            }

            $zdb->db->commit();
            return true;
        } catch (\Exception $e) {
            $zdb->db->rollBack();
            Analog::log(
                'An error occured storing dynamic field. Form name: ' . $form_name .
                '; item_id:' . $item_id . '; field_id: ' . $field_id .
                '; val_index: ' . $val_index . '; field_val:' . $field_val .
                ' | Error was: ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Set all dynamic fields for a given entry
     *
     * @param string $form_name  Form name in $all_forms
     * @param string $item_id    Key to find entry values
     * @param array  $all_values Values as returned by
     *                           self::extractPosted.
     *
     * @return boolean
     */
    public function setAllFields($form_name, $item_id, $all_values)
    {
        $ret = true;
        while ( list($field_id, $contents) = each($all_values) ) {
            while ( list($val_index, $field_val) = each($contents) ) {
                $res = $this->_setField(
                    $form_name,
                    $item_id,
                    $field_id,
                    $val_index,
                    $field_val
                );
                if ( !$res ) {
                    $ret = false;
                }
            }
        }
        return $ret;
    }

    /**
     * Get correct field type instance
     *
     * @param int $t  field type
     * @param int $id optionnal dynamic field id (ot laod data)
     *
     * @return DynamicFieldType
     */
    public function getFieldType($t, $id = null)
    {
        $df = null;
        switch ( $t ) {
        case self::SEPARATOR:
            $df = new \Galette\DynamicFieldsTypes\Separator($id);
            break;
        case self::TEXT:
            $df = new \Galette\DynamicFieldsTypes\Text($id);
            break;
        case self::LINE:
            $df = new \Galette\DynamicFieldsTypes\Line($id);
            break;
        case self::CHOICE:
            $df = new \Galette\DynamicFieldsTypes\Choice($id);
            break;
        case self::DATE:
            $df = new \Galette\DynamicFieldsTypes\Date($id);
            break;
        case self::BOOLEAN:
            $df = new \Galette\DynamicFieldsTypes\Boolean($id);
            break;
        default:
            throw new \Exception('Unknow field type ' . $t . '!');
            break;
        }
        return $df;
    }

    /**
     * Load field from its id
     *
     * @param int $id field id
     *
     * @return DynamicFieldType or false
     */
    public function loadFieldType($id)
    {
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(
                PREFIX_DB . DynamicFieldType::TABLE,
                'field_type'
            )->where('field_id = ?', $id);
            $field_type = $select->query()->fetchColumn();
            if ( $field_type !== false ) {
                return $this->getFieldType($field_type, $id);
            } else {
                return false;
            }
        } catch (\Exception $e) {
            Analog::log(
                __METHOD__ . ' | Unable to retrieve field `' . $id .
                '` informations | ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Is field duplicated?
     *
     * @param Db     $zdb        Database instance
     * @param string $form_name  Form name
     * @param string $field_name Field name
     * @param string $field_id   Field ID
     *
     * @return boolean
     */
    public function isDuplicate($zdb, $form_name, $field_name, $field_id = null)
    {
        //let's consider field is duplicated, in case of future errors
        $duplicated = true;
        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(
                PREFIX_DB . DynamicFieldType::TABLE,
                'COUNT(field_id)'
            )->where('field_form = ?', $form_name)
                ->where('field_name = ?', $field_name);

            if ( $field_id !== null ) {
                $select->where('NOT field_id = ?', $field_id);
            }
            $dup = $select->query()->fetchColumn();
            if ( !$dup > 0 ) {
                $duplicated = false;
            }
        } catch (\Exception $e) {
            Analog::log(
                'An error occured checking field duplicity' . $e->getMessage(),
                Analog::ERROR
            );
        }
        return $duplicated;
    }
}
