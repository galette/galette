<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Dynamics fields trait
 *
 * PHP version 5
 *
 * Copyright © 2017-2024 The Galette Team
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
 *
 * @category  Features
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.9dev - 2017-05-26
 */

namespace Galette\Features;

use Galette\Entity\Adherent;
use Galette\Repository\DynamicFieldsSet;
use Throwable;
use Analog\Analog;
use Galette\DynamicFields\File;
use Galette\DynamicFields\Date;
use Galette\DynamicFields\Boolean;
use Galette\Entity\DynamicFieldsHandle;

/**
 * Dynamics fields trait
 *
 * @category  Features
 * @name      Dynamics
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.9dev - 2017-05-26
 */

trait Dynamics
{
    use Dependencies;

    /** @var string */
    protected string $name_pattern = 'info_field_';

    /** @var DynamicFieldsHandle */
    protected DynamicFieldsHandle $dynamics;

    /**
     * Load dynamic fields for member
     *
     * @return void
     */
    private function loadDynamicFields(): void
    {
        if (!property_exists($this, 'login')) {
            global $login;
        } else {
            $login = $this->login;
        }
        $this->dynamics = new DynamicFieldsHandle($this->zdb, $login, $this);
    }

    /**
     * Get dynamic fields
     *
     * @return DynamicFieldsHandle
     */
    public function getDynamicFields(): DynamicFieldsHandle
    {
        if (empty($this->dynamics)) {
            $this->loadDynamicFields();
        }
        return $this->dynamics;
    }

    /**
     * Extract posted values for dynamic fields
     *
     * @param array<string, mixed>   $post     Posted values
     * @param array<string,int|bool> $required Array of required fields
     * @param array<string>          $disabled Array of disabled fields
     *
     * @return bool
     */
    protected function dynamicsCheck(array $post, array $required, array $disabled): bool
    {
        if (!isset($this->dynamics)) {
            Analog::log(
                'Dynamics fields have not been loaded, cannot be checked. (from: ' . __METHOD__ . ')',
                Analog::WARNING
            );
            $this->loadDynamicFields();
        }

        if ($post != null) {
            $valid = true;
            $fields = $this->dynamics->getFields();

            $dynamic_fields = [];
            //posted fields
            foreach ($post as $key => $value) {
                // if the field is enabled, and match patterns check it
                if (isset($disabled[$key]) || substr($key, 0, 11) != $this->name_pattern) {
                    continue;
                }

                list($field_id, $val_index) = explode('_', str_replace($this->name_pattern, '', $key));
                if (!is_numeric($field_id) || !is_numeric($val_index)) {
                    continue;
                }

                $dynamic_fields[$key] = [
                    'value'     => $value,
                    'field_id'  => $field_id,
                    'val_index' => $val_index
                ];
            }

            //some fields may be missing in posted values (checkboxes)
            foreach ($fields as $field) {
                $pattern = '/' . $this->name_pattern . $field->getId() . '_(\d)/';
                if ($field instanceof Boolean && !preg_grep($pattern, array_keys($dynamic_fields))) {
                    $dynamic_fields[$this->name_pattern . $field->getId() . '_1'] = [
                        'value'     => '',
                        'field_id'  => $field->getId(),
                        'val_index' => 1
                    ];
                }
            }

            foreach ($dynamic_fields as $key => $dfield_values) {
                $field_id = $dfield_values['field_id'];
                $value = $dfield_values['value'];
                $val_index = $dfield_values['val_index'];

                if ($fields[$field_id]->isRequired() && (trim($value) === '' || $value == null)) {
                    $this->errors[] = str_replace(
                        '%field',
                        $fields[$field_id]->getName(),
                        _T('Missing required field %field')
                    );
                } else {
                    if ($fields[$field_id] instanceof File) {
                        //delete checkbox
                        $filename = $fields[$field_id]->getFileName($this->id, $val_index);
                        if (file_exists(GALETTE_FILES_PATH . $filename)) {
                            unlink(GALETTE_FILES_PATH . $filename);
                        } elseif (!$this instanceof Adherent) {
                            $test_filename = $fields[$field_id]->getFileName($this->id, $val_index, 'member');
                            if (file_exists(GALETTE_FILES_PATH . $test_filename)) {
                                unlink(GALETTE_FILES_PATH . $test_filename);
                            }
                        }
                        $this->dynamics->setValue($this->id, $field_id, $val_index, '');
                    } else {
                        if ($fields[$field_id] instanceof Date && !empty(trim($value))) {
                            //check date format
                            try {
                                $d = \DateTime::createFromFormat(__("Y-m-d"), $value);
                                if ($d === false) {
                                    //try with non localized date
                                    $d = \DateTime::createFromFormat("Y-m-d", $value);
                                    if ($d === false) {
                                        throw new \Exception('Incorrect format');
                                    }
                                }
                            } catch (Throwable $e) {
                                $valid = false;
                                Analog::log(
                                    'Wrong date format. field: ' . $field_id .
                                    ', value: ' . $value . ', expected fmt: ' .
                                    __("Y-m-d") . ' | ' . $e->getMessage(),
                                    Analog::INFO
                                );
                                $this->errors[] = sprintf(
                                    //TRANS: %1$s date format, %2$s is the field name
                                    _T('- Wrong date format (%1$s) for %2$s!'),
                                    __("Y-m-d"),
                                    $fields[$field_id]->getName()
                                );
                            }
                        }
                        //actual field value
                        if ($value !== null && trim($value) !== '') {
                            $this->dynamics->setValue($this->id, $field_id, $val_index, $value);
                        } else {
                            $this->dynamics->unsetValue($field_id, $val_index);
                        }
                    }
                }
            }

            return $valid;
        }
        return false;
    }

