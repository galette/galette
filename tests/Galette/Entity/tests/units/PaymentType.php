<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Payment type tests
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
 * @since     2019-12-15
 */

namespace Galette\Entity\test\units;

use PHPUnit\Framework\TestCase;

/**
 * Payment type tests
 *
 * @category  Entity
 * @name      PaymentType
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2019-12-15
 */
class PaymentType extends TestCase
{
    private \Galette\Core\Db $zdb;
    private \Galette\Core\Preferences $preferences;
    private \Galette\Core\Login $login;
    private array $remove = [];
    private \Galette\Core\I18n $i18n;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->zdb = new \Galette\Core\Db();
        $this->preferences = new \Galette\Core\Preferences($this->zdb);
        $this->i18n = new \Galette\Core\I18n(
            \Galette\Core\I18n::DEFAULT_LANG
        );
        $this->login = new \Galette\Core\Login($this->zdb, $this->i18n);

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
        if (TYPE_DB === 'mysql') {
            $this->assertSame([], $this->zdb->getWarnings());
        }
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
            $delete->where->in(\Galette\Entity\PaymentType::PK, $this->remove);
            $this->zdb->execute($delete);
        }

        //Clean logs
        $this->zdb->db->query(
            'TRUNCATE TABLE ' . PREFIX_DB . \Galette\Core\History::TABLE,
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
    }

    /**
     * Test payment type
     *
     * @return void
     */
    public function testPaymentType()
    {
        global $i18n; // globals :(
        $i18n = $this->i18n;

        $type = new \Galette\Entity\PaymentType($this->zdb);

        $type->name = 'Test payment type';
        $this->assertTrue($type->store());

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            array(
                'text_orig'     => 'Test payment type'
            )
        );
        $results = $this->zdb->execute($select);
        $result = (array)$results->current();

        $this->assertSame('Test payment type', $result['text_orig']);

        $id = $type->id;
        $this->remove[] = $id;

        $type = new \Galette\Entity\PaymentType($this->zdb, $id);
        $type->name = 'Changed test payment type';
        $this->assertTrue($type->store());

        $type = new \Galette\Entity\PaymentType($this->zdb, $id);
        $this->assertSame('Changed test payment type', $type->getName());

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            array(
                'text_orig'     => 'Changed test payment type'
            )
        );
        $results = $this->zdb->execute($select);
        $this->assertSame(count($this->i18n->getArrayList()), count($results));

        $type = new \Galette\Entity\PaymentType($this->zdb, \Galette\Entity\PaymentType::CASH);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You cannot delete system payment types!');
        $type->remove();

        $type = new \Galette\Entity\PaymentType($this->zdb, $id);
        $this->assertTrue($type->remove());

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            array(
                'text_orig'     => 'Test payment type'
            )
        );
        $results = $this->zdb->execute($select);
        $this->assertSame(0, $results->count());
    }
}
