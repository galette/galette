<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Dynamic fields handle, aggregating field descriptors and values
 *
 * PHP version 5
 *
 * Copyright © 2011-2023 The Galette Team
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
 * @copyright 2011-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-06-20
 */

namespace Galette\Entity;

use Throwable;
use Analog\Analog;
use Laminas\Db\Adapter\Driver\StatementInterface;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Core\Authentication;
use Galette\DynamicFields\Separator;
use Galette\DynamicFields\Text;
use Galette\DynamicFields\Line;
use Galette\DynamicFields\Choice;
use Galette\DynamicFields\Date;
use Galette\DynamicFields\Boolean;
use Galette\DynamicFields\File;
use Galette\DynamicFields\DynamicField;
use Galette\Repository\DynamicFieldsSet;

/**
 * Dynamic fields handle, aggregating field descriptors and values
 *
 * @name DynamicFieldsHandle
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

class DynamicFieldsHandle
{
    public const TABLE = 'dynamic_fields';

    private $dynamic_fields = [];
    private $current_values = [];
    private $form_name;
    private $item_id;

    private $errors = array();

    private $zdb;
    private $login;

    private $insert_stmt;
    private $update_stmt;
    private $delete_stmt;

    private $has_changed = false;

    /**
     * Default constructor
     *
     * @param Db    $zdb      Database instance
     * @param Login $login    Login instance
     * @param mixed $instance Object instance
     */
    public function __construct(Db $zdb, Login $login, $instance = null)
    {
        $this->zdb = $zdb;
        $this->login = $login;
        if ($instance !== null) {
            $this->load($instance);
        }
    }

    /**
     * Load dynamic fields values for specified object
     *
     * @param mixed $object Object instance
     *
     * @return array|false
     */
    public function load($object)
    {
        switch (get_class($object)) {
            case 'Galette\Entity\Adherent':
                $this->form_name = 'adh';
                break;
            case 'Galette\Entity\Contribution':
                $this->form_name = 'contrib';
                break;
            case 'Galette\Entity\Transaction':
                $this->form_name = 'trans';
                break;
            default:
                throw new \RuntimeException('Class ' . get_class($object) . ' does not handle dynamic fields!');
                break;
        }

        try {
            $this->item_id = $object->id;
            $fields = new DynamicFieldsSet($this->zdb, $this->login);
            $this->dynamic_fields = $fields->getList($this->form_name);

            $results = $this->getCurrentFields();

            if ($results->count() > 0) {
                foreach ($results as $f) {
                    if (isset($this->dynamic_fields[$f->{DynamicField::PK}])) {
                        $field = $this->dynamic_fields[$f->{DynamicField::PK}];
                        if ($field->hasFixedValues()) {
                            $choices = $field->getValues();
                            $f->text_val = $choices[$f->field_val];
                        }
                        $this->current_values[$f->{DynamicField::PK}][] = array_filter(
                            (array)$f,
                            static function ($k) {
                                return $k != DynamicField::PK;
                            },
                            ARRAY_FILTER_USE_KEY
                        );
                    } else {
                        Analog::log(
                            'Dynamic values found for ' . get_class($object) . ' #' . $this->item_id .
                            '; but no dynamic field configured!',
                            Analog::WARNING
                        );
                    }
                }
                return true;
            } else {
                return false;
            }
        } catch (Throwable $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Get errors
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get fields
     *
     * @return array
     */
    public function getFields(): array
    {
        return $this->dynamic_fields;
    }

    /**
     * Get values
     *
     * @param integer $field Field ID
     *
     * @return array
     */
    public function getValues($field): array
    {
        if (!isset($this->current_values[$field])) {
            $this->current_values[$field][] = [
                'item_id'       => $this->item_id,
                'field_form'    => $this->dynamic_fields[$field]->getForm(),
                'val_index'     => 1,
                'field_val'     => '',
                'is_new'        => true
            ];
        }
        return $this->current_values[$field];
    }

    /**
     * Set field value
     *
     * @param integer $item  Item ID
     * @param integer $field Field ID
     * @param integer $index Value index
     * @param mixed   $value Value
     *
     * @return void
     */
    public function setValue($item, $field, $index, $value)
    {
        $idx = $index - 1;
        $input = [
            'item_id'       => $item,
            'field_form'    => $this->dynamic_fields[$field]->getForm(),
            'val_index'     => $index,
            'field_val'     => $value,
        ];

        if (!isset($this->current_values[$field][$idx])) {
            $input['is_new'] = true;
        }

        $this->current_values[$field][$idx] = $input;
    }

    /**
     * Unset field value
     *
     * @param integer $item  Item ID
     * @param integer $field Field ID
     * @param integer $index Value index
     *
     * @return void
     */
    public function unsetValue($item, $field, $index)
    {
        $idx = $index - 1;
        if (isset($this->current_values[$field][$idx])) {
            unset($this->current_values[$field][$idx]);
        }
    }

    /**
     * Store values
     *
     * @param integer $item_id     Curent item id to use (will be used if current item_id is 0)
     * @param boolean $transaction True if a transaction already exists
     *
     * @return boolean
     */
    public function storeValues($item_id = null, $transaction = false)
    {
        try {
            if ($item_id !== null && ($this->item_id == null || $this->item_id == 0)) {
                $this->item_id = $item_id;
            }
            if (!$transaction) {
                $this->zdb->connection->beginTransaction();
            }

            $this->handleRemovals();

            foreach ($this->current_values as $field_id => $values) {
                foreach ($values as $value) {
                    $value[DynamicField::PK] = $field_id;
                    if ($value['item_id'] == 0) {
                        $value['item_id'] = $this->item_id;
                    }

                    if (isset($value['is_new'])) {
                        unset($value['is_new']);
                        $this->getInsertStatement()->execute($value);
                        $this->has_changed = true;
                    } else {
                        $params = [
                            'field_val' => $value['field_val'],
                            'val_index' => $value['val_index'],
                            'item_id'   => $value['item_id'],
                            'field_id'  => $value['field_id'],
                            'field_form' => $value['field_form'],
                            'old_val_index' => $value['old_val_index'] ?? $value['val_index'] //:old_val_index
                        ];
                        $this->getUpdateStatement()->execute($params);
                        $this->has_changed = true;
                    }
                }
            }

            if (!$transaction) {
                $this->zdb->connection->commit();
            }
            return true;
        } catch (Throwable $e) {
            if (!$transaction) {
                $this->zdb->connection->rollBack();
            }
            Analog::log(
                'An error occurred storing dynamic field. Form name: ' . $this->form_name .
                ' | Error was: ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        } finally {
            unset(
                $this->update_stmt,
                $this->insert_stmt
            );
        }
    }

    /**
     * Get (and prepare if not done yet) insert statement
     *
     * @return StatementInterface
     */
    private function getInsertStatement(): StatementInterface
    {
        if (!isset($this->insert_stmt)) {
            $insert = $this->zdb->insert(self::TABLE);
            $insert->values([
                'item_id'       => ':item_id',
                'field_id'      => ':field_id',
                'field_form'    => ':field_form',
                'val_index'     => ':val_index',
                'field_val'     => ':field_val'
            ]);
            $this->insert_stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);
        }
        return $this->insert_stmt;
    }

    /**
     * Get (and prepare if not done yet) update statement
     *
     * @return StatementInterface
     */
    private function getUpdateStatement(): StatementInterface
    {
        if (!isset($this->update_stmt)) {
            $update = $this->zdb->update(self::TABLE);
            $update->set([
                'field_val'     => ':field_val',
                'val_index'     => ':val_index'
            ])->where([
                'item_id'       => ':item_id',
                'field_id'      => ':field_id',
                'field_form'    => ':field_form',
                'val_index'     => ':old_val_index'
            ]);
            $this->update_stmt = $this->zdb->sql->prepareStatementForSqlObject($update);
        }
        return $this->update_stmt;
    }

    /**
     * Handle values that have been removed
     *
     * @return void
     */
    private function handleRemovals()
    {
        $fields = new DynamicFieldsSet($this->zdb, $this->login);
        $this->dynamic_fields = $fields->getList($this->form_name, $this->login);

        $results = $this->getCurrentFields();

        $fromdb = [];
        if ($results->count() > 0) {
            foreach ($results as $result) {
                $fromdb[$result->field_id . '_' . $result->val_index] = [
                    'item_id'       => $this->item_id,
                    'field_form'    => $this->form_name,
                    'field_id'      => $result->field_id,
                    'val_index'     => $result->val_index
                ];
            }
        }

        if (!count($fromdb)) {
            //no entry in database, nothing to do.
            return;
        }

        foreach ($this->current_values as $field_id => $values) {
            foreach ($values as $value) {
                $key = $field_id . '_' . $value['val_index'];
                if (isset($fromdb[$key])) {
                    unset($fromdb[$key]);
                }
            }
        }

        if (count($fromdb)) {
            foreach ($fromdb as $entry) {
                if ($this->delete_stmt === null) {
                    $delete = $this->zdb->delete(self::TABLE);
                    $delete->where([
                        'item_id'       => ':item_id',
                        'field_form'    => ':field_form',
                        'field_id'      => ':field_id',
                        'val_index'     => ':val_index'
                    ]);
                    $this->delete_stmt = $this->zdb->sql->prepareStatementForSqlObject($delete);
                }
                $this->delete_stmt->execute($entry);
                //update val index
                $field_id = $entry['field_id'];
                if (
                    isset($this->current_values[$field_id])
                    && count($this->current_values[$field_id])
                ) {
                    $val_index = (int)$entry['val_index'];
                    foreach ($this->current_values[$field_id] as &$current) {
                        if ((int)$current['val_index'] === $val_index + 1) {
                            $current['val_index'] = $val_index;
                            ++$val_index;
                            $current['old_val_index'] = $val_index;
                        }
                    }
                }
            }
            $this->has_changed = true;
        }
    }

    /**
     * Is there any change in dynamic fields?
     *
     * @return boolean
     */
    public function hasChanged()
    {
        return $this->has_changed;
    }

    /**
     * Remove values
     *
     * @param integer $item_id     Curent item id to use (will be used if current item_id is 0)
     * @param boolean $transaction True if a transaction already exists
     *
     * @return boolean
     */
    public function removeValues($item_id = null, $transaction = false)
    {
        try {
            if ($item_id !== null && ($this->item_id == null || $this->item_id == 0)) {
                $this->item_id = $item_id;
            }
            if (!$transaction) {
                $this->zdb->connection->beginTransaction();
            }

            $delete = $this->zdb->delete(self::TABLE);
            $delete->where(
                array(
                    'item_id'       => $this->item_id,
                    'field_form'    => $this->form_name
                )
            );
            $this->zdb->execute($delete);

            if (!$transaction) {
                $this->zdb->connection->commit();
            }
            return true;
        } catch (Throwable $e) {
            if (!$transaction) {
                $this->zdb->connection->rollBack();
            }
            Analog::log(
                'An error occurred removing dynamic field. Form name: ' . $this->form_name .
                ' | Error was: ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Get current fields resultset
     *
     * @return ResulSet
     */
    protected function getCurrentFields()
    {
        $select = $this->zdb->select(self::TABLE, 'd');
        $select->join(
            array('t' => PREFIX_DB . DynamicField::TABLE),
            'd.' . DynamicField::PK . '=t.' . DynamicField::PK,
            array('field_id')
        )->where(
            array(
                'item_id'       => $this->item_id,
                'd.field_form'  => $this->form_name
            )
        );

        /** only load values for accessible fields*/
        $accessible_fields = [];
        $access_level = $this->login->getAccessLevel();

        foreach ($this->dynamic_fields as $field) {
            $perm = $field->getPerm();
            if (
                ($perm == DynamicField::PERM_MANAGER &&
                    $access_level < Authentication::ACCESS_MANAGER) ||
                ($perm == DynamicField::PERM_STAFF &&
                        $access_level < Authentication::ACCESS_STAFF) ||
                ($perm == DynamicField::PERM_ADMIN &&
                    $access_level < Authentication::ACCESS_ADMIN)
            ) {
                continue;
            }
            $accessible_fields[] = $field->getId();
        }

        if (count($accessible_fields)) {
            $select->where->in('d.' . DynamicField::PK, $accessible_fields);
        }

        $results = $this->zdb->execute($select);
        return $results;
    }
}
