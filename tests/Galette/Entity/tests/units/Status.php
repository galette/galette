<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Status tests
 *
 * PHP version 5
 *
 * Copyright © 2018 The Galette Team
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
 * @copyright 2018 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2018-03-10
 */

namespace Galette\Entity\test\units;

use atoum;
use Laminas\Db\Adapter\Adapter;

/**
 * Status tests
 *
 * @category  Entity
 * @name      Status
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2018 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2018-04-15
 */
class Status extends atoum
{
    private $zdb;
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
        $this->i18n = new \Galette\Core\I18n(
            \Galette\Core\I18n::DEFAULT_LANG
        );
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
        $this->deleteStatus();
    }

    /**
     * Delete status
     *
     * @return void
     */
    private function deleteStatus()
    {
        if (is_array($this->remove) && count($this->remove) > 0) {
            $delete = $this->zdb->delete(\Galette\Entity\Status::TABLE);
            $delete->where->in(\Galette\Entity\Status::PK, $this->remove);
            $this->zdb->execute($delete);
        }

        //Clean logs
        $this->zdb->db->query(
            'TRUNCATE TABLE ' . PREFIX_DB . \Galette\Core\History::TABLE,
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
    }

    /**
     * Test status
     *
     * @return void
     */
    public function testStatus()
    {
        global $i18n; // globals :(
        $i18n = $this->i18n;

        $status = new \Galette\Entity\Status($this->zdb);

        $this->integer(
            $status->add('Active member', 81)
        )->isIdenticalTo(-2);

        $this->boolean(
            $status->add('Test status', 81)
        )->isTrue();

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            array(
                'text_orig'     => 'Test status'
            )
        );
        $results = $this->zdb->execute($select);
        $result = $results->current();

        $this->array((array)$result)
            ->string['text_orig']->isIdenticalTo('Test status');

        $this->remove[] = $status->id;
        $id = $status->id;

        $this->integer(
            $status->update(42, 'Active member', 81)
        )->isIdenticalTo(\Galette\Entity\Entitled::ID_NOT_EXITS);

        $this->boolean(
            $status->update($id, 'Tested status', 81)
        )->isTrue();

        $this->string(
            $status->getLabel($id)
        )->isIdenticalTo('Tested status');

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            array(
                'text_orig'     => 'Tested status'
            )
        );
        $results = $this->zdb->execute($select);
        $result = $results->current();

        $this->array((array)$result)
            ->string['text_orig']->isIdenticalTo('Tested status');

        $this->integer(
            $status->delete(42)
        )->isIdenticalTo(\Galette\Entity\Entitled::ID_NOT_EXITS);

        $this->exception(
            function () use ($status) {
                $status->delete($status::DEFAULT_STATUS);
            }
        )
            ->hasMessage('You cannot delete default status!')
            ->isInstanceOf('\RuntimeException');

        $this->boolean(
            $status->delete($id)
        )->isTrue();

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            array(
                'text_orig'     => 'Tested status'
            )
        );
        $results = $this->zdb->execute($select);
        $this->integer($results->count())->isIdenticalTo(0);
    }

    /**
     * Test getList
     *
     * @return void
     */
    public function testGetList()
    {
        $status = new \Galette\Entity\Status($this->zdb);

        $list = $status->getList();
        $this->array($list)->hasSize(10);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($status::TABLE . '_id_seq');
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->integer($result->last_value)->isGreaterThanOrEqualTo(10, 'Incorrect status sequence');

            $this->zdb->db->query(
                'SELECT setval(\'' . PREFIX_DB . $status::TABLE . '_id_seq\', 1)',
                Adapter::QUERY_MODE_EXECUTE
            );
        }

        //reinstall status
        $status->installInit();

        $list = $status->getList();
        $this->array($list)->hasSize(10);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($status::TABLE . '_id_seq');
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->integer($result->last_value)->isGreaterThanOrEqualTo(10, 'Incorrect status sequence ' . $result->last_value);
        }
    }
}