    /**
     * Stores dynamic fields
     *
     * @param bool $transaction True if a transaction already exists
     *
     * @return bool
     */
    protected function dynamicsStore(bool $transaction = false): bool
    {
        if (!isset($this->dynamics)) {
            Analog::log(
                'Dynamics fields have not been loaded, cannot be stored. (from: ' . __METHOD__ . ')',
                Analog::WARNING
            );
            $this->loadDynamicFields();
        }
        $return = $this->dynamics->storeValues($this->id, $transaction);
        if (method_exists($this, 'updateModificationDate') && $this->dynamics->hasChanged()) {
            $this->updateModificationDate();
        }
        return $return;
    }

    /**
     * Store dynamic Files
     *
     * @param array<string, mixed> $files Posted files
     *
     * @return void
     */
    protected function dynamicsFiles(array $files): void
    {
        $this->loadDynamicFields();
        $fields = $this->dynamics->getFields();
        $store = false;

        foreach ($files as $key => $file) {
            if (substr($key, 0, 11) != $this->name_pattern) {
                continue;
            }

            list($field_id, $val_index) = explode('_', str_replace($this->name_pattern, '', $key));
            if (!is_numeric($field_id) || !is_numeric($val_index)) {
                continue;
            }

            if (
                $file['error'] == UPLOAD_ERR_NO_FILE
                && $file['name'] == ''
                && $file['tmp_name'] == ''
            ) {
                //not upload atempt.
                continue;
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                Analog::log("file upload error", Analog::ERROR);
                continue;
            }

            $tmp_filename = $file['tmp_name'];
            if ($tmp_filename == '') {
                Analog::log("empty temporary filename", Analog::ERROR);
                continue;
            }

            if (!is_uploaded_file($tmp_filename)) {
                Analog::log("not an uploaded file", Analog::ERROR);
                continue;
            }

            $max_size =
                $fields[$field_id]->getSize() ?
                $fields[$field_id]->getSize() * 1024 : File::DEFAULT_MAX_FILE_SIZE * 1024;
            if ($file['size'] > $max_size) {
                Analog::log(
                    "file too large: " . $file['size'] . " Ko, vs $max_size Ko allowed",
                    Analog::ERROR
                );
                $this->errors[] = preg_replace(
                    '|%d|',
                    (string)$max_size,
                    _T("File is too big. Maximum allowed size is %dKo")
                );
                continue;
            }

            $form_name = $this->getFormName();
            if ($form_name === 'adh') {
                $form_name = 'member'; //for compatibility with existing files
            }
            $new_filename = sprintf(
                '%s_%d_field_%d_value_%d',
                $form_name,
                $this->id,
                $field_id,
                $val_index
            );
            Analog::log("new file: $new_filename", Analog::DEBUG);

            move_uploaded_file(
                $tmp_filename,
                GALETTE_FILES_PATH . $new_filename
            );
            $this->dynamics->setValue($this->id, (int)$field_id, (int)$val_index, $file['name']);
            $store = true;
        }

        if ($store === true) {
            $this->dynamicsStore();
        }
    }

    /**
     * Remove dynamic fields values
     *
     * @param bool $transaction True if a transaction already exists
     *
     * @return bool
     */
    protected function dynamicsRemove(bool $transaction = false): bool
    {
        if (!isset($this->dynamics)) {
            Analog::log(
                'Dynamics fields have not been loaded, cannot be removed. (from: ' . __METHOD__ . ')',
                Analog::WARNING
            );
            $this->loadDynamicFields();
        }
        $return = $this->dynamics->removeValues($this->id, $transaction);
        return $return;
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
     * Validate data for dynamic fields
     * Set valid data in current object, also resets errors list
     *
     * @param array<string> $values Dynamic fields values
     * @param string        $prefix Prefix to replace, default to 'dynfield_'
     *
     * @return bool
     */
    public function dynamicsValidate(array $values, string $prefix = 'dynfield_'): bool
    {
        $dfields = [];
        foreach ($values as $key => $value) {
            $dfields[str_replace($prefix, $this->name_pattern, $key)] = $value;
        }
        return $this->dynamicsCheck($dfields, [], []);
    }

    /**
     * Get form name
     *
     * @return string
     */
    public function getFormName(): string
    {
        return array_search(get_class($this), DynamicFieldsSet::getClasses());
    }
}
