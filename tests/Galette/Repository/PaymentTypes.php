<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
