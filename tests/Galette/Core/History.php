<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\GaletteTestCase;

/**
 * History tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class History extends GaletteTestCase
{
    /**
     * Test class constants
     */
    public function testConstants(): void
    {
        $this->assertSame('logs', \Galette\Core\History::TABLE);
        $this->assertSame('id_log', \Galette\Core\History::PK);
    }

    /**
     * Test history workflow
     */
    public function testHistoryFlow(): void
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
        $this->assertSame('Logs flushed', $list[0]->action_log);
    }
}
