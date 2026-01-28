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
