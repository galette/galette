<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Core\RowsCache;
use Galette\Entity\Status;
use Galette\Tests\GaletteTestCase;

/**
 * Reference rows cache tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RowsCacheTest extends GaletteTestCase
{
    private string $status_table;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->status_table = PREFIX_DB . Status::TABLE;
    }

    /**
     * Rows are read once, and copies are returned
     */
    public function testReadOnce(): void
    {
        $this->assertSame(
            1,
            $this->countQueries(
                function (): void {
                    $this->assertSame('Non-member', RowsCache::get(
                        zdb: $this->zdb,
                        table: Status::TABLE,
                        pk: Status::PK,
                        id: 9
                    )['libelle_statut'] ?? null);
                    $this->assertSame('President', RowsCache::get(
                        zdb: $this->zdb,
                        table: Status::TABLE,
                        pk: Status::PK,
                        id: 1
                    )['libelle_statut'] ?? null);
                    $this->assertNull(RowsCache::get(
                        zdb: $this->zdb,
                        table: Status::TABLE,
                        pk: Status::PK,
                        id: 999
                    ));
                },
                $this->status_table
            )
        );

        $row = RowsCache::get(
            zdb: $this->zdb,
            table: Status::TABLE,
            pk: Status::PK,
            id: 9
        );
        $this->assertNotNull($row);
        $row['libelle_statut'] = 'Changed';
        $this->assertSame('Non-member', RowsCache::get(
            zdb: $this->zdb,
            table: Status::TABLE,
            pk: Status::PK,
            id: 9
        )['libelle_statut'] ?? null);
    }

    /**
     * Query results are read once, and copies are returned
     */
    public function testQuery(): void
    {
        $runs = 0;
        $query = function () use (&$runs): iterable {
            ++$runs;
            $select = $this->zdb->select(Status::TABLE);
            $select->where->lessThan('priorite_statut', 20);
            $select->order('priorite_statut');
            return $this->zdb->execute($select);
        };

        $rows = RowsCache::query(Status::TABLE, 'board', $query);
        $this->assertSame(['President', 'Vice-president', 'Treasurer'], array_map(fn($row) => $row['libelle_statut'], $rows));
        $rows[0]['libelle_statut'] = 'Changed';
        $rows = RowsCache::query(Status::TABLE, 'board', $query);
        $this->assertSame('President', $rows[0]['libelle_statut']);
        $this->assertSame(1, $runs);

        //writing to the table forgets results
        $update = $this->zdb->update(Status::TABLE);
        $update->set(['priorite_statut' => 15])->where([Status::PK => 3]);
        $this->zdb->execute($update);
        $rows = RowsCache::query(Status::TABLE, 'board', $query);
        $this->assertSame(2, $runs);
        $this->assertCount(4, $rows);
    }

    /**
     * Writes and rollbacks forget rows
     */
    public function testForget(): void
    {
        $status = new Status($this->zdb);
        $this->assertSame('Non-member', $status->getLabel(9, translated: false));

        //write from an entity
        $this->assertTrue($status->update(9, 'Cached member', 120));
        $this->assertSame('Cached member', $status->getLabel(9, translated: false));

        //write without the entity
        $update = $this->zdb->update(Status::TABLE);
        $update->set(['libelle_statut' => 'Raw member'])->where([Status::PK => 9]);
        $this->zdb->execute($update);
        $this->assertSame('Raw member', $status->getLabel(9, translated: false));

        //prepared writes too
        $update = $this->zdb->update(Status::TABLE);
        $update->set(['libelle_statut' => 'Prepared member'])->where([Status::PK => 9]);
        $this->zdb->sql->prepareStatementForSqlObject($update)->execute();
        $this->assertSame('Prepared member', $status->getLabel(9, translated: false));

        //rows read in a transaction that is rolled back are forgotten
        $this->zdb->rollback();
        $this->assertSame('Non-member', $status->getLabel(9, translated: false));
        $this->zdb->beginTransaction();
    }
}
