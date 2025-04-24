<?php

/**
 * Copyright © 2003-2025 The Galette Team
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
 */

declare(strict_types=1);

namespace Galette\Filters\test\units;

use Galette\Enums\SQLOrder;
use Galette\GaletteTestCase;

/**
 * History filters tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class HistoryList extends GaletteTestCase
{
    /**
     * Test filter defaults values
     *
     * @param \Galette\Filters\HistoryList $filters Filters instance
     *
     * @return void
     */
    protected function testDefaults(\Galette\Filters\HistoryList $filters): void
    {
        $this->assertSame(\Galette\Filters\HistoryList::ORDERBY_DATE, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::DESC->value, $filters->getDirection());
        $this->assertNull($filters->start_date_filter);
        $this->assertNull($filters->end_date_filter);
        $this->assertNull($filters->user_filter);
        $this->assertNull($filters->action_filter);
    }

    /**
     * Test creation
     *
     * @return void
     */
    public function testCreate(): void
    {
        $filters = new \Galette\Filters\HistoryList();

        $this->testDefaults($filters);

        //change order field
        $filters->orderby = \Galette\Filters\HistoryList::ORDERBY_USER;
        $this->assertSame(\Galette\Filters\HistoryList::ORDERBY_USER, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::DESC->value, $filters->getDirection());

        //same order field again: direction inverted
        $filters->orderby = \Galette\Filters\HistoryList::ORDERBY_USER;
        $this->assertSame(\Galette\Filters\HistoryList::ORDERBY_USER, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::ASC->value, $filters->getDirection());

        //not existing order, same kept
        $filters->setDirection('abcde');
        $this->assertSame(\Galette\Filters\HistoryList::ORDERBY_USER, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::ASC->value, $filters->getDirection());
        $this->expectLogEntry(
            \Analog::WARNING,
            '[Galette\Filters\HistoryList|Pagination] "abcde" is not a valid backing value for enum Galette\Enums\SQLOrder'
        );

        //change direction only
        $filters->setDirection(\Galette\Enums\SQLOrder::DESC);
        $this->assertSame(\Galette\Filters\HistoryList::ORDERBY_USER, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::DESC->value, $filters->getDirection());

        //change direction only - deprecated way
        $filters->ordered = \Galette\Enums\SQLOrder::DESC;
        $this->expectLogEntry(
            \Analog::WARNING,
            '[Galette\Filters\HistoryList|Pagination] ordered is deprecated, use setDirection() instead'
        );
        $this->assertSame(\Galette\Filters\HistoryList::ORDERBY_USER, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::DESC->value, $filters->ordered);
        $this->expectLogEntry(
            \Analog::WARNING,
            '[Galette\Filters\HistoryList|Pagination] ordered is deprecated, use getDirection() instead'
        );

        //set filter on user
        $filters->user_filter = '42';
        $this->assertSame('42', $filters->user_filter);

        //reinit and test defaults are back
        $filters->reinit();
        $this->testDefaults($filters);
    }

    /**
     * Test localized date in filter
     *
     * @return void
     */
    public function testLocalizedDates(): void
    {
        $filters = new \Galette\Filters\HistoryList();
        $this->testDefaults($filters);

        $i18n = new \Galette\Core\I18n(
            \Galette\Core\I18n::DEFAULT_LANG
        );
        $langs = $i18n->getList();
        $filter_date = new \DateTime('2000-01-01'); //day of the bug :D

        foreach ($langs as $lang) {
            $this->assertInstanceOf('\Galette\Core\I18n', $lang);
            $i18n->changeLanguage($lang->getID());
            $this->assertSame($i18n->getID(), $lang->getID());
            try {
                $filters->start_date_filter = $filter_date->format(__('Y-m-d'));
                $filters->start_date_filter = $filter_date->format(__('Y-m'));
                $filters->start_date_filter = $filter_date->format(__('Y'));
            } catch (\Throwable $e) {
                $this->fail(
                    sprintf(
                        'Failed to set start date filter with lang %s: %s',
                        $lang->getID(),
                        $e->getMessage()
                    )
                );
            }
        }
    }
}
