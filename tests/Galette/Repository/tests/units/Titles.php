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

namespace Galette\Repository\test\units;

use Galette\GaletteTestCase;

/**
 * Payment types repository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Titles extends GaletteTestCase
{
    protected int $seed = 20240417170519;

    private array $remove = [];

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $titles = new \Galette\Repository\Titles($this->zdb);
        $res = $titles->installInit();
        $this->assertTrue($res);
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
        $this->deleteTitles();
    }

    /**
     * Delete payment type
     *
     * @return void
     */
    private function deleteTitles(): void
    {
        if (is_array($this->remove) && count($this->remove) > 0) {
            $delete = $this->zdb->delete(\Galette\Entity\Title::TABLE);
            $delete->where->in(\Galette\Entity\Title::PK, $this->remove);
            $this->zdb->execute($delete);
        }

        //Clean logs
        $this->zdb->db->query(
            'TRUNCATE TABLE ' . PREFIX_DB . \Galette\Core\History::TABLE,
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
    }

    /**
     * Test getList
     *
     * @return void
     */
    public function testGetList(): void
    {
        $titles = new \Galette\Repository\Titles($this->zdb);

        $list = $titles->getList();
        $this->assertCount(2, $list);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select(\Galette\Entity\PaymentType::TABLE . '_id_seq');
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->assertGreaterThanOrEqual(1, $result->last_value, 'Incorrect titles sequence');
        }

        //add another one
        $title = new \Galette\Entity\Title();
        $title->short = 'Te.';
        $title->long = 'Test';
        $this->assertTrue($title->store($this->zdb));

        $id = $title->id;
        $this->remove[] = $id;

        $list = $titles->getList();
        $this->assertCount(3, $list);

        //reinstall payment types
        $titles->installInit();

        $list = $titles->getList();
        $this->assertCount(2, $list);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select(\Galette\Entity\PaymentType::TABLE . '_id_seq');
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->assertGreaterThanOrEqual(
                1,
                $result->last_value,
                'Incorrect title sequence ' . $result->last_value
            );
        }
    }
}
