<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

namespace Galette\Tests\Repository;

use Galette\Tests\GaletteTestCase;

/**
 * Payment types repository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PaymentTypes extends GaletteTestCase
{
    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();

        $types = new \Galette\Repository\PaymentTypes($this->zdb, $this->preferences, $this->login);
        $res = $types->installInit(false);
        $this->assertTrue($res);
    }

    /**
     * Test getList
     */
    public function testGetList(): void
    {
        $types = new \Galette\Repository\PaymentTypes($this->zdb, $this->preferences, $this->login);

        //non admin users will not see scheduled payment type
        $list = $types->getList();
        $this->assertCount(8, $list);

        $this->logSuperadmin();
        $list = $types->getList();
        $this->assertCount(9, $list);
        $this->login->logout();

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($this->zdb->getSequenceName(\Galette\Entity\PaymentType::TABLE, \Galette\Entity\PaymentType::PK));
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->assertGreaterThanOrEqual(9, $result->last_value, 'Incorrect payments types sequence');
        }

        //reinstall payment types
        $types->installInit();

        $this->logSuperadmin();
        $list = $types->getList();
        $this->assertCount(9, $list);
        $this->login->logout();

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($this->zdb->getSequenceName(\Galette\Entity\PaymentType::TABLE, \Galette\Entity\PaymentType::PK));
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->assertGreaterThanOrEqual(
                9,
                $result->last_value,
                'Incorrect payment types sequence ' . $result->last_value
            );
        }
    }
}
