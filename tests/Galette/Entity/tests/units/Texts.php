<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

namespace Galette\Entity\test\units;

use PHPUnit\Framework\TestCase;
use Galette\GaletteTestCase;
use Laminas\Db\Adapter\Adapter;

/**
 * Text tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Texts extends GaletteTestCase
{
    private array $remove = [];

    /**
     * Test getList
     *
     * @return void
     */
    public function testGetList(): void
    {
        $count_texts = 13;
        $texts = new \Galette\Entity\Texts(
            $this->preferences
        );
        $texts->installInit();

        $list = $texts->getRefs(\Galette\Core\I18n::DEFAULT_LANG);
        $this->assertCount($count_texts, $list);

        foreach (array_keys($this->i18n->getArrayList()) as $lang) {
            $list = $texts->getRefs($lang);
            $this->assertCount($count_texts, $list);
        }

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($this->zdb->getSequenceName($texts::TABLE, $texts::PK));
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->assertGreaterThanOrEqual($count_texts, $result->last_value, 'Incorrect texts sequence ' . $result->last_value);

            $this->zdb->db->query(
                'SELECT setval(\'' . $this->zdb->getSequenceName($texts::TABLE, $texts::PK, true) . '\', 1)',
                Adapter::QUERY_MODE_EXECUTE
            );
        }

        //reinstall texts
        $texts->installInit(false);

        $list = $texts->getRefs(\Galette\Core\I18n::DEFAULT_LANG);
        $this->assertCount($count_texts, $list);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($this->zdb->getSequenceName($texts::TABLE, $texts::PK));
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->assertGreaterThanOrEqual(12, $result->last_value, 'Incorrect texts sequence ' . $result->last_value);
        }
    }
}
