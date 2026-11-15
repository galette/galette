<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\BaseGaletteTestCase;

/**
 * SQL scripts execution tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class InstallSql extends BaseGaletteTestCase
{
    protected string $app_mode = 'INSTALL';
    //DROP queries implicitly commit transactions on MySQL
    protected bool $db_transactions = false;

    /**
     * Errors on DROP and RENAME queries do not stop SQL scripts, other errors do
     */
    public function testExecuteSqlErrors(): void
    {
        $install = new \Galette\Core\Install();
        $errors = [];
        $install->setDbType(TYPE_DB, $errors);
        $install->setTablesPrefix(PREFIX_DB);
        $install->reinitReport();

        //next query still runs, even on PostgreSQL that aborts transactions on error
        $this->assertTrue(
            $install->executeSql($this->zdb, "DROP TABLE galette_does_not_exist;\nSELECT 1;")
        );
        $this->assertSame([true, true], array_column($install->getDbInstallReport(), 'res'));
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Error executing query: DROP TABLE ' . PREFIX_DB . 'does_not_exist');

        $install->reinitReport();
        $this->assertFalse(
            $install->executeSql($this->zdb, "SELECT * FROM galette_does_not_exist;\nSELECT 1;")
        );
        $this->assertSame([false], array_column($install->getDbInstallReport(), 'res'));
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Error executing query: SELECT * FROM ' . PREFIX_DB . 'does_not_exist');
        $this->expected_mysql_warnings[] = new \ArrayObject([
            'Level' => 'Error',
            'Code'  => 1146,
            'Message' => 'regex:/' . PREFIX_DB . 'does_not_exist.* doesn.t exist/'
        ]);

        $this->assertFalse($this->zdb->inTransaction());
    }
}
