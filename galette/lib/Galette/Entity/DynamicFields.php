<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Dynamic fields handler
 *
 * PHP version 5
 *
 * Copyright © 2011-2014 The Galette Team
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
 * @copyright 2011-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-06-20
 */

namespace Galette\Entity;

use Analog\Analog;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\Expression as PredicateExpression;
use Galette\DynamicFieldsTypes\Separator;
use Galette\DynamicFieldsTypes\Text;
use Galette\DynamicFieldsTypes\Line;
use Galette\DynamicFieldsTypes\Choice;
use Galette\DynamicFieldsTypes\Date;
use Galette\DynamicFieldsTypes\Boolean;
use Galette\DynamicFieldsTypes\File;
use Galette\DynamicFieldsTypes\DynamicFieldType;

/**
 * Dynamic fields handler for Galette
 *
 * @name DynamicFields
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2014 The Galette Team
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
    /** File field (upload) */
    const FILE = 6;

    const PERM_ALL = 0;
    const PERM_STAFF = 2;
    const PERM_ADM = 1;

    const DEFAULT_MAX_FILE_SIZE = 1024;

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

    private $_errors = array();

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
            self::BOOLEAN   => _T("boolean"),
            self::FILE      => _T("file")
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
     * @param integer $id       Field's id
     * @param boolean $prefixed Whether table name should be prefixed
     *
     * @return string
     */
    public static function getFixedValuesTableName($id, $prefixed = false)
    {
        $name = 'field_contents_' . $id;
        if ($prefixed === true) {
            $name = PREFIX_DB . $name;
        }
        return $name;
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
            $select = $zdb->select(self::getFixedValuesTableName($field_id));
            $select->columns(
                array('val')
            )->order('id');

            $results = $zdb->execute($select);

            $fixed_values = array();
            if ($results) {
                foreach ($results as $val) {
                    $fixed_values[] = $val->val;
                }
            }
            return $fixed_values;
        } catch (\Exception $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
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
            $select = $zdb->select(self::TABLE, 'a');

            $select->join(
                array('b' => PREFIX_DB . DynamicFieldType::TABLE),
                'a.' . DynamicFieldType::PK . '=b.' . DynamicFieldType::PK,
                array('field_type')
            )->where(
                array(
                    'item_id'       => $item_id,
                    'a.field_form'  => $form_name
                )
            );

            $results = $zdb->execute($select);

            if ($results->count() > 0) {
                $dfields = array();

                foreach ($results as $f) {
                    $df = $this->getFieldType($f->field_type);

                    $value = $f->field_val;
                    if ($quote) {
                        if ($df->hasFixedValues()) {
                            $choices = $this->getFixedValues($f->field_id);
                            $value = $choices[$value];
                        }
                    }
                    $array_index = 1;
                    if (isset($dfields[$f->field_id])) {
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
            return false;
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
        $form_name,
        $all_values,
        $disabled,
        $edit
    ) {
        global $zdb, $login;

        try {
            $select = $zdb->select(DynamicFieldType::TABLE);

            $select
                ->where(array('field_form' => $form_name))
                ->order('field_index');

            $results = $zdb->execute($select);

            $dfields = array();
            if ($results) {
                $extra = $edit ? 1 : 0;

                foreach ($results as $r) {
                    $df = $this->getFieldType($r['field_type']);
                    if ((int)$r['field_type'] === self::CHOICE
                        || (int)$r['field_type'] === self::TEXT
                        || (int)$r['field_type'] === self::DATE
                        || (int)$r['field_type'] === self::BOOLEAN
                        || (int)$r['field_type'] === self::FILE
                    ) {
                        $r['field_repeat'] = 1;
                    }
                    $field_id = $r['field_id'];
                    $r['field_name'] = _T($r['field_name']);
                    //store field repetition config as field_repeat may change
                    $r['config_field_repeat'] = $r['field_repeat'];

                    if ($df->isMultiValued()) {
                         // Infinite multi-valued field
                        if ($r['field_repeat'] == 0) {
                            if (isset($all_values[$r['field_id']])) {
                                $nb_values = count($all_values[$r['field_id']]);
                            } else {
                                $nb_values = 0;
                            }
                            if (isset($all_values)) {
                                $r['field_repeat'] = $nb_values + $extra;
                            } else {
                                $r['field_repeat'] = 1 + $extra;
                            }
                        }
                    } else {
                        $r['field_repeat'] = 1;
                        if ($df->hasFixedValues()) {
                            $r['choices'] = $this->getFixedValues($field_id);
                        }
                    }

                    //Disable field depending on ACLs
                    if (!$login->isAdmin()
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
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
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
            $select = $zdb->select(DynamicFieldType::TABLE);
            $select
                ->where(array('field_form' => $form_name))
                ->order('field_id');

            $results = $zdb->execute($select);

            $dfields = array();
            if ($results) {
                foreach ($results as $r) {
                    $dfields[$r['field_id']] = $r;
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
        }
    }

    /**
     * Extract posted values for dynamic fields
     *
     * @param array $post      Array containing the posted values
     * @param array $files     Array containing the posted files
     * @param array $disabled  Array with fields that are discarded as key
     * @param int   $member_id Member id
     *
     * @return array
     */
    public function extractPosted($post, $files, $disabled, $member_id)
    {
        if ($post != null) {
            $dfields = array();

            // initialize all boolean fields to 0
            $descriptions = $this->getFieldsDescription('adh');
            while (list ($field_id, $description) = each($descriptions)) {
                if ((int) $description['field_type'] == self::BOOLEAN) {
                    $dfields[$field_id][1] = 0;
                }
            }

            while (list($key, $value) = each($post)) {
                // if the field is enabled, check it
                if (!isset($disabled[$key])) {
                    if (substr($key, 0, 11) == 'info_field_') {
                        list($field_id, $val_index) = explode('_', substr($key, 11));
                        if (is_numeric($field_id)
                            && is_numeric($val_index)
                        ) {
                            if ((int) $descriptions[$field_id]['field_type'] == self::FILE) {
                                //delete checkbox
                                $filename = sprintf(
                                    'member_%d_field_%d_value_%d',
                                    $member_id,
                                    $field_id,
                                    $val_index
                                );
                                unlink(GALETTE_FILES_PATH . $filename);
                                $dfields[$field_id][$val_index] = '';
                            } else {
                                //actual field value
                                $dfields[$field_id][$val_index] = $value;
                            }
                        }
                    }
                }
            }

            while (list($key, $value) = each($files)) {
                // if the field is disabled, skip it
                if (isset($disabled[$key])) {
                    continue;
                }

                if (substr($key, 0, 11) != 'info_field_') {
                    continue;
                }

                list($field_id, $val_index) = explode('_', substr($key, 11));
                if (! is_numeric($field_id) || ! is_numeric($val_index)) {
                    continue;
                }

                if ($files[$key]['error'] !== UPLOAD_ERR_OK) {
                    Analog::log("file upload error", Analog::ERROR);
                    continue;
                }

                $tmp_filename = $files[$key]['tmp_name'];
                if ($tmp_filename == '') {
                    Analog::log("empty temporary filename", Analog::ERROR);
                    continue;
                }

                if (!is_uploaded_file($tmp_filename)) {
                    Analog::log("not an uploaded file", Analog::ERROR);
                    continue;
                }

                $max_size =
                    $descriptions[$field_id]['field_size'] === 'NULL' ?
                    self::DEFAULT_MAX_FILE_SIZE            * 1024:
                    $descriptions[$field_id]['field_size'] * 1024;
                if ($files[$key]['size'] > $max_size) {
                    Analog::log(
                        "file too large: " . $files[$key]['size'] . " Ko, vs $max_size Ko allowed",
                        Analog::ERROR
                    );
                    $this->_errors[] = preg_replace(
                        '|%d|',
                        $max_size,
                        _T("File is too big. Maximum allowed size is %dKo")
                    );
                    continue;
                }

                $new_filename = sprintf(
                    'member_%d_field_%d_value_%d',
                    $member_id,
                    $field_id,
                    $val_index
                );
                Analog::log("new file: $new_filename", Analog::DEBUG);

                move_uploaded_file(
                    $tmp_filename,
                    GALETTE_FILES_PATH . $new_filename
                );
                $dfields[$field_id][$val_index] = $files[$key]['name'];
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
    private function setField(
        $form_name,
        $item_id,
        $field_id,
        $val_index,
        $field_val
    ) {
        global $zdb;
        $ret = false;

        try {
            $zdb->connection->beginTransaction();

            $select = $zdb->select(self::TABLE);
            $select->columns(
                array('cnt' => new Expression('COUNT(*)'))
            )->where(
                array(
                    'item_id'       => $item_id,
                    'field_form'    => $form_name,
                    'field_id'      => $field_id,
                    'val_index'     => $val_index
                )
            );

            $results = $zdb->execute($select);
            $result = $results->current();
            $count = $result->cnt;

            if ($count > 0) {
                $where = $select->where;

                if (trim($field_val) == '') {
                    Analog::log(
                        'Field ' . $field_id . ' is empty (index:' .
                        $val_index . ')',
                        Analog::DEBUG
                    );

                    $delete = $zdb->delete(self::TABLE);
                    $delete->where($where);
                    $zdb->execute($delete);
                } else {
                    Analog::log(
                        'Field ' . $field_id . ' will be set to value: ' .
                        $field_val . ' (index: ' . $val_index . ')',
                        Analog::DEBUG
                    );

                    $update = $zdb->update(self::TABLE);
                    $update->set(
                        array('field_val' => $field_val)
                    )->where($where);
                    $zdb->execute($update);
                }
            } else {
                if ($field_val !== '') {
                    $values = array(
                        'item_id'    => $item_id,
                        'field_form' => $form_name,
                        'field_id'   => $field_id,
                        'val_index'  => $val_index,
                        'field_val'  => $field_val
                    );

                    $insert = $zdb->insert(self::TABLE);
                    $insert->values($values);
                    $zdb->execute($insert);
                }
            }

            $zdb->connection->commit();
            return true;
        } catch (\Exception $e) {
            $zdb->connection->rollBack();
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
        while (list($field_id, $contents) = each($all_values)) {
            while (list($val_index, $field_val) = each($contents)) {
                $res = $this->setField(
                    $form_name,
                    $item_id,
                    $field_id,
                    $val_index,
                    $field_val
                );
                if (!$res) {
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
        switch ($t) {
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
            case self::FILE:
                $df = new \Galette\DynamicFieldsTypes\File($id);
                break;
            default:
                throw new \Exception('Unknown field type ' . $t . '!');
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
            $select = $zdb->select(DynamicFieldType::TABLE);
            $select->columns(
                array('field_type')
            )->where('field_id = ' . $id);

            $results = $zdb->execute($select);
            $result = $results->current();
            $field_type = $result->field_type;
            if ($field_type !== false) {
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
            $select = $zdb->select(DynamicFieldType::TABLE);
            $select->columns(
                array(
                    'cnt' => new Expression('COUNT(field_id)')
                )
            )->where(
                array(
                    'field_form' => $form_name,
                    'field_name' => $field_name
                )
            );

            if ($field_id !== null) {
                $select->where->addPredicate(
                    new PredicateExpression(
                        'field_id NOT IN (?)',
                        array($field_id)
                    )
                );
            }

            $results = $zdb->execute($select);
            $result = $results->current();
            $dup = $result->cnt;
            if (!$dup > 0) {
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

    /**
     * Get errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->_errors;
    }
}
