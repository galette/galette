<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * I18n
 *
 * PHP version 5
 *
 * Copyright © 2018-2021 The Galette Team
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
 * @copyright 2018-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.9.1dev - 2018-03-10
 */

namespace Galette\Features;

use Throwable;
use Analog\Analog;
use Galette\Core\L10n;
use Laminas\Db\Sql\Expression;

/**
 * Files
 *
 * @category  Features
 * @name      I18n
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2018-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.9.1dev - 2018-03-10
 */

trait I18n
{
    protected array $warnings = [];

    /**
     * Add a translation stored in the database
     *
     * @param string $text_orig Text to translate
     *
     * @return boolean
     */
    protected function addTranslation(string $text_orig): bool
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
        } catch (Throwable $e) {
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
    protected function updateTranslation(string $text_orig, string $text_locale, string $text_trans): bool
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
        } catch (Throwable $e) {
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
    protected function deleteTranslation(string $text_orig): bool
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
        } catch (Throwable $e) {
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
     * Get warnings
     *
     * @return array
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
