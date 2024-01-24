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

namespace Galette\Repository\test\units;

use Galette\GaletteTestCase;

/**
 * PDF models repository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PdfModels extends GaletteTestCase
{
    private array $remove = [];

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $models = new \Galette\Repository\PdfModels($this->zdb, $this->preferences, $this->login);
        $res = $models->installInit(false);
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
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
    }

    /**
     * Test getList
     *
     * @return void
     */
    public function testGetList()
    {
        global $zdb;
        $zdb = $this->zdb; //globals '(

        $_SERVER['HTTP_HOST'] = '';

        $models = new \Galette\Repository\PdfModels($this->zdb, $this->preferences, $this->login);

        //install pdf models
        $list = $models->getList();
        $this->assertCount(4, $list);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select(\Galette\Entity\PdfModel::TABLE . '_id_seq');
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->assertGreaterThanOrEqual(
                4,
                $result->last_value,
                'Incorrect PDF models sequence: ' . $result->last_value
            );
        }

        //reinstall pdf models
        $models->installInit();

        $list = $models->getList();
        $this->assertCount(4, $list);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select(\Galette\Entity\PdfModel::TABLE . '_id_seq');
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->assertGreaterThanOrEqual(
                4,
                $result->last_value,
                'Incorrect PDF models sequence ' . $result->last_value
            );
        }
    }
}
