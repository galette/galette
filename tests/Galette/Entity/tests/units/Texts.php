<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Text tests
 *
 * PHP version 5
 *
 * Copyright © 2019-2020 The Galette Team
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
 * @copyright 2019-2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2019-12-20
 */

namespace Galette\Entity\test\units;

use atoum;
use Zend\Db\Adapter\Adapter;

/**
 * Text tests
 *
 * @category  Entity
 * @name      Texts
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-01-18
 */
class Texts extends atoum
{
    private $zdb;
    private $remove = [];
    private $i18n;
    private $preferences;

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
        $this->i18n = new \Galette\Core\I18n(
            \Galette\Core\I18n::DEFAULT_LANG
        );
        $this->preferences = new \Galette\Core\Preferences(
            $this->zdb
        );

        global $zdb, $i18n; // globals :(
        $zdb = $this->zdb;
        $i18n = $this->i18n;
    }

    /**
     * Test getList
     *
     * @return void
     */
    public function testGetList()
    {
        $count_texts = 13;
        $texts = new \Galette\Entity\Texts(
            $this->preferences
        );
        $texts->installInit();

        $list = $texts->getRefs(\Galette\Core\I18n::DEFAULT_LANG);
        $this->array($list)->hasSize($count_texts);

        foreach (array_keys($this->i18n->getArrayList()) as $lang) {
            $list = $texts->getRefs($lang);
            $this->array($list)->hasSize($count_texts);
        }

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($texts::TABLE . '_id_seq');
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->integer($result->last_value)
                 ->isGreaterThanOrEqualTo($count_texts, 'Incorrect texts sequence ' . $result->last_value);

            $this->zdb->db->query(
                'SELECT setval(\'' . PREFIX_DB . $texts::TABLE . '_id_seq\', 1)',
                Adapter::QUERY_MODE_EXECUTE
            );
        }

        //reinstall texts
        $texts->installInit(false);

        $list = $texts->getRefs(\Galette\Core\I18n::DEFAULT_LANG);
        $this->array($list)->hasSize($count_texts);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($texts::TABLE . '_id_seq');
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->integer($result->last_value)
                 ->isGreaterThanOrEqualTo(12, 'Incorrect texts sequence ' . $result->last_value);
        }
    }
}
