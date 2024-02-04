<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

namespace Galette\Entity\test\units;

use PHPUnit\Framework\TestCase;
use Laminas\Db\Adapter\Adapter;

/**
 * Contributions types tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ContributionsTypes extends TestCase
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
        $this->deleteTypes();
    }

    /**
     * Delete contributions types
     *
     * @return void
     */
    private function deleteTypes()
    {
        if (is_array($this->remove) && count($this->remove) > 0) {
            $delete = $this->zdb->delete(\Galette\Entity\ContributionsTypes::TABLE);
            $delete->where->in(\Galette\Entity\ContributionsTypes::PK, $this->remove);
            $this->zdb->execute($delete);
        }

        //Clean logs
        $this->zdb->db->query(
            'TRUNCATE TABLE ' . PREFIX_DB . \Galette\Core\History::TABLE,
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
    }

    /**
     * Test contributions types
     *
     * @return void
     */
    public function testContributionsTypes()
    {
        global $i18n; // globals :(
        $i18n = $this->i18n;

        $ctype = new \Galette\Entity\ContributionsTypes($this->zdb);

        $this->assertSame(
            -2,
            $ctype->add('annual fee', 0)
        );

        $this->assertTrue(
            $ctype->add('Test contribution type', 0)
        );

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            array(
                'text_orig'     => 'Test contribution type'
            )
        );
        $results = $this->zdb->execute($select);
        $result = (array)$results->current();

        $this->assertSame(
            'Test contribution type',
            $result['text_orig']
        );

        $this->remove[] = $ctype->id;
        $id = $ctype->id;

        $this->assertSame(
            \Galette\Entity\ContributionsTypes::ID_NOT_EXITS,
            $ctype->update(42, 'annual fee', 0)
        );

        $this->assertTrue(
            $ctype->update($id, 'Tested contribution type', 1)
        );

        $this->assertSame(
            'Tested contribution type',
            $ctype->getLabel($id)
        );

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            array(
                'text_orig'     => 'Tested contribution type'
            )
        );
        $results = $this->zdb->execute($select);
        $result = (array)$results->current();

        $this->assertSame(
            'Tested contribution type',
            $result['text_orig']
        );

        $this->assertSame(
            \Galette\Entity\ContributionsTypes::ID_NOT_EXITS,
            $ctype->delete(42)
        );

        /*$this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You cannot delete default contribution type!');
        $status->delete($status::DEFAULT_STATUS);*/

        $this->assertTrue(
            $ctype->delete($id)
        );

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            array(
                'text_orig'     => 'Tested contribution type'
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
        $ctypes = new \Galette\Entity\ContributionsTypes($this->zdb);

        $list = $ctypes->getList();
        $this->assertCount(7, $list);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($ctypes::TABLE . '_id_seq');
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->assertGreaterThanOrEqual(7, $result->last_value, 'Incorrect contributions types sequence');

            $this->zdb->db->query(
                'SELECT setval(\'' . PREFIX_DB . $ctypes::TABLE . '_id_seq\', 1)',
                Adapter::QUERY_MODE_EXECUTE
            );
        }

        //reinstall status
        $ctypes->installInit();

        $list = $ctypes->getList();
        $this->assertCount(7, $list);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($ctypes::TABLE . '_id_seq');
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->assertGreaterThanOrEqual(7, $result->last_value, 'Incorrect contributions types sequence ' . $result->last_value);
        }
    }
}
