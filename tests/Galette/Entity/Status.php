<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Entity;

use Galette\Tests\GaletteTestCase;
use Laminas\Db\Adapter\Adapter;

/**
 * Status tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Status extends GaletteTestCase
{
    /**
     * Test status
     */
    public function testStatus(): void
    {
        global $i18n; // globals :(
        $i18n = $this->i18n;

        $status = $this->container->get(\Galette\Entity\Status::class);

        $this->assertSame(
            -2,
            $status->add('Active member', 81)
        );
        $this->expectLogEntry(\Analog\Analog::WARNING, 'A status with label `Active member` already exists');

        $this->assertTrue(
            $status->add('Test status', 81)
        );

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            [
                'text_orig'     => 'Test status'
            ]
        );
        $results = $this->zdb->execute($select);
        $result = (array)$results->current();

        $this->assertSame(
            'Test status',
            $result['text_orig']
        );

        $id = $status->id;

        $this->assertSame(
            \Galette\Entity\Status::ID_NOT_EXITS,
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
            [
                'text_orig'     => 'Tested status'
            ]
        );
        $results = $this->zdb->execute($select);
        $result = (array)$results->current();

        $this->assertSame(
            'Tested status',
            $result['text_orig']
        );

        $this->assertSame(
            \Galette\Entity\Status::ID_NOT_EXITS,
            $status->delete(42)
        );

        $this->assertTrue(
            $status->delete($id)
        );

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            [
                'text_orig'     => 'Tested status'
            ]
        );
        $results = $this->zdb->execute($select);
        $this->assertSame(0, $results->count());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You cannot delete default status!');
        $status->delete($status::DEFAULT_STATUS);
    }

    /**
     * Test getList
     */
    public function testGetList(): void
    {
        $status = $this->container->get(\Galette\Entity\Status::class);

        $this->logSuperAdmin();
        $list = $status->getList();
        $this->assertCount(10, $list);
        $this->login->logOut();

        //there are 10 status, but staff ones are not listed for normal users
        $list = $status->getList();
        $this->assertCount(6, $list);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($this->zdb->getSequenceName($status::TABLE, $status::PK));
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->assertGreaterThanOrEqual(10, $result->last_value, 'Incorrect status sequence');

            $this->zdb->db->query(
                'SELECT setval(\'' . $this->zdb->getSequenceName($status::TABLE, $status::PK, true) . '\', 1)',
                Adapter::QUERY_MODE_EXECUTE
            );
        }

        //reinstall status
        $status->installInit();

        $this->logSuperAdmin();
        $list = $status->getList();
        $this->assertCount(10, $list);
        $this->login->logOut();

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($this->zdb->getSequenceName($status::TABLE, $status::PK));
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->assertGreaterThanOrEqual(10, $result->last_value, 'Incorrect status sequence ' . $result->last_value);
        }
    }
}
