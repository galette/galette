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
 * Payment type tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PaymentType extends GaletteTestCase
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
     * Test payment type
     */
    public function testPaymentType(): void
    {
        global $i18n; // globals :(
        $i18n = $this->i18n;

        $type = new \Galette\Entity\PaymentType($this->zdb);

        $type->name = 'Test payment type';
        $this->assertTrue($type->store());

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            [
                'text_orig'     => 'Test payment type'
            ]
        );
        $results = $this->zdb->execute($select);
        $result = (array)$results->current();

        $this->assertSame('Test payment type', $result['text_orig']);

        $id = $type->id;

        $type = new \Galette\Entity\PaymentType($this->zdb, $id);
        $type->name = 'Changed test payment type';
        $this->assertTrue($type->store());

        $type = new \Galette\Entity\PaymentType($this->zdb, $id);
        $this->assertSame('Changed test payment type', $type->getName());

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            [
                'text_orig'     => 'Changed test payment type'
            ]
        );
        $results = $this->zdb->execute($select);
        $this->assertSame(count($this->i18n->getArrayList()), count($results));

        $type = new \Galette\Entity\PaymentType($this->zdb, $id);
        $this->assertTrue($type->remove());

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            [
                'text_orig'     => 'Test payment type'
            ]
        );
        $results = $this->zdb->execute($select);
        $this->assertSame(0, $results->count());

        $type = new \Galette\Entity\PaymentType($this->zdb, \Galette\Entity\PaymentType::CASH);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You cannot delete system payment types!');
        $type->remove();
    }
}
