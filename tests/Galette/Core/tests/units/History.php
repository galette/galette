<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * History tests
 *
 * PHP version 5
 *
 * Copyright © 2016 The Galette Team
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
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2016 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2016-11-26
 */

namespace Galette\Core\test\units;

use \atoum;

/**
 * History tests class
 *
 * @category  Core
 * @name      History
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2016 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2016-11-26
 */
class History extends atoum
{
    private $history = null;
    private $login;
    private $zdb;
    private $i18n;
    private $session;

    /**
     * Set up tests
     *
     * @param string $testMethod Method name
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
    {
        $this->zdb = new \Galette\Core\Db();
        $this->i18n = new \Galette\Core\I18n();
        $this->session = new \RKA\Session();
        $this->login = new \Galette\Core\Login($this->zdb, $this->i18n, $this->session);
        $this->history = new \Galette\Core\History($this->zdb, $this->login);
    }

    /**
     * Test class constants
     *
     * @return void
     */
    public function testConstants()
    {
        $this->string(\Galette\Core\History::TABLE)->isIdenticalTo('logs');
        $this->string(\Galette\Core\History::PK)->isIdenticalTo('id_log');
    }

    /**
     * Test history workflow
     *
     * @return void
     */
    public function testHistoryFlow()
    {
        //nothing in the logs at the begining
        $list = $this->history->getHistory();
        $this->integer($list->count())->isIdenticalTo(0);

        //add some entries
        $add = $this->history->add(
            'Test',
            'Something was added from tests'
        );
        $this->boolean($add)->isTrue();

        $add = $this->history->add(
            'Test',
            'Something else was added from tests',
            'SELECT * FROM none WHERE non ORDER BY none'
        );
        $this->boolean($add)->isTrue();

        $add = $this->history->add(
            'AnotherTest',
            'And something else, again'
        );
        $this->boolean($add)->isTrue();

        //check what has been stored
        $list = $this->history->getHistory();
        $this->integer($list->count())->isIdenticalTo(3);

        $actions = $this->history->getActionsList();
        $this->array($actions)
            ->hasSize(2)
            ->string[0]->isIdenticalTo('AnotherTest')
            ->string[1]->isIdenticalTo('Test');

        //some filtering
        $this->history->filters->action_filter = 'Test';
        $list = $this->history->getHistory();
        $this->integer($list->count())->isIdenticalTo(2);

        $this->history->filters->start_date_filter = date('Y-m-d');
        $this->history->filters->end_date_filter = date('Y-m-d');
        $list = $this->history->getHistory();
        $this->integer($list->count())->isIdenticalTo(2);

        //let's clean now
        $cleaned = $this->history->clean();
        $this->boolean($cleaned)->isTrue();

        $list = $this->history->getHistory();
        $this->integer($list->count())->isIdenticalTo(1);

        $this->zdb->db->query(
            'TRUNCATE TABLE ' . PREFIX_DB . \Galette\Core\History::TABLE,
            \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
    }

    /**
     * Test add that throws an exception
     *
     * @return void
     */
    public function testAddWException()
    {
        $this->zdb = new \mock\Galette\Core\Db();
        $this->calling($this->zdb)->execute = function ($o) {
            throw new \LogicException('Error executing query!', 123);
        };

        $this->history = new \Galette\Core\History($this->zdb, $this->login);
        $add = $this->history->add('Test');
        $this->boolean($add)->isFalse();
    }

    /**
     * Test getHistory that throws an exception
     *
     * @return void
     */
    public function testGetHistoryWException()
    {
        $this->zdb = new \mock\Galette\Core\Db();
        $this->calling($this->zdb)->execute = function ($o) {
            throw new \LogicException('Error executing query!', 123);
        };

        $this->history = new \Galette\Core\History($this->zdb, $this->login);
        $list = $this->history->getHistory();
        $this->boolean($list)->isFalse();
    }
}
