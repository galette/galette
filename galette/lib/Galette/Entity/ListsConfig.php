<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Entity;

use ArrayObject;
use Throwable;
use Analog\Analog;
use Galette\Core\Logs;
use Galette\Core\Login;
use Galette\Core\Authentication;
use Galette\DynamicFields\DynamicField;
use Galette\DynamicFields\File;
use Galette\DynamicFields\Text;

use function Safe\preg_match;
use function Safe\preg_replace;

/**
 * Lists config class for galette:
 * defines fields order and visibility
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ListsConfig extends FieldsConfig
{
    public const string DYNAMIC_PREFIX = 'dyn_';
    /** Displayed length of dynamic fields values in lists */
    public const int DYNAMIC_VALUE_LENGTH = 20;

    /** @var array<int,array<string,mixed>> */
    protected array $listed_fields = [];
    /** @var array<string,int> Stored dynamic fields list positions, by field id */
    private array $dynamic_positions = [];
    /** @var ?array<int,DynamicField> Members dynamic fields that can be listed, by id */
    private ?array $dynamic_fields = null;

    /**
     * Fields that are not part of lists
     *
     * @var array<string>
     */
    private array $non_list_elements = [
        'mdp_adh',
        'photo',
        'info_adh',
        'info_public_adh',
        'nom_adh',
        'prenom_adh'
    ];

    /**
     * ACL mapping for list elements not present in form configuration
     *
     * @var array<string,string>
     */
    private array $acl_mapping = [
        'list_adh_name'             => 'nom_adh',
        'list_adh_contribstatus'    => 'id_statut'
    ];

    /**
     * Load current fields configuration from database.
     */
    public function load(): bool
    {
        $this->dynamic_positions = [];
        $this->dynamic_fields = null;
        return parent::load();
    }

    /**
     * Keep list position of stored dynamic fields
     *
     * @param ArrayObject<string, int|string> $rset DB ResultSet row
     */
    protected function loadExtraField(ArrayObject $rset): void
    {
        if (self::getDynamicId((string)$rset->field_id) !== null) {
            $this->dynamic_positions[(string)$rset->field_id] = (int)$rset->list_position;
        }
    }

    /**
     * Prepare a field (required data, automation)
     *
     * @param ArrayObject<string, int|string> $rset DB ResultSet row
     *
     * @return array<string, mixed>
     */
    protected function buildField(ArrayObject $rset): array
    {
        $f = parent::buildField($rset);
        $f['list_position'] = (int)$rset->list_position;
        $f['list_visible'] = ($f['list_position'] >= 0);
        $f['width_in_forms'] = (int)$rset->width_in_forms;
        return $f;
    }

    /**
     * Create field array configuration
     * Several lists of fields are kept (visible, required, etc), build them.
     */
    protected function buildLists(): void
    {
        //Specific list fields does not have rights; fix this from mapping
        //Cannot be done preparing fields, cannot be sure of the order it is processed
        foreach ($this->acl_mapping as $list_key => $field_key) {
            $this->core_db_fields[$list_key]['visible'] = $this->core_db_fields[$field_key]['visible'];
        }

        //handle parent field: is always inactive on form. Hardcode to STAFF.
        if (isset($this->core_db_fields['parent_id'])) {
            $this->core_db_fields['parent_id']['visible'] = self::STAFF;
        }

        $this->listed_fields = [];
        parent::buildLists();

        if (count(array_filter($this->dynamic_positions, fn($pos) => $pos >= 0))) {
            $dynamic_fields = $this->getDynamicFields();
            foreach ($this->dynamic_positions as $field_id => $pos) {
                $dynamic_id = self::getDynamicId($field_id);
                if ($pos < 0 || !isset($dynamic_fields[$dynamic_id])) {
                    //not listed, or field has been removed
                    continue;
                }
                while (isset($this->listed_fields[$pos])) {
                    ++$pos;
                }
                $this->listed_fields[$pos] = $this->buildDynamicField($dynamic_fields[$dynamic_id], $pos);
            }
        }

        //make sure array order is the same as in the database, since query is ordered differently
        ksort($this->listed_fields);
    }

    /**
     * Get dynamic field id from a list field id
     *
     * @param string $field_id List field id
     */
    public static function getDynamicId(string $field_id): ?int
    {
        if (preg_match('/^' . self::DYNAMIC_PREFIX . '(\d+)$/', $field_id, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    /**
     * Is a dynamic field possible in lists?
     *
     * @param DynamicField $field Dynamic field
     */
    public static function isListable(DynamicField $field): bool
    {
        return $field->hasData() && !$field instanceof File;
    }

    /**
     * Can lists be ordered on a dynamic field?
     *
     * @param DynamicField $field Dynamic field
     */
    public static function isSortable(DynamicField $field): bool
    {
        return !$field->isRepeatable() && !$field instanceof Text;
    }

    /**
     * Get members dynamic fields that can be listed, whatever their permissions
     *
     * @return array<int,DynamicField>
     */
    private function getDynamicFields(): array
    {
        if ($this->dynamic_fields === null) {
            $this->dynamic_fields = [];
            $select = $this->zdb->select(DynamicField::TABLE);
            $select
                ->where(['field_form' => 'adh'])
                ->order('field_index');

            foreach ($this->zdb->execute($select) as $r) {
                /** @var ArrayObject<string, int|string> $r */
                $df = DynamicField::getFieldType($this->zdb, (int)$r['field_type']);
                $df->loadFromRS($r);
                if (self::isListable($df)) {
                    $this->dynamic_fields[(int)$df->getId()] = $df;
                }
            }
        }
        return $this->dynamic_fields;
    }

    /**
     * Build list field from a dynamic field
     *
     * @param DynamicField $field    Dynamic field
     * @param int          $position List position, -1 when not listed
     *
     * @return array<string,mixed>
     */
    private function buildDynamicField(DynamicField $field, int $position): array
    {
        $field_id = self::DYNAMIC_PREFIX . $field->getId();
        return [
            'field_id'       => $field_id,
            'label'          => $field->getName(),
            'propname'       => $field_id,
            'visible'        => $field->getPermission(),
            'list_position'  => $position,
            'list_visible'   => $position >= 0,
            'width_in_forms' => 1,
            'is_dynamic'     => true,
            'dynamic_id'     => $field->getId(),
            'sortable'       => self::isSortable($field),
            'field'          => $field
        ];
    }

    /**
     * Adds a field to lists
     *
     * @param array<string,mixed> $field Field values
     */
    protected function addToLists(array $field): void
    {
        if (in_array($field['field_id'], $this->non_list_elements)) {
            return;
        }
        parent::addToLists($field);

        if ($field['list_visible'] ?? false) {
            $this->listed_fields[(int)$field['list_position']] = $field;
        }
    }

    /**
     * Retrieve display elements
     *
     * @param Login $login Login instance
     *
     * @return array<int,object>
     */
    public function getDisplayElements(Login $login): array
    {
        $display_elements = [];
        $access_level = $login->getAccessLevel();
        try {
            $elements = $this->listed_fields;

            foreach ($elements as $elt) {
                $o = (object)$elt;
                $this->handleLabel($o);

                if ($o->is_dynamic ?? false) {
                    if ($o->field->isAccessible($access_level)) {
                        $display_elements[] = $o;
                    }
                } elseif ($o->field_id == 'id_adh') {
                    // ignore access control, as member ID is always needed
                    $o->type = self::TYPE_STR;
                    $display_elements[] = $o;
                } else {
                    // skip fields blacklisted for display
                    if (in_array($o->field_id, $this->non_list_elements)) {
                        continue;
                    }

                    // skip fields according to access control
                    if (
                        $o->visible == self::NOBODY
                        || ($o->visible == self::ADMIN
                            && $access_level < Authentication::ACCESS_ADMIN)
                        || ($o->visible == self::STAFF
                            && $access_level < Authentication::ACCESS_STAFF)
                        || ($o->visible == self::MANAGER
                            && $access_level < Authentication::ACCESS_MANAGER)
                    ) {
                        continue;
                    }
                    $display_elements[] = $o;
                }
            }

            return $display_elements;
        } catch (Throwable $e) {
            Logs::exception($e, 'An error occurred getting list elements to display');
            throw $e;
        }
    }

    /**
     * Handle list labels
     *
     * @param object $field Field data
     */
    private function handleLabel(object $field): void
    {
        switch ($field->field_id) {
            case 'bool_admin_adh':
                $field->label = __('Is admin');
                break;
            case 'date_modif_adh':
                $field->label = _T('Modified');
                break;
            case 'tel_adh':
                $field->label = _T('Phone');
                break;
            case 'bool_display_info':
                $field->label = _T('Public');
                break;
        }

        $field->label = trim(str_replace('&nbsp;', ' ', $field->label));
        $field->label = preg_replace('/\s?:$/', '', $field->label);
    }

    /**
     * Get all fields for list
     *
     * @return array<int,array<string,mixed>>
     */
    public function getListedFields(): array
    {
        return $this->listed_fields;
    }

    /**
     * Get remaining free fields for list
     *
     * @return array<string,array<string,mixed>>
     */
    public function getRemainingFields(): array
    {
        $db_fields = $this->core_db_fields;

        //remove non list
        foreach ($this->non_list_elements as $todrop) {
            unset($db_fields[$todrop]);
        }

        //remove already listed
        foreach ($this->listed_fields as $listed) {
            unset($db_fields[$listed['field_id']]);
        }

        $remainings = [];
        foreach ($db_fields as $key => $db_field) {
            $remainings[$key] = $db_field;
        }

        foreach ($this->getDynamicFields() as $field) {
            $remaining = $this->buildDynamicField($field, -1);
            if (!$this->isListed($remaining['field_id'])) {
                $remainings[$remaining['field_id']] = $remaining;
            }
        }

        return $remainings;
    }

    /**
     * Is a field in current list?
     *
     * @param string $field_id Field id
     */
    private function isListed(string $field_id): bool
    {
        foreach ($this->listed_fields as $listed) {
            if ($listed['field_id'] === $field_id) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get field configuration
     *
     * @param string $name Field name
     *
     * @return array<string, mixed>
     */
    public function getField(string $name): array
    {
        $dynamic_id = self::getDynamicId($name);
        if ($dynamic_id === null) {
            return parent::getField($name);
        }

        $dynamic_fields = $this->getDynamicFields();
        if (!isset($dynamic_fields[$dynamic_id])) {
            throw new \UnexpectedValueException("$name field does not exists");
        }
        foreach ($this->listed_fields as $listed) {
            if ($listed['field_id'] === $name) {
                return $listed;
            }
        }
        return $this->buildDynamicField($dynamic_fields[$dynamic_id], -1);
    }

    /**
     * Set fields
     *
     * @param array<int,array<string,mixed>> $fields categorized fields array
     */
    public function setListFields(array $fields): bool
    {
        $this->listed_fields = $fields;
        return $this->storeList();
    }

    /**
     * Store list config in database
     */
    private function storeList(): bool
    {
        $class = static::class;

        try {
            if (!count($this->listed_fields)) {
                throw new \RuntimeException('No fields for list, aborting.');
            }

            $this->zdb->beginTransaction();

            $update = $this->zdb->update(self::TABLE);
            $update->set(
                [
                    'list_visible'          => ':list_visible',
                    'list_position'         => ':list_position',
                    'width_in_forms'        => ':width_in_forms'
                ]
            )->where(
                [
                    'field_id'      => ':field_id',
                    'table_name'    => $this->table
                ]
            );
            $stmt = $this->zdb->sql->prepareStatementForSqlObject($update);


            $dynamics = [];
            foreach ($this->listed_fields as $pos => $field) {
                if ($field['is_dynamic'] ?? false) {
                    //dynamic fields only have a row while they are listed
                    $dynamics[] = [
                        'field_id'      => $field['field_id'],
                        'table_name'    => $this->table,
                        'required'      => false,
                        'visible'       => self::NOBODY,
                        'category'      => $this->getField('id_adh')['category'],
                        'position'      => -1,
                        'list_visible'  => true,
                        'list_position' => $pos
                    ];
                    continue;
                }
                $params = [
                    'list_visible'   => $field['list_visible'],
                    'list_position'  => $pos,
                    'field_id'       => $field['field_id'],
                    'width_in_forms' => $field['width_in_forms']
                ];
                $stmt->execute($params);
            }

            foreach ($this->getRemainingFields() as $field => $remaining) {
                if ($remaining['is_dynamic'] ?? false) {
                    continue;
                }
                $params = [
                    'list_visible'  => false,
                    'list_position' => -1,
                    'field_id'      => $field
                ];
                $stmt->execute($params);
            }

            if (count($this->dynamic_positions)) {
                $delete = $this->zdb->delete(self::TABLE);
                $delete->where(['table_name' => $this->table]);
                $delete->where->in('field_id', array_keys($this->dynamic_positions));
                $this->zdb->execute($delete);
            }
            if (count($dynamics)) {
                $this->insert($dynamics);
            }

            Analog::log(
                str_replace(
                    '%s',
                    $this->table,
                    '[' . $class . '] List configuration for table %s stored '
                    . 'successfully.'
                ),
                Analog::INFO
            );

            $this->zdb->commit();
            return $this->load();
        } catch (Throwable $e) {
            $this->zdb->rollback();
            Logs::exception(
                $e,
                '[' . $class . '] An error occurred while saving list '
                . 'configuration for table `' . $this->table . '`.'
            );
            throw $e;
        }
    }

    /**
     * Get ACL mapping for list elements not present in form configuration
     *
     * @return array<string,string>
     */
    public function getAclMapping(): array
    {
        return $this->acl_mapping;
    }

    /**
     * Get visibility for specified field
     *
     * @param string $field The requested field
     */
    public function getVisibility(string $field): int
    {
        if (in_array($field, $this->non_list_elements)) {
            return self::NOBODY;
        }
        $dynamic_id = self::getDynamicId($field);
        if ($dynamic_id !== null) {
            $dynamic_fields = $this->getDynamicFields();
            return isset($dynamic_fields[$dynamic_id])
                ? (int)$dynamic_fields[$dynamic_id]->getPermission() : self::NOBODY;
        }
        return $this->all_visibles[$field];
    }
}
