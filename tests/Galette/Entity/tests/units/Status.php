<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Status tests
 *
 * PHP version 5
 *
 * Copyright © 2018-2023 The Galette Team
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
 * @copyright 2018-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2018-03-10
 */

namespace Galette\Entity\test\units;

use PHPUnit\Framework\TestCase;
use Laminas\Db\Adapter\Adapter;

/**
 * Status tests
 *
 * @category  Entity
 * @name      Status
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2018-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2018-04-15
 */
class Status extends TestCase
{
    private \Galette\Core\Db $zdb;
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
        $this->i18n = new \Galette\Core\I18n(
            \Galette\Core\I18n::DEFAULT_LANG
        );
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

        $this->assertSame(
            -2,
            $status->add('Active member', 81)
        );

        $this->assertTrue(
            $status->add('Test status', 81)
        );

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            array(
                'text_orig'     => 'Test status'
            )
        );
        $results = $this->zdb->execute($select);
        $result = (array)$results->current();

        $this->assertSame(
            'Test status',
            $result['text_orig']
        );

        $this->remove[] = $status->id;
        $id = $status->id;

        $this->assertSame(
            \Galette\Entity\Entitled::ID_NOT_EXITS,
            $status->update(42, 'Active member', 81)
        );

        $this->assertTrue(
            $status->update($id, 'Tested status', 81)
        );

        $this->assertSame(
            'Tested status',
            $status->getLabel($id)
        );

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            array(
                'text_orig'     => 'Tested status'
            )
        );
        $results = $this->zdb->execute($select);
        $result = (array)$results->current();

        $this->assertSame(
            'Tested status',
            $result['text_orig']
        );

        $this->assertSame(
            \Galette\Entity\Entitled::ID_NOT_EXITS,
            $status->delete(42)
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You cannot delete default status!');
        $status->delete($status::DEFAULT_STATUS);

        $this->assertTrue(
            $status->delete($id)
        );

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            array(
                'text_orig'     => 'Tested status'
            )
        );
        $results = $this->zdb->execute($select);
        $this->assertSame(0, $results->count());
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
        $this->assertCount(10, $list);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($status::TABLE . '_id_seq');
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->assertGreaterThanOrEqual(10, $result->last_value, 'Incorrect status sequence');

            $this->zdb->db->query(
                'SELECT setval(\'' . PREFIX_DB . $status::TABLE . '_id_seq\', 1)',
                Adapter::QUERY_MODE_EXECUTE
            );
        }

        //reinstall status
        $status->installInit();

        $list = $status->getList();
        $this->assertCount(10, $list);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($status::TABLE . '_id_seq');
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->assertGreaterThanOrEqual(10, $result->last_value, 'Incorrect status sequence ' . $result->last_value);
        }
    }
}
