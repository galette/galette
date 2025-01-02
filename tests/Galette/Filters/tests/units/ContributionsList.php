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

use Galette\GaletteTestCase;

/**
 * Contribution filters tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ContributionsList extends GaletteTestCase
{
    /**
     * Test filter defaults values
     *
     * @param \Galette\Filters\ContributionsList $filters Filters instance
     *
     * @return void
     */
    protected function testDefaults(\Galette\Filters\ContributionsList $filters): void
    {
        $this->assertSame(\Galette\Filters\ContributionsList::ORDERBY_BEGIN_DATE, $filters->orderby);
        $this->assertSame(\Galette\Filters\ContributionsList::ORDER_ASC, $filters->ordered);
        $this->assertSame(\Galette\Filters\ContributionsList::DATE_BEGIN, $filters->date_field);
        $this->assertNull($filters->start_date_filter);
        $this->assertNull($filters->end_date_filter);
        $this->assertNull($filters->payment_type_filter);
        $this->assertFalse($filters->filtre_transactions);
        $this->assertNull($filters->filtre_cotis_adh);
        $this->assertFalse($filters->filtre_cotis_children);
        $this->assertFalse($filters->from_transaction);
        $this->assertNull($filters->max_amount);
        $this->assertEmpty($filters->selected);
    }

    /**
     * Test creation
     *
     * @return void
     */
    public function testCreate(): void
    {
        $filters = new \Galette\Filters\ContributionsList();

        $this->testDefaults($filters);

        //change order field
        $filters->orderby = \Galette\Filters\ContributionsList::ORDERBY_AMOUNT;
        $this->assertSame(\Galette\Filters\ContributionsList::ORDERBY_AMOUNT, $filters->orderby);
        $this->assertSame(\Galette\Filters\ContributionsList::ORDER_ASC, $filters->ordered);

        //same order field again: direction inverted
        $filters->orderby = \Galette\Filters\ContributionsList::ORDERBY_AMOUNT;
        $this->assertSame(\Galette\Filters\ContributionsList::ORDERBY_AMOUNT, $filters->orderby);
        $this->assertSame(\Galette\Filters\ContributionsList::ORDER_DESC, $filters->ordered);

        //not existing order, same kept
        $filters->ordered = 42;
        $this->assertSame(\Galette\Filters\ContributionsList::ORDERBY_AMOUNT, $filters->orderby);
        $this->assertSame(\Galette\Filters\ContributionsList::ORDER_DESC, $filters->ordered);

        //change direction only
        $filters->ordered = \Galette\Filters\ContributionsList::ORDER_ASC;
        $this->assertSame(\Galette\Filters\ContributionsList::ORDERBY_AMOUNT, $filters->orderby);
        $this->assertSame(\Galette\Filters\ContributionsList::ORDER_ASC, $filters->ordered);

        //set filter on children
        $filters->filtre_cotis_children = 5;
        $this->assertSame(5, $filters->filtre_cotis_children);

        $filters->date_field = \Galette\Filters\ContributionsList::DATE_END;
        $this->assertSame(\Galette\Filters\ContributionsList::DATE_END, $filters->date_field);

        $filters->payment_type_filter = 42;
        $this->assertSame(42, $filters->payment_type_filter);

        $filters->filtre_transactions = true;
        $this->assertTrue($filters->filtre_transactions);

        $filters->filtre_cotis_adh = 42;
        $this->assertSame(42, $filters->filtre_cotis_adh);

        $filters->from_transaction = 18;
        $this->assertSame(18, $filters->from_transaction);

        $filters->max_amount = 42;
        $this->assertSame(42, $filters->max_amount);

        $filters->selected = [0, 1, 2];
        $this->assertSame([0, 1, 2], $filters->selected);

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
        $filters = new \Galette\Filters\ContributionsList();
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
