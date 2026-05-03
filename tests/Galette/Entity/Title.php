<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Entity;

use Galette\Tests\GaletteTestCase;

/**
 * Status tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Title extends GaletteTestCase
{
    /**
     * Test title
     */
    public function testTitle(): void
    {
        global $zdb;
        $zdb = $this->zdb;

        $title = new \Galette\Entity\Title();

        $title->short = 'Te.';
        $title->long = 'Test';
        $this->assertTrue($title->store($this->zdb));

        $id = $title->id;
        $title = new \Galette\Entity\Title($id); //reload

        //$title->long = 'Test title 🤘'; //FIXME: works locally, fails on gh actions...
        $title->long = 'Test title';
        $this->assertTrue($title->store($this->zdb));
        $title = new \Galette\Entity\Title($id); //reload

        //$this->assertSame('Test title 🤘', $title->long); //FIXME: works locally, fails on gh actions...
        $this->assertSame('Test title', $title->long);

        $title = new \Galette\Entity\Title($id); //reload
        $this->assertTrue($title->remove($this->zdb));

        $title = new \Galette\Entity\Title(\Galette\Entity\Title::MR);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You cannot delete Mr. or Mrs. titles!');
        $title->remove($this->zdb);
    }
}
