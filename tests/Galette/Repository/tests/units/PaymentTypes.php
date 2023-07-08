<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Payment types repository tests
 *
 * PHP version 5
 *
 * Copyright © 2019-2023 The Galette Team
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
 * @copyright 2019-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2019-12-17
 */

namespace Galette\Repository\test\units;

use Galette\GaletteTestCase;

/**
 * Payment types repository tests
 *
 * @category  Repository
 * @name      PaymentTypes
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2019-12-17
 */
class PaymentTypes extends GaletteTestCase
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

        $types = new \Galette\Repository\PaymentTypes($this->zdb, $this->preferences, $this->login);
        $res = $types->installInit(false);
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
        $this->deletePaymentType();
    }

    /**
     * Delete payment type
     *
     * @return void
     */
    private function deletePaymentType()
    {
        if (is_array($this->remove) && count($this->remove) > 0) {
            $delete = $this->zdb->delete(\Galette\Entity\PaymentType::TABLE);
            $delete->where->in(\Galette\Entity\PaymentTypes::PK, $this->remove);
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
        $types = new \Galette\Repository\PaymentTypes($this->zdb, $this->preferences, $this->login);

        $list = $types->getList();
        $this->assertCount(6, $list);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select(\Galette\Entity\PaymentType::TABLE . '_id_seq');
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->assertGreaterThanOrEqual(6, $result->last_value, 'Incorrect payments types sequence');
        }

        //reinstall payment types
        $types->installInit();

        $list = $types->getList();
        $this->assertCount(6, $list);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select(\Galette\Entity\PaymentType::TABLE . '_id_seq');
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->assertGreaterThanOrEqual(
                6,
                $result->last_value,
                6,
                'Incorrect payment types sequence ' . $result->last_value
            );
        }
    }
}
