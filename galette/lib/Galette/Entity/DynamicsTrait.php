<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Dynamics fields trait
 *
 * PHP version 5
 *
 * Copyright © 2017-2018 The Galette Team
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
 * @copyright 2017-2018 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9dev - 2017-05-26
 */

namespace Galette\Entity;

use Analog\Analog;
use Galette\DynamicFields\File;
use Galette\DynamicFields\Date;

/**
 * Dynamics fields trait
 *
 * @category  Entity
 * @name      DynamicsTrait
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017-2018 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9dev - 2017-05-26
 */

trait DynamicsTrait
{
    protected $dynamics;

    /**
     * Load dynamic fields for member
     *
     * @return void
     */
    private function loadDynamicFields()
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
     * @return array
     */
    public function getDynamicFields()
    {
        return $this->dynamics;
    }

    /**
     * Extract posted values for dynamic fields
     *
     * @param array $post Posted values
     *
     * @return boolean
     */
    protected function dynamicsCheck($post)
    {
        if ($this->dynamics === null) {
            Analog::log(
                'Dynamics fields have not been loaded, cannot be checked. (from: ' . __METHOD__ . ')',
                Analog::WARNING
            );
            $this->loadDynamicFields();
        }

        if ($post != null) {
            $valid = true;
            $fields = $this->dynamics->getFields();

            foreach ($post as $key => $value) {
                // if the field is enabled, check it
                if (!isset($disabled[$key])) {
                    if (substr($key, 0, 11) == 'info_field_') {
                        list($field_id, $val_index) = explode('_', substr($key, 11));
                        if (
                            is_numeric($field_id)
                            && is_numeric($val_index)
                        ) {
                            if ($fields[$field_id]->isRequired() && (trim($value) === '' || $value == null)) {
                                $this->errors[] = str_replace(
                                    '%field',
                                    $fields[$field_id]->getName(),
                                    _T('Missing required field %field')
                                );
                            } else {
                                if ($fields[$field_id] instanceof File) {
                                    //delete checkbox
                                    $filename = sprintf(
                                        'member_%d_field_%d_value_%d',
                                        $this->id,
                                        $field_id,
                                        $val_index
                                    );
                                    unlink(GALETTE_FILES_PATH . $filename);
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
                                        } catch (\Exception $e) {
                                            $valid = false;
                                            Analog::log(
                                                'Wrong date format. field: ' . $field_id .
                                                ', value: ' . $value . ', expected fmt: ' .
                                                __("Y-m-d") . ' | ' . $e->getMessage(),
                                                Analog::INFO
                                            );
                                            $this->errors[] = str_replace(
                                                array(
                                                    '%date_format',
                                                    '%field'
                                                ),
                                                array(
                                                    __("Y-m-d"),
                                                    $fields[$field_id]->getName()
                                                ),
                                                _T("- Wrong date format (%date_format) for %field!")
                                            );
                                        }
                                    }
                                    //actual field value
                                    if ($value !== null && trim($value) !== '') {
                                        $this->dynamics->setValue($this->id, $field_id, $val_index, $value);
                                    } else {
                                        $this->dynamics->unsetValue($this->id, $field_id, $val_index);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return $valid;
        }
    }

    /**
     * Stores dynamic fields
     *
     * @param boolean $transaction True if a transaction already exists
     *
     * @return boolean
     */
    protected function dynamicsStore($transaction = false)
    {
        if ($this->dynamics === null) {
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
     * @param array $files Posted files
     *
     * @return void
     */
    protected function dynamicsFiles($files)
    {
        if ($this->dynamics === null) {
            Analog::log(
                'Dynamics fields have not been loaded, cannot be stored. (from: ' . __METHOD__ . ')',
                Analog::WARNING
            );
            $this->loadDynamicFields();
        }
        $fields = $this->dynamics->getFields();
        $store = false;

        foreach ($files as $key => $file) {
            // if the field is disabled, skip it
            if (isset($disabled[$key])) {
                continue;
            }

            if (substr($key, 0, 11) != 'info_field_') {
                continue;
            }

            list($field_id, $val_index) = explode('_', substr($key, 11));
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
                    $max_size,
                    _T("File is too big. Maximum allowed size is %dKo")
                );
                continue;
            }

            $new_filename = sprintf(
                'member_%d_field_%d_value_%d',
                $this->id,
                $field_id,
                $val_index
            );
            Analog::log("new file: $new_filename", Analog::DEBUG);

            move_uploaded_file(
                $tmp_filename,
                GALETTE_FILES_PATH . $new_filename
            );
            $this->dynamics->setValue($this->id, $field_id, $val_index, $file['name']);
            $store = true;
        }

        if ($store === true) {
            $this->dynamicsStore();
        }
    }

    /**
     * Remove dynamic fields values
     *
     * @param boolean $transaction True if a transaction already exists
     *
     * @return boolean
     */
    protected function dynamicsRemove($transaction = false)
    {
        if ($this->dynamics === null) {
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
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Validate data for dynamic fields
     * Set valid data in current object, also resets errors list
     *
     * @param array  $values Dynamic fields values
     * @param string $prefix Prefix to replace, default to 'dynfield_'
     *
     * @return void
     */
    public function dynamicsValidate($values, $prefix = 'dynfield_')
    {
        $dfields = [];
        foreach ($values as $key => $value) {
            $dfields[str_replace($prefix, 'info_field_', $key)] = $value;
        }
        return $this->dynamicsCheck($dfields);
    }
}
