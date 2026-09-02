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
class Document extends GaletteTestCase
{
    protected int $seed = 20240312213127;

    /**
     * Test document object
     */
    public function testObject(): void
    {
        $document = new \Galette\Entity\Document($this->zdb);

        //getters only
        $this->assertSame('', $document->getDocumentFilename());
        $this->assertSame($document->getDestDir(), $document->getURL());
        $this->assertNull($document->getID());

        //setters and getters
        $this->assertSame('', $document->getType());
        $this->assertInstanceOf(\Galette\Entity\Document::class, $document->setType('mytype'));
        $this->assertSame('mytype', $document->getType());

        $this->assertNull($document->getComment());
        $this->assertInstanceOf(\Galette\Entity\Document::class, $document->setComment('any comment'));
        $this->assertSame('any comment', $document->getComment());
    }
}
