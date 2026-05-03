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
class Titles extends GaletteTestCase
{
    protected int $seed = 20240417170519;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();

        $titles = new \Galette\Repository\Titles($this->zdb);
        $res = $titles->installInit();
        $this->assertTrue($res);
    }

    /**
     * Test getList
     */
    public function testGetList(): void
    {
        $titles = new \Galette\Repository\Titles($this->zdb);

        $list = $titles->getList();
        $this->assertCount(2, $list);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($this->zdb->getSequenceName(\Galette\Entity\PaymentType::TABLE, \Galette\Entity\PaymentType::PK));
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

        $list = $titles->getList();
        $this->assertCount(3, $list);

        //reinstall payment types
        $titles->installInit();

        $list = $titles->getList();
        $this->assertCount(2, $list);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($this->zdb->getSequenceName(\Galette\Entity\PaymentType::TABLE, \Galette\Entity\PaymentType::PK));
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
