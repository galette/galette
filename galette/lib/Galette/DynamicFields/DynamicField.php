<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Abstract dynamic field
 *
 * PHP version 5
 *
 * Copyright © 2012-2021 The Galette Team
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
 * @category  DynamicFields
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.1dev - 2012-07-28
 */

namespace Galette\DynamicFields;

use Throwable;
use Analog\Analog;
use Galette\Core\Db;
use Galette\Entity\DynamicFieldsHandle;
use Galette\Features\Translatable;
use Galette\Features\I18n;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;

/**
 * Abstract dynamic field
 *
 * @name      DynamicField
 * @category  DynamicFields
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

abstract class DynamicField
{
    use Translatable;
    use I18n;

    public const TABLE = 'field_types';
    public const PK = 'field_id';

    /** Separator field */
    public const SEPARATOR = 0;
    /** Simple text field */
    public const TEXT = 1;
    /** Line field */
    public const LINE = 2;
    /** Choice field (listbox) */
    public const CHOICE = 3;
    /** Date field */
    public const DATE = 4;
    /** Boolean field (checkbox) */
    public const BOOLEAN = 5;
    /** File field (upload) */
    public const FILE = 6;

    public const PERM_USER_WRITE = 0;
    public const PERM_ADMIN = 1;
    public const PERM_STAFF = 2;
    public const PERM_MANAGER = 3;
    public const PERM_USER_READ = 4;

    public const DEFAULT_MAX_FILE_SIZE = 1024;
    public const VALUES_FIELD_LENGTH = 100;

    protected $has_data = false;
    protected $has_width = false;
    protected $has_height = false;
    protected $has_size = false;
    protected $multi_valued = false;
    protected $fixed_values = false;
    protected $has_permissions = true;

    protected $id;
    protected $index;
    protected $perm;
    protected $required;
    protected $width;
    protected $height;
    protected $repeat;
    protected $size;
    protected $old_size;
    protected $values;
    protected $form;

    protected $errors;

    protected $zdb;

    /**
     * Default constructor
     *
     * @param Db    $zdb  Database instance
     * @param mixed $args Arguments
     */
    public function __construct(Db $zdb, $args = null)
    {
        $this->zdb = $zdb;

        if (is_int($args)) {
            $this->load($args);
        } elseif ($args !== null && is_object($args)) {
            $this->loadFromRs($args);
        }
    }

    /**
     * Load field from its id
     *
     * @param Db  $zdb Database instance
     * @param int $id  Field id
     *
     * @return DynamicField|false
     */
    public static function loadFieldType(Db $zdb, $id)
    {
        try {
            $select = $zdb->select(self::TABLE);
            $select->where('field_id = ' . $id);

            $results = $zdb->execute($select);
            $result = $results->current();
            if ($result) {
                $field_type = $result->field_type;
                $field_type = self::getFieldType($zdb, $field_type);
                $field_type->loadFromRs($result);
                return $field_type;
            }
        } catch (Throwable $e) {
            Analog::log(
                __METHOD__ . ' | Unable to retrieve field `' . $id .
                '` information | ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
        return false;
    }

    /**
     * Get correct field type instance
     *
     * @param Db  $zdb Database instance
     * @param int $t   Field type
     * @param int $id  Optional dynamic field id (to load data)
     *
     * @return DynamicField
     */
    public static function getFieldType(Db $zdb, $t, $id = null)
    {
        $df = null;
        switch ($t) {
            case self::SEPARATOR:
                $df = new Separator($zdb, $id);
                break;
            case self::TEXT:
                $df = new Text($zdb, $id);
                break;
            case self::LINE:
                $df = new Line($zdb, $id);
                break;
            case self::CHOICE:
                $df = new Choice($zdb, $id);
                break;
            case self::DATE:
                $df = new Date($zdb, $id);
                break;
            case self::BOOLEAN:
                $df = new Boolean($zdb, $id);
                break;
            case self::FILE:
                $df = new File($zdb, $id);
                break;
            default:
                throw new \Exception('Unknown field type ' . $t . '!');
                break;
        }
        return $df;
    }

    /**
     * Load field
     *
     * @param integer $id Id
     *
     * @return void
     */
    public function load($id)
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->where(self::PK . ' = ' . $id);

            $results = $this->zdb->execute($select);
            $result = $results->current();

            if ($result) {
                $this->loadFromRs($result);
            }
        } catch (Throwable $e) {
            Analog::log(
                'Unable to retrieve field type for field ' . $id . ' | ' .
                $e->getMessage(),
                Analog::ERROR
            );
        }
    }

    /**
     * Load field type from a db ResultSet
     *
     * @param ResultSet $rs     ResultSet
     * @param boolean   $values Whether to load values. Defaults to true
     *
     * @return void
     */
    public function loadFromRs($rs, $values = true)
    {
        $this->id = (int)$rs->field_id;
        $this->name = $rs->field_name;
        $this->index = (int)$rs->field_index;
        $this->perm = (int)$rs->field_perm;
        $this->required = ($rs->field_required == 1 ? true : false);
        $this->width = $rs->field_width;
        $this->height = $rs->field_height;
        $this->repeat = $rs->field_repeat;
        $this->size = $rs->field_size;
        $this->form = $rs->field_form;
        if ($values && $this->hasFixedValues()) {
            $this->loadFixedValues();
        }
    }

    /**
     * Retrieve fixed values table name
     *
     * @param integer $id       Field ID
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
     * @return void
     */
    private function loadFixedValues()
    {
        try {
            $val_select = $this->zdb->select(
                self::getFixedValuesTableName($this->id)
            );

            $val_select->columns(
                array(
                    'val'
                )
            )->order('id');

            $results = $this->zdb->execute($val_select);
            $this->values = array();
            if ($results) {
                foreach ($results as $val) {
                    $this->values[] = $val->val;
                }
            }
        } catch (Throwable $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
        }
    }

    /**
     * Get field type
     *
     * @return integer
     */
    abstract public function getType();

    /**
     * Get field type name
     *
     * @return String
     */
    public function getTypeName()
    {
        $types = $this->getFieldsTypesNames();
        if (isset($types[$this->getType()])) {
            return $types[$this->getType()];
        } else {
            throw new \RuntimeException(
                'Unknow type ' . $this->getType()
            );
        }
    }

    /**
     * Does the field handle data?
     *
     * @return boolean
     */
    public function hasData()
    {
        return $this->has_data;
    }

    /**
     * Does the field has width?
     *
     * @return boolean
     */
    public function hasWidth()
    {
        return $this->has_width;
    }

    /**
     * Does the field has height?
     *
     * @return boolean
     */
    public function hasHeight()
    {
        return $this->has_height;
    }

    /**
     * Does the field has a size?
     *
     * @return boolean
     */
    public function hasSize()
    {
        return $this->has_size;
    }

    /**
     * Is the field multi valued?
     *
     * @return boolean
     */
    public function isMultiValued()
    {
        return $this->multi_valued;
    }

    /**
     * Does the field has fixed values?
     *
     * @return boolean
     */
    public function hasFixedValues()
    {
        return $this->fixed_values;
    }

    /**
     * Does the field require permissions?
     *
     * @return boolean
     */
    public function hasPermissions()
    {
        return $this->has_permissions;
    }


    /**
     * Get field id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get field Permissions
     *
     * @return integer
     */
    public function getPerm()
    {
        return $this->perm;
    }


    /**
     * Is field required?
     *
     * @return boolean
     */
    public function isRequired()
    {
        return $this->required;
    }

    /**
     * Get field width
     *
     * @return integer
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * Get field height
     *
     * @return integer
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * Is current field repeatable?
     *
     * @return boolean
     */
    public function isRepeatable()
    {
        return $this->repeat != null && trim($this->repeat) != '' && (int)$this->repeat >= 0;
    }

    /**
     * Get fields repetitions
     *
     * @return integer|boolean
     */
    public function getRepeat()
    {
        return $this->repeat;
    }

    /**
     * Get field size
     *
     * @return integer
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * Get field index
     *
     * @return integer
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * Retrieve permissions names for display
     *
     * @return array
     */
    public static function getPermsNames()
    {
        return [
            self::PERM_USER_WRITE => _T("User, read/write"),
            self::PERM_STAFF      => _T("Staff member"),
            self::PERM_ADMIN      => _T("Administrator"),
            self::PERM_MANAGER    => _T("Group manager"),
            self::PERM_USER_READ  => _T("User, read only")
        ];
    }

    /**
     * Retrieve forms names
     *
     * @return array
     */
    public static function getFormsNames()
    {
        return [
            'adh'       => _T("Members"),
            'contrib'   => _T("Contributions"),
            'trans'     => _T("Transactions")
        ];
    }

    /**
     * Retrieve form name
     *
     * @param string $form_name Form name
     *
     * @return string
     */
    public static function getFormTitle($form_name)
    {
        $names = self::getFormsNames();
        return $names[$form_name];
    }

    /**
     * Get permission name
     *
     * @return string
     */
    public function getPermName()
    {
        $perms = self::getPermsNames();
        return $perms[$this->getPerm()];
    }

    /**
     * Get form
     *
     * @return string
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * Get field values
     *
     * @param boolean $imploded Whether to implode values
     *
     * @return array
     */
    public function getValues($imploded = false)
    {
        if (!is_array($this->values)) {
            return false;
        }
        if ($imploded === true) {
            return implode("\n", $this->values);
        } else {
            return $this->values;
        }
    }

    /**
     * Check posted values validity
     *
     * @param array $values All values to check, basically the $_POST array
     *                      after sending the form
     *
     * @return true|array
     */
    public function check($values)
    {
        $this->errors = [];
        $this->warnings = [];

        if (
            (!isset($values['field_name']) || $values['field_name'] == '')
            && get_class($this) != '\Galette\DynamicField\Separator'
        ) {
            $this->errors[] = _T('Missing required field name!');
        } else {
            if ($this->old_name === null && $this->name !== null && $this->name != $values['field_name']) {
                $this->old_name = $this->name;
            }
            $this->name = $values['field_name'];
        }

        if (!isset($values['field_perm']) || $values['field_perm'] === '') {
            $this->errors[] = _T('Missing required field permissions!');
        } else {
            if (in_array($values['field_perm'], array_keys(self::getPermsNames()))) {
                $this->perm = $values['field_perm'];
            } else {
                $this->errors[] = _T('Unknown permission!');
            }
        }

        if ($this->id === null) {
            if (!isset($values['form_name']) || $values['form_name'] == '') {
                $this->errors[] = _T('Missing required form!');
            } else {
                if (in_array($values['form_name'], array_keys(self::getFormsNames()))) {
                    $this->form = $values['form_name'];
                } else {
                    $this->errors[] = _T('Unknown form!');
                }
            }
        }

        $this->required = $values['field_required'] ?? false;

        if (count($this->errors) === 0 && $this->isDuplicate($values['form_name'], $this->name, $this->id)) {
            $this->errors[] = _T("- Field name already used.");
        }

        if ($this->hasWidth() && isset($values['field_width']) && trim($values['field_width']) != '') {
            $this->width = $values['field_width'];
        }

        if ($this->hasHeight() && isset($values['field_height']) && trim($values['field_height']) != '') {
            $this->height = $values['field_height'];
        }

        if ($this->hasSize() && isset($values['field_size']) && trim($values['field_size']) != '') {
            $this->size = $values['field_size'];
        }

        if (isset($values['field_repeat']) && trim($values['field_repeat']) != '') {
            $this->repeat = $values['field_repeat'];
        }

        if ($this->hasFixedValues() && isset($values['fixed_values'])) {
            $fixed_values = [];
            foreach (explode("\n", $values['fixed_values']) as $val) {
                $val = trim($val);
                $len = mb_strlen($val);
                if ($len > 0) {
                    $fixed_values[] = $val;
                    if ($len > $this->size) {
                        if ($this->old_size === null) {
                            $this->old_size = $this->size;
                        }
                        $this->size = $len;
                    }
                }
            }

            $this->values = $fixed_values;
        }

        if ($this->id == null) {
            $this->index = $this->getNewIndex();
        }

        if (count($this->errors) === 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Store the field type
     *
     * @param array $values All values to check, basically the $_POST array
     *                      after sending the form
     *
     * @return boolean
     */
    public function store($values)
    {
        if (!$this->check($values)) {
            return false;
        }

        $isnew = ($this->id === null);
        if ($this->old_name !== null) {
            $this->deleteTranslation($this->old_name);
            $this->addTranslation($this->name);
        }

        try {
            $values = array(
                'field_name'        => strip_tags($this->name),
                'field_perm'        => $this->perm,
                'field_required'    => $this->required,
                'field_width'       => ($this->width === null ? new Expression('NULL') : $this->width),
                'field_height'      => ($this->height === null ? new Expression('NULL') : $this->height),
                'field_size'        => ($this->size === null ? new Expression('NULL') : $this->size),
                'field_repeat'      => ($this->repeat === null ? new Expression('NULL') : $this->repeat),
                'field_form'        => $this->form,
                'field_index'       => $this->index
            );

            if ($this->required === false) {
                //Handle booleans for postgres ; bugs #18899 and #19354
                $values['field_required'] = $this->zdb->isPostgres() ? 'false' : 0;
            }

            if (!$isnew) {
                $update = $this->zdb->update(self::TABLE);
                $update->set($values)->where(
                    self::PK . ' = ' . $this->id
                );
                $this->zdb->execute($update);
            } else {
                $values['field_type'] = $this->getType();
                $insert = $this->zdb->insert(self::TABLE);
                $insert->values($values);
                $this->zdb->execute($insert);

                $this->id = $this->zdb->getLastGeneratedValue($this);

                if ($this->name != '') {
                    $this->addTranslation($this->name);
                }
            }
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred storing field | ' . $e->getMessage(),
                Analog::ERROR
            );
            $this->errors[] = _T("An error occurred storing the field.");
        }

        if (count($this->errors) === 0 && $this->hasFixedValues()) {
            $contents_table = self::getFixedValuesTableName($this->id, true);

            try {
                $this->zdb->drop(str_replace(PREFIX_DB, '', $contents_table), true);
                $field_size = ((int)$this->size > 0) ? $this->size : 1;
                $this->zdb->db->query(
                    'CREATE TABLE ' . $contents_table .
                    ' (id INTEGER NOT NULL,val varchar(' . $field_size .
                    ') NOT NULL)',
                    \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
                );
            } catch (Throwable $e) {
                Analog::log(
                    'Unable to manage fields values table ' .
                    $contents_table . ' | ' . $e->getMessage(),
                    Analog::ERROR
                );
                $this->errors[] = _T("An error occurred creating field values table");
            }

            if (count($this->errors) == 0 && is_array($this->values)) {
                $contents_table = self::getFixedValuesTableName($this->id);
                try {
                    $this->zdb->connection->beginTransaction();

                    $insert = $this->zdb->insert($contents_table);
                    $insert->values(
                        array(
                            'id'    => ':id',
                            'val'   => ':val'
                        )
                    );
                    $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

                    $cnt_values = count($this->values);
                    for ($i = 0; $i < $cnt_values; $i++) {
                        $stmt->execute(
                            array(
                                'id'    => $i,
                                'val'   => $this->values[$i]
                            )
                        );
                    }
                    $this->zdb->connection->commit();
                } catch (Throwable $e) {
                    $this->zdb->connection->rollBack();
                    Analog::log(
                        'Unable to store field ' . $this->id . ' values (' .
                        $e->getMessage() . ')',
                        Analog::ERROR
                    );
                    $this->warnings[] = _T('An error occurred storing dynamic field values :(');
                }
            }
        }

        if (count($this->errors) === 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get new index
     *
     * @return integer
     */
    protected function getNewIndex()
    {
        $select = $this->zdb->select(self::TABLE);
        $select->columns(
            array(
                'idx' => new \Laminas\Db\Sql\Expression('COUNT(*) + 1')
            )
        );
        $select->where(['field_form' => $this->form]);
        $results = $this->zdb->execute($select);
        $result = $results->current();
        $idx = $result->idx;
        return $idx;
    }

    /**
     * Is field duplicated?
     *
     * @return boolean
     */
    public function isDuplicate()
    {
        //let's consider field is duplicated, in case of future errors
        $duplicated = true;
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->columns(
                array(
                    'cnt' => new \Laminas\Db\Sql\Expression('COUNT(' . self::PK . ')')
                )
            )->where(
                array(
                    'field_form' => $this->form,
                    'field_name' => $this->name
                )
            );

            if ($this->id !== null) {
                $select->where->addPredicate(
                    new PredicateExpression(
                        'field_id NOT IN (?)',
                        array($this->id)
                    )
                );
            }

            $results = $this->zdb->execute($select);
            $result = $results->current();
            $dup = $result->cnt;
            if (!$dup > 0) {
                $duplicated = false;
            }
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred checking field duplicity' . $e->getMessage(),
                Analog::ERROR
            );
        }
        return $duplicated;
    }
    /**
     * Move a dynamic field
     *
     * @param string $action What to do (either 'up' or 'down')
     *
     * @return boolean
     */
    public function move($action)
    {
        try {
            $this->zdb->connection->beginTransaction();

            $old_rank = $this->index;

            $direction = $action == 'up' ? -1 : 1;
            $new_rank = $old_rank + $direction;
            $update = $this->zdb->update(self::TABLE);
            $update->set([
                    'field_index' => $old_rank
            ])->where([
                    'field_index'   => $new_rank,
                    'field_form'    => $this->form
            ]);
            $this->zdb->execute($update);

            $update = $this->zdb->update(self::TABLE);
            $update->set(
                array(
                    'field_index' => $new_rank
                )
            )->where(
                array(
                    self::PK        => $this->id
                )
            );
            $this->zdb->execute($update);
            $this->zdb->connection->commit();

            return true;
        } catch (Throwable $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                'Unable to change field ' . $this->id . ' rank | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Delete a dynamic field
     *
     * @return boolean
     */
    public function remove()
    {
        try {
            if ($this->hasFixedValues()) {
                $contents_table = self::getFixedValuesTableName($this->id);
                $this->zdb->drop($contents_table);
            }

            $this->zdb->connection->beginTransaction();
            $old_rank = $this->index;

            $update = $this->zdb->update(self::TABLE);
            $update->set(
                array(
                    'field_index' => new \Laminas\Db\Sql\Expression('field_index-1')
                )
            )->where
                ->greaterThan('field_index', $old_rank)
                ->equalTo('field_form', $this->form);
            $this->zdb->execute($update);

            //remove associated values
            $delete = $this->zdb->delete(DynamicFieldsHandle::TABLE);
            $delete->where(
                array(
                    'field_id'      => $this->id,
                    'field_form'    => $this->form
                )
            );
            $result = $this->zdb->execute($delete);
            if (!$result) {
                throw new \RuntimeException('Unable to remove associated values for field ' . $this->id . '!');
            }

            //remove field type
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where(
                array(
                    'field_id'      => $this->id,
                    'field_form'    => $this->form
                )
            );
            $result = $this->zdb->execute($delete);
            if (!$result) {
                throw new \RuntimeException('Unable to remove field ' . $this->id . '!');
            }

            $this->deleteTranslation($this->name);

            $this->zdb->connection->commit();

            return true;
        } catch (Throwable $e) {
            if ($this->zdb->connection->inTransaction()) {
                //because of DROP autocommit on mysql...
                $this->zdb->connection->rollBack();
            }
            Analog::log(
                'An error occurred deleting field | ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Retrieve fields types names
     *
     * @return array
     */
    public static function getFieldsTypesNames()
    {
        $names = [
            self::SEPARATOR => _T("separator"),
            self::TEXT      => _T("free text"),
            self::LINE      => _T("single line"),
            self::CHOICE    => _T("choice"),
            self::DATE      => _T("date"),
            self::BOOLEAN   => _T("boolean"),
            self::FILE      => _T("file")
        ];
        return $names;
    }

    /**
     * Get errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get warnings
     *
     * @return array
     */
    public function getWarnings()
    {
        return $this->warnings;
    }
}
