<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Payment type tests
 *
 * PHP version 5
 *
 * Copyright © 2019 The Galette Team
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
 * @copyright 2019 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2019-12-15
 */

namespace Galette\Entity\test\units;

use atoum;

/**
 * Payment type tests
 *
 * @category  Entity
 * @name      PaymentType
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2019-12-15
 */
class PaymentType extends atoum
{
    private $zdb;
    private $preferences;
    private $login;
    private $remove = [];
    private $i18n;

    /**
     * Set up tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function beforeTestMethod($method)
    {
        $this->zdb = new \Galette\Core\Db();
        $this->preferences = new \Galette\Core\Preferences($this->zdb);
        $this->i18n = new \Galette\Core\I18n(
            \Galette\Core\I18n::DEFAULT_LANG
        );
        $this->login = new \Galette\Core\Login($this->zdb, $this->i18n);

        $types = new \Galette\Repository\PaymentTypes($this->zdb, $this->preferences, $this->login);
        $res = $types->installInit(false);
        $this->boolean($res)->isTrue();
    }

    /**
     * Tear down tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function afterTestMethod($method)
    {
        if (TYPE_DB === 'mysql') {
            $this->array($this->zdb->getWarnings())->isIdenticalTo([]);
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
        $this->boolean($type->store())->isTrue();

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            array(
                'text_orig'     => 'Test payment type'
            )
        );
        $results = $this->zdb->execute($select);
        $result = $results->current();

        $this->array((array)$result)
            ->string['text_orig']->isIdenticalTo('Test payment type');

        $id = $type->id;
        $this->remove[] = $id;

        $type = new \Galette\Entity\PaymentType($this->zdb, $id);
        $type->name = 'Changed test payment type';
        $this->boolean($type->store())->isTrue();

        $type = new \Galette\Entity\PaymentType($this->zdb, $id);
        $this->string($type->getName())->isIdenticalTo('Changed test payment type');

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            array(
                'text_orig'     => 'Changed test payment type'
            )
        );
        $results = $this->zdb->execute($select);
        $this->integer(count($results))->isIdenticalTo(count($this->i18n->getArrayList()));

        $type = new \Galette\Entity\PaymentType($this->zdb, \Galette\Entity\PaymentType::CASH);
        $this->exception(
            function () use ($type) {
                $type->remove();
            }
        )
            ->hasMessage('You cannot delete system payment types!')
            ->isInstanceOf('\RuntimeException');

        $type = new \Galette\Entity\PaymentType($this->zdb, $id);
        $this->boolean($type->remove())->isTrue();

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            array(
                'text_orig'     => 'Test payment type'
            )
        );
        $results = $this->zdb->execute($select);
        $this->integer($results->count())->isIdenticalTo(0);
    }
}
