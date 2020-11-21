<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * PDF models repository tests
 *
 * PHP version 5
 *
 * Copyright © 2019 The Galette Team
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
 * @category  Repository
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2019-12-17
 */

namespace Galette\Repository\test\units;

use atoum;

/**
 * PDF models repository tests
 *
 * @category  Repository
 * @name      PdfModels
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2019-12-17
 */
class PdfModels extends atoum
{
    private $zdb;
    private $preferences;
    private $session;
    private $login;
    private $remove = [];
    private $i18n;

    /**
     * Set up tests
     *
     * @param string $testMethod Calling method
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
    {
        $this->zdb = new \Galette\Core\Db();
        $this->preferences = new \Galette\Core\Preferences($this->zdb);
        $this->i18n = new \Galette\Core\I18n(
            \Galette\Core\I18n::DEFAULT_LANG
        );
        $this->session = new \RKA\Session();
        $this->login = new \Galette\Core\Login($this->zdb, $this->i18n, $this->session);

        $models = new \Galette\Repository\PdfModels($this->zdb, $this->preferences, $this->login);
        $res = $models->installInit(false);
        $this->boolean($res)->isTrue();
    }

    /**
     * Tear down tests
     *
     * @param string $testMethod Calling method
     *
     * @return void
     */
    public function afterTestMethod($testMethod)
    {
        $this->deletePdfModels();
    }

    /**
     * Delete pdf models
     *
     * @return void
     */
    private function deletePdfModels()
    {
        if (is_array($this->remove) && count($this->remove) > 0) {
            $delete = $this->zdb->delete(\Galette\Entity\PdfModel::TABLE);
            $delete->where->in(\Galette\Repository\PdfModel::PK, $this->remove);
            $this->zdb->execute($delete);
        }

        //Clean logs
        $this->zdb->db->query(
            'TRUNCATE TABLE ' . PREFIX_DB . \Galette\Core\History::TABLE,
            \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
    }

    /**
     * Test getList
     *
     * @return void
     */
    public function testGetList()
    {
        global $container, $zdb;
        $zdb = $this->zdb; //globals '(
        $container = new class {
            /**
             * Get (only router)
             *
             * @param string $name Param name
             *
             * @return mixed
             */
            public function get($name)
            {
                $router = new class {
                    /**
                     * Get path ('')
                     *
                     * @param sttring $name Route name
                     *
                     * @return string
                     */
                    public function pathFor($name)
                    {
                        return '';
                    }
                };
                return $router;
            }
        };
        $_SERVER['HTTP_HOST'] = '';

        $models = new \Galette\Repository\PdfModels($this->zdb, $this->preferences, $this->login);

        //install pdf models
        $list = $models->getList();
        $this->array($list)->hasSize(4);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select(\Galette\Entity\PdfModel::TABLE . '_id_seq');
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->integer($result->last_value)
                 ->isGreaterThanOrEqualTo(4, 'Incorrect PDF models sequence: ' . $result->last_value);
        }

        //reinstall pdf models
        $models->installInit();

        $list = $models->getList();
        $this->array($list)->hasSize(4);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select(\Galette\Entity\PdfModel::TABLE . '_id_seq');
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->integer($result->last_value)->isGreaterThanOrEqualTo(
                4,
                'Incorrect PDF models sequence ' . $result->last_value
            );
        }
    }
}
