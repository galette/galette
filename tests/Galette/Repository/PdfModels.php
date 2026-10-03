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
        $res = $models->installInit(check_first: false);
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

    /**
     * Test existing models are kept on update, and missing ones restored
     */
    public function testInstallInitKeepsExisting(): void
    {
        $update = $this->zdb->update(\Galette\Entity\PdfModel::TABLE);
        $update->set(['model_footer' => 'My own footer'])
            ->where([\Galette\Entity\PdfModel::PK => \Galette\Entity\PdfModel::MAIN_MODEL]);
        $this->zdb->execute($update);

        $delete = $this->zdb->delete(\Galette\Entity\PdfModel::TABLE);
        $delete->where([\Galette\Entity\PdfModel::PK => 4]);
        $this->zdb->execute($delete);

        $models = new \Galette\Repository\PdfModels($this->zdb, $this->preferences, $this->login);
        $this->assertTrue($models->installInit());
        $this->assertCount(4, $models->getList());

        $select = $this->zdb->select(\Galette\Entity\PdfModel::TABLE);
        $select->where([\Galette\Entity\PdfModel::PK => \Galette\Entity\PdfModel::MAIN_MODEL]);
        $this->assertSame('My own footer', $this->zdb->execute($select)->current()->model_footer);

        //nothing missing
        $this->assertTrue($models->installInit());
        $this->assertCount(4, $models->getList());
        $this->assertSame('My own footer', $this->zdb->execute($select)->current()->model_footer);
    }
}
