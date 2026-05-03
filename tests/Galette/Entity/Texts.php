<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Entity;

use Galette\Tests\GaletteTestCase;
use Laminas\Db\Adapter\Adapter;

/**
 * Text tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Texts extends GaletteTestCase
{
    /**
     * Test getList
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
