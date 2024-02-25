<?php

/**
 * Copyright © 2003-2024 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
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
 */

namespace Galette\DynamicFields;

use ArrayObject;
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
 * @author Johan Cwiklinski <johan@x-tnd.be>
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

    public const MOVE_UP = 'up';
    public const MOVE_DOWN = 'down';

    public const PERM_USER_WRITE = 0;
    public const PERM_ADMIN = 1;
    public const PERM_STAFF = 2;
    public const PERM_MANAGER = 3;
    public const PERM_USER_READ = 4;

    public const DEFAULT_MAX_FILE_SIZE = 1024;
    public const VALUES_FIELD_LENGTH = 100;

    protected bool $has_data = false;
    protected bool $has_width = false;
    protected bool $has_height = false;
    protected bool $has_size = false;
    protected bool $has_min_size = false;
    protected bool $multi_valued = false;
    protected bool $fixed_values = false;
    protected bool $has_permissions = true;

    protected ?int $id = null;
    protected ?int $index = null;
    protected ?int $perm = null;
    protected bool $required = false;
    protected ?int $width = null;
    protected ?int $height = null;
    protected ?int $repeat = null;
    protected ?int $min_size = null;
    protected ?int $size = null;
    protected ?int $old_size = null;
    /** @var string|array<string>|false */
    protected string|array|false $values = false;
    protected string $form;
    protected ?string $information = null;
    protected ?string $name = null;
    protected ?string $old_name = null;

    /** @var array<string> */
    protected array $errors = [];

    protected Db $zdb;

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
        } elseif (is_object($args)) {
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
    public static function loadFieldType(Db $zdb, int $id): DynamicField|false
    {
        try {
            $select = $zdb->select(self::TABLE);
            $select->where(['field_id' => $id]);

            $results = $zdb->execute($select);
            if ($results->count() > 0) {
                /** @var ArrayObject<string, int|string> $result */
                $result = $results->current();
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
     * @param Db       $zdb Database instance
     * @param int      $t   Field type
     * @param int|null $id  Optional dynamic field id (to load data)
     *
     * @return DynamicField
     */
    public static function getFieldType(Db $zdb, int $t, int $id = null): DynamicField
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
    public function load(int $id): void
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->where([self::PK => $id]);

            $results = $this->zdb->execute($select);
            if ($results->count() > 0) {
                /** @var ArrayObject<string, int|string> $result */
                $result = $results->current();
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
     * @param ArrayObject<string, int|string> $rs     ResultSet
     * @param bool                            $values Whether to load values. Defaults to true
     *
     * @return void
     */
    public function loadFromRs(ArrayObject $rs, bool $values = true): void
    {
        $this->id = (int)$rs->field_id;
        $this->name = $rs->field_name;
        $this->index = (int)$rs->field_index;
        $this->perm = (int)$rs->field_perm;
        $this->required = $rs->field_required == 1;
        $this->min_size = $rs->field_min_size;
        $this->width = $rs->field_width;
        $this->height = $rs->field_height;
        $this->repeat = (int)$rs->field_repeat;
        $this->size = $rs->field_size;
        $this->form = $rs->field_form;
        $this->information = $rs->field_information;
        if ($values && $this->hasFixedValues()) {
            $this->loadFixedValues();
        }
    }

    /**
     * Retrieve fixed values table name
     *
     * @param integer $id       Field ID
     * @param bool    $prefixed Whether table name should be prefixed
     *
     * @return string
     */
    public static function getFixedValuesTableName(int $id, bool $prefixed = false): string
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
    private function loadFixedValues(): void
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
            if ($results->count() > 0) {
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
    abstract public function getType(): int;

    /**
     * Get field type name
     *
     * @return String
     */
    public function getTypeName(): string
    {
        $types = $this->getFieldsTypesNames();
        if (isset($types[$this->getType()])) {
            return $types[$this->getType()];
        } else {
            throw new \RuntimeException(
                'Unknown type ' . $this->getType()
            );
        }
    }

    /**
     * Does the field handle data?
     *
     * @return bool
     */
    public function hasData(): bool
    {
        return $this->has_data;
    }

    /**
     * Does the field has width?
     *
     * @return bool
     */
    public function hasWidth(): bool
    {
        return $this->has_width;
    }

    /**
     * Does the field has height?
     *
     * @return bool
     */
    public function hasHeight(): bool
    {
        return $this->has_height;
    }

    /**
     * Does the field has min size?
     *
     * @return bool
     */
    public function hasMinSize(): bool
    {
        return $this->has_min_size;
    }

    /**
     * Does the field has a size?
     *
     * @return bool
     */
    public function hasSize(): bool
    {
        return $this->has_size;
    }

    /**
     * Is the field multivalued?
     *
     * @return bool
     */
    public function isMultiValued(): bool
    {
        return $this->multi_valued;
    }

    /**
     * Does the field has fixed values?
     *
     * @return bool
     */
    public function hasFixedValues(): bool
    {
        return $this->fixed_values;
    }

    /**
     * Does the field require permissions?
     *
     * @return bool
     */
    public function hasPermissions(): bool
    {
        return $this->has_permissions;
    }

    /**
     * Get field id
     *
     * @return integer|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get field Permissions
     *
     * @return integer|null
     */
    public function getPerm(): ?int
    {
        return $this->perm;
    }

    /**
     * Is field required?
     *
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Get field width
     *
     * @return integer|null
     */
    public function getWidth(): ?int
    {
        return $this->width;
    }

    /**
     * Get field height
     *
     * @return integer|null
     */
    public function getHeight(): ?int
    {
        return $this->height;
    }

    /**
     * Is current field repeatable?
     *
     * @return bool
     */
    public function isRepeatable(): bool
    {
        return $this->repeat != null && $this->repeat >= 0;
    }

    /**
     * Get fields repetitions
     *
     * @return integer|null
     */
    public function getRepeat(): ?int
    {
        return $this->repeat;
    }

    /**
     * Get field min size
     *
     * @return integer|null
     */
    public function getMinSize(): ?int
    {
        return $this->min_size;
    }

    /**
     * Get field size
     *
     * @return integer|null
     */
    public function getSize(): ?int
    {
        return $this->size;
    }

    /**
     * Get field index
     *
     * @return integer|null
     */
    public function getIndex(): ?int
    {
        return $this->index;
    }

    /**
     * Get field information
     *
     * @return string
     */
    public function getInformation(): string
    {
        return $this->information ?? '';
    }

    /**
     * Retrieve permissions names for display
     *
     * @return array<int,string>
     */
    public static function getPermsNames(): array
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
     * @return array<string,string>
     */
    public static function getFormsNames(): array
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
    public static function getFormTitle(string $form_name): string
    {
        $names = self::getFormsNames();
        return $names[$form_name];
    }

    /**
     * Get permission name
     *
     * @return string
     */
    public function getPermName(): string
    {
        $perms = self::getPermsNames();
        return $perms[$this->getPerm()];
    }

    /**
     * Get form
     *
     * @return string
     */
    public function getForm(): string
    {
        return $this->form;
    }

    /**
     * Get field values
     *
     * @param bool $imploded Whether to implode values
     *
     * @return array<string>|string|false
     */
    public function getValues(bool $imploded = false): array|string|false
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
     * @param array<string,mixed> $values All values to check, basically the $_POST array
     *                                    after sending the form
     *
     * @return bool
     */
    public function check(array $values): bool
    {
        $this->errors = [];
        $this->warnings = [];

        if (
            (!isset($values['field_name']) || $values['field_name'] == '')
            && !$this instanceof Separator
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

        if (!isset($this->id)) {
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

        if (count($this->errors) === 0 && $this->isDuplicate()) {
            $this->errors[] = _T("- Field name already used.");
        }

        if ($this->hasWidth() && isset($values['field_width']) && trim($values['field_width']) != '') {
            if (!is_numeric($values['field_width']) || $values['field_width'] <= 0) {
                $this->errors[] = _T("- Width must be a positive integer!");
            } else {
                $this->width = $values['field_width'];
            }
        }

        if ($this->hasHeight() && isset($values['field_height']) && trim($values['field_height']) != '') {
            if (!is_numeric($values['field_height']) || $values['field_height'] <= 0) {
                $this->errors[] = _T("- Height must be a positive integer!");
            } else {
                $this->height = $values['field_height'];
            }
        }

        if ($this->hasSize() && isset($values['field_size']) && trim($values['field_size']) != '') {
            if (!is_numeric($values['field_size']) || $values['field_size'] <= 0) {
                $this->errors[] = _T("- Size must be a positive integer!");
            } else {
                $this->size = $values['field_size'];
            }
        }

        if ($this->hasMinSize() && isset($values['field_min_size']) && trim($values['field_min_size']) != '') {
            if (!is_numeric($values['field_min_size']) || $values['field_min_size'] <= 0) {
                $this->errors[] = _T("- Min size must be a positive integer!");
            } else {
                $this->min_size = $values['field_min_size'];
            }
        }

        if (
            $this->hasMinSize()
                && $this->min_size !== null
            && $this->hasSize()
                && $this->size !== null
        ) {
            if ($this->min_size > $this->size) {
                $this->errors[] = _T("- Min size must be lower than size!");
            }
        }

        if (isset($values['field_repeat']) && trim($values['field_repeat']) != '') {
            if (!is_numeric($values['field_repeat'])) {
                $this->errors[] = _T("- Repeat must be an integer!");
            } else {
                $this->repeat = $values['field_repeat'];
            }
        }

        if (isset($values['field_information']) && trim($values['field_information']) != '') {
            global $preferences;
            $this->information = $preferences->cleanHtmlValue($values['field_information']);
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

        if (!isset($this->id)) {
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
     * @param array<string,mixed> $values All values to check, basically the $_POST array
     *                                    after sending the form
     *
     * @return bool
     */
    public function store(array $values): bool
    {
        if (!$this->check($values)) {
            return false;
        }

        $isnew = (!isset($this->id));
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
                'field_min_size'    => ($this->min_size === null ? new Expression('NULL') : $this->min_size),
                'field_size'        => ($this->size === null ? new Expression('NULL') : $this->size),
                'field_repeat'      => ($this->repeat === null ? new Expression('NULL') : $this->repeat),
                'field_form'        => $this->form,
                'field_index'       => $this->index,
                'field_information' => ($this->information === null ? new Expression('NULL') : $this->information)
            );

            if ($this->required === false) {
                //Handle booleans for postgres ; bugs #18899 and #19354
                $values['field_required'] = $this->zdb->isPostgres() ? 'false' : 0;
            }

            if (!$isnew) {
                $update = $this->zdb->update(self::TABLE);
                $update->set($values)->where([self::PK => $this->id]);
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
    protected function getNewIndex(): int
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
        return (int)$idx;
    }

    /**
     * Is field duplicated?
     *
     * @return bool
     */
    public function isDuplicate(): bool
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

            if (isset($this->id)) {
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
            throw $e;
        }
        return $duplicated;
    }

    /**
     * Move a dynamic field
     *
     * @param string $action What to do (one of self::MOVE_*)
     *
     * @return bool
     */
    public function move(string $action): bool
    {
        if ($action !== self::MOVE_UP && $action !== self::MOVE_DOWN) {
            throw new \RuntimeException(('Unknown action ' . $action));
        }

        try {
            $this->zdb->connection->beginTransaction();

            $old_rank = $this->index;

            $direction = $action == self::MOVE_UP ? -1 : 1;
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
                    self::PK => $this->id
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
     * @return bool
     */
    public function remove(): bool
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
            try {
                $delete = $this->zdb->delete(DynamicFieldsHandle::TABLE);
                $delete->where(
                    array(
                        'field_id'      => $this->id,
                        'field_form'    => $this->form
                    )
                );
                $this->zdb->execute($delete);
            } catch (Throwable $e) {
                throw new \RuntimeException('Unable to remove associated values for field ' . $this->id . '!');
            }

            //remove field type
            try {
                $delete = $this->zdb->delete(self::TABLE);
                $delete->where(
                    array(
                        'field_id'      => $this->id,
                        'field_form'    => $this->form
                    )
                );
                $this->zdb->execute($delete);
            } catch (Throwable $e) {
                throw new \RuntimeException('Unable to remove field type ' . $this->id . '!');
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
     * @return array<int, string>
     */
    public static function getFieldsTypesNames(): array
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
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get warnings
     *
     * @return array<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
