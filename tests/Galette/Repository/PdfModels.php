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
 * PDF models repository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PdfModels extends GaletteTestCase
{
    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();

        $models = new \Galette\Repository\PdfModels($this->zdb, $this->preferences, $this->login);
        $res = $models->installInit(false);
        $this->assertTrue($res);
    }

    /**
     * Test getList
     */
    public function testGetList(): void
    {
        global $zdb;
        $zdb = $this->zdb; //globals '(

        $_SERVER['HTTP_HOST'] = '';

        $models = new \Galette\Repository\PdfModels($this->zdb, $this->preferences, $this->login);

        //install pdf models
        $list = $models->getList();
        $this->assertCount(4, $list);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($this->zdb->getSequenceName(\Galette\Entity\PdfModel::TABLE, \Galette\Entity\PdfModel::PK));
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
            $select = $this->zdb->select($this->zdb->getSequenceName(\Galette\Entity\PdfModel::TABLE, \Galette\Entity\PdfModel::PK));
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
