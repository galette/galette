<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * I18n
 *
 * PHP version 5
 *
 * Copyright © 2018 The Galette Team
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
 * @copyright 2018 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.1dev - 2018-03-10
 */

namespace Galette\Entity;

use Analog\Analog;
use Galette\Core\L10n;
use Laminas\Db\Sql\Expression;

/**
 * Files
 *
 * @category  Entity
 * @name      I18nTrait
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2018 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.1dev - 2018-03-10
 */

trait I18nTrait
{
    protected $warnings = [];

    /**
     * Add a translation stored in the database
     *
     * @param string $text_orig Text to translate
     *
     * @return boolean
     */
    protected function addTranslation($text_orig)
    {
        global $i18n;

        try {
            foreach ($i18n->getList() as $lang) {
                //check if translation already exists
                $select = $this->zdb->select(L10n::TABLE);
                $select->columns(array('text_nref'))
                    ->where(
                        array(
                            'text_orig'     => $text_orig,
                            'text_locale'   => $lang->getLongID()
                        )
                    );

                $results = $this->zdb->execute($select);
                $result = $results->current();
                $nref = 0;
                if ($result) {
                    $nref = $result->text_nref;
                }

                if (is_numeric($nref) && $nref > 0) {
                    //already existing, update
                    $values = array(
                        'text_nref' => new Expression('text_nref+1')
                    );
                    Analog::log(
                        'Entry for `' . $text_orig .
                        '` dynamic translation already exists.',
                        Analog::INFO
                    );

                    $owhere = $select->where;

                    $update = $this->zdb->update(L10n::TABLE);
                    $update->set($values)->where($owhere);
                    $this->zdb->execute($update);
                } else {
                    //add new entry
                    // User is supposed to use current language as original text.
                    $text_trans = $text_orig;
                    if ($lang->getLongID() != $i18n->getLongID()) {
                        $text_trans = '';
                    }
                    $values = array(
                        'text_orig' => $text_orig,
                        'text_locale' => $lang->getLongID(),
                        'text_trans' => $text_trans
                    );

                    $insert = $this->zdb->insert(L10n::TABLE);
                    $insert->values($values);
                    $this->zdb->execute($insert);
                }
            }
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'An error occurred adding dynamic translation for `' .
                $text_orig . '` | ' . $e->getMessage(),
                Analog::ERROR
            );

            $this->warnings[] = str_replace(
                '%field',
                $text_orig,
                _T('Unable to add dynamic translation for %field :(')
            );

            return false;
        }
    }

    /**
     * Update a translation stored in the database
     *
     * @param string $text_orig   Text to translate
     * @param string $text_locale The locale
     * @param string $text_trans  Translated text
     *
     * @return boolean
     */
    protected function updateTranslation($text_orig, $text_locale, $text_trans)
    {
        try {
            //check if translation already exists
            $select = $this->zdb->select(L10n::TABLE);
            $select->columns(array('text_nref'))->where(
                array(
                    'text_orig'     => $text_orig,
                    'text_locale'   => $text_locale
                )
            );

            $results = $this->zdb->execute($select);
            $result = $results->current();

            $exists = false;
            if ($result) {
                $nref = $result->text_nref;
                $exists = (is_numeric($nref) && $nref > 0);
            }

            $values = array(
                'text_trans' => $text_trans
            );

            if ($exists) {
                $owhere = $select->where;

                $update = $this->zdb->update(L10n::TABLE);
                $update->set($values)->where($owhere);
                $this->zdb->execute($update);
            } else {
                $values['text_orig'] = $text_orig;
                $values['text_locale'] = $text_locale;

                $insert = $this->zdb->insert(L10n::TABLE);
                $insert->values($values);
                $this->zdb->execute($insert);
            }
            return true;
        } catch (Exception $e) {
            Analog::log(
                'An error occurred updating dynamic translation for `' .
                $text_orig . '` | ' . $e->getMessage(),
                Analog::ERROR
            );

            $this->warnings[] = str_replace(
                '%field',
                $text_orig,
                _T('Unable to update dynamic translation for %field :(')
            );

            return false;
        }
    }

    /**
    * Delete a translation stored in the database
    *
    * @param string $text_orig Text to translate
    *
    * @return boolean
    */
    protected function deleteTranslation($text_orig)
    {
        try {
            $delete = $this->zdb->delete(L10n::TABLE);
            $delete->where(
                array(
                    'text_orig'     => $text_orig
                )
            );
            $this->zdb->execute($delete);
            return true;
        } catch (Exception $e) {
            Analog::log(
                'An error occurred deleting dynamic translation for `' .
                $text_orig . ' | ' . $e->getMessage(),
                Analog::ERROR
            );

            $this->warnings[] = str_replace(
                '%field',
                $text_orig,
                _T('Unable to remove old dynamic translation for %field :(')
            );

            return false;
        }
    }

    /**
     * Load dynamic fields for member
     *
     * @return void
     */
    /*private function loadDynamicFields()
    {
        if (!property_exists($this, 'login')) {
            global $login;
        } else {
            $login = $this->login;
        }
        $this->dynamics = new DynamicFields($this->zdb, $login, $this);
    }*/

    /**
     * Get dynamic fields
     *
     * @return array
     */
    /*public function getDynamicFields()
    {
        return $this->dynamics;
    }*/

    /**
     * Extract posted values for dynamic fields
     *
     * @param array $post Posted values
     *
     * @return boolean
     */
    /*protected function dynamicsCheck($post)
    {
        if ($this->dynamics === null) {
            Analog::log(
                'Dynamics fields have not been loaded, cannot be checked. (from: ' . __METHOD__ . ')',
                Analog::WARNING
            );
            $this->loadDynamicFields();
        }
        if ($post != null) {
            $fields = $this->dynamics->getFields();

            foreach ($post as $key => $value) {
                // if the field is enabled, check it
                if (!isset($disabled[$key])) {
                    if (substr($key, 0, 11) == 'info_field_') {
                        list($field_id, $val_index) = explode('_', substr($key, 11));
                        if (is_numeric($field_id)
                            && is_numeric($val_index)
                        ) {
                            if ($fields[$field_id]->isRequired() && (trim($value) === '' || $value == null)) {
                                $this->errors[] = str_replace(
                                    '%field',
                                    $field->getName(),
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

            return true;
        }
    }*/

    /**
     * Stores dynamic fields
     *
     * @param boolean $transaction True if a transaction already exists
     *
     * @return boolean
     */
    /*protected function dynamicsStore($transaction = false)
    {
        if ($this->dynamics === null) {
            Analog::log(
                'Dynamics fields have not been loaded, cannot be checked. (from: ' . __METHOD__ . ')',
                Analog::WARNING
            );
            $this->loadDynamicFields();
        }
        return $this->dynamics->storeValues($this->id, $transaction);
    }*/

    /**
     * Store dynamic Files
     *
     * @param array $files Posted files
     *
     * @return void
     */
    /*protected function dynamicsFiles($files)
    {
        if ($this->dynamics === null) {
            Analog::log(
                'Dynamics fields have not been loaded, cannot be checked. (from: ' . __METHOD__ . ')',
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
            if (! is_numeric($field_id) || ! is_numeric($val_index)) {
                continue;
            }

            if ($file['error'] == UPLOAD_ERR_NO_FILE
                && $file['name'] == ''
                && $file['tmp_name'] == '') {
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
                $fields[$field_id]->getSize() * 1024 :
                File::DEFAULT_MAX_FILE_SIZE * 1024;
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
    }*/

    /**
     * Get errors
     *
     * @return array
     */
    /*public function getErrors()
    {
        return $this->errors;
    }*/
}
