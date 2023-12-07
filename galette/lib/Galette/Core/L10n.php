<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * l10n handling
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
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-07-31
 */

namespace Galette\Core;

use Throwable;
use Analog\Analog;
use Galette\Core\Db;
use Galette\Core\I18n;
use Laminas\Db\Sql\Expression;

/**
 * l10n handling
 *
 * @category  Core
 * @name      l10n
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-07-31
 */

class L10n
{
    public const TABLE = 'l10n';

    /** @var Db */
    private $zdb;
    /** @var I18n */
    private $i18n;

    /**
     * Default constructor.
     *
     * @param Db   $zdb  Database instance
     * @param I18n $i18n I18n instance
     */
    public function __construct(Db $zdb, I18n $i18n)
    {
        $this->zdb = $zdb;
        $this->i18n = $i18n;
    }

    /**
     * Add a translation stored in the database
     *
     * @param string $text_orig Text to translate
     *
     * @return boolean
     */
    public function addDynamicTranslation($text_orig)
    {
        try {
            foreach ($this->i18n->getList() as $lang) {
                //check if translation already exists
                $select = $this->zdb->select(self::TABLE);
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

                    $update = $this->zdb->update(self::TABLE);
                    $update->set($values)->where($owhere);
                    $this->zdb->execute($update);
                } else {
                    //add new entry
                    // User is supposed to use current language as original text.
                    $text_trans = $text_orig;
                    if ($lang->getLongID() != $this->i18n->getLongID()) {
                        $text_trans = '';
                    }
                    $values = array(
                        'text_orig' => $text_orig,
                        'text_locale' => $lang->getLongID(),
                        'text_trans' => $text_trans
                    );

                    $insert = $this->zdb->insert(self::TABLE);
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
    public function deleteDynamicTranslation($text_orig)
    {
        try {
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where(
                array(
                    'text_orig'     => $text_orig,
                    'text_locale'   => ':lang_id'
                )
            );
            $stmt = $this->zdb->sql->prepareStatementForSqlObject($delete);

            foreach ($this->i18n->getList() as $lang) {
                $stmt->execute(
                    array(
                        'text_locale' => $lang->getLongID()
                    )
                );
            }
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred deleting dynamic translation for `' .
                $text_orig . ' | ' .
                $e->getMessage(),
                Analog::ERROR
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
    public function updateDynamicTranslation($text_orig, $text_locale, $text_trans)
    {
        try {
            //check if translation already exists
            $select = $this->zdb->select(self::TABLE);
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

                $update = $this->zdb->update(self::TABLE);
                $update->set($values)->where($owhere);
                $this->zdb->execute($update);
            } else {
                $values['text_orig'] = $text_orig;
                $values['text_locale'] = $text_locale;

                $insert = $this->zdb->insert(self::TABLE);
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
            return false;
        }
    }

    /**
     * Get a translation stored in the database
     *
     * @param string $text_orig   Text to translate
     * @param string $text_locale The locale
     *
     * @return string
     */
    public function getDynamicTranslation($text_orig, $text_locale)
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->limit(1)->columns(
                array('text_trans')
            )->where(
                array(
                    'text_orig'     => $text_orig,
                    'text_locale'   => $text_locale
                )
            );
            $results = $this->zdb->execute($select);
            if ($results->count() > 0) {
                $res = $results->current();
                return $res->text_trans;
            } else {
                return '';
            }
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred retrieving l10n entry. text_orig=' . $text_orig .
                ', text_locale=' . $text_locale . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }
}
