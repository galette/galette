<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * History tests
 *
 * PHP version 5
 *
 * Copyright © 2016-2021 The Galette Team
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
 * @copyright 2016-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2016-11-26
 */

namespace Galette\Core\test\units;

use Galette\GaletteTestCase;

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
class History extends GaletteTestCase
{
    /**
     * Test class constants
     *
     * @return void
     */
    public function testConstants()
    {
        $this->assertSame('logs', \Galette\Core\History::TABLE);
        $this->assertSame('id_log', \Galette\Core\History::PK);
    }

    /**
     * Test history workflow
     *
     * @return void
     */
    public function testHistoryFlow()
    {
        $this->i18n->changeLanguage('en_US');
        //nothing in the logs at the beginning
        $list = $this->history->getHistory();
        $this->assertCount(0, $list);

        //add some entries
        $add = $this->history->add(
            'Test',
            'Something was added from tests'
        );
        $this->assertTrue($add);

        $add = $this->history->add(
            'Test',
            'Something else was added from tests',
            'SELECT * FROM none WHERE non ORDER BY none'
        );
        $this->assertTrue($add);

        $add = $this->history->add(
            'AnotherTest',
            'And something else, again'
        );
        $this->assertTrue($add);

        //check what has been stored
        $list = $this->history->getHistory();
        $this->assertCount(3, $list);

        $actions = $this->history->getActionsList();
        $this->assertSame(
            $actions,
            [
                'AnotherTest',
                'Test'
            ]
        );

        //some filtering
        $this->history->filters->action_filter = 'Test';
        $list = $this->history->getHistory();
        $this->assertCount(2, $list);

        $this->history->filters->start_date_filter = date('Y-m-d');
        $this->history->filters->end_date_filter = date('Y-m-d');
        $list = $this->history->getHistory();
        $this->assertCount(2, $list);

        //let's clean now
        $cleaned = $this->history->clean();
        $this->assertTrue($cleaned);

        $list = $this->history->getHistory();
        $this->assertCount(1, $list);

        $this->cleanHistory();
    }
}
