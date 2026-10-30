<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\BaseGaletteTestCase;

use function Safe\ob_get_clean;
use function Safe\ob_start;

/**
 * Database permissions checks tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class InstallDbPermissions extends BaseGaletteTestCase
{
    protected string $app_mode = 'INSTALL';

    /**
     * Permissions checks create and drop a table; on MySQL, this commits
     * any running transaction.
     */
    protected bool $db_transactions = false;

    /**
     * Test database permissions checks, and their rendering in installer
     */
    public function testCheckDbPermissions(): void
    {
        $install = new \Galette\Core\Install();
        $errors = [];
        $install->setDbType(TYPE_DB, $errors);
        $install->setDsn(
            host: HOST_DB,
            port: PORT_DB,
            name: NAME_DB,
            user: USER_DB,
            pass: PWD_DB
        );
        $install->setMode(\Galette\Core\Install::INSTALL);

        $checks = $install->checkDbPermissions($this->zdb);
        $this->assertTrue($checks['supported']);
        $this->assertTrue($checks['ok']);
        $this->assertSame(
            [
                'CREATE operation allowed',
                'INSERT operation allowed',
                'UPDATE operation allowed',
                'SELECT operation allowed',
                'DELETE operation allowed',
                'DROP operation allowed',
            ],
            array_column($checks['checks'], 'message')
        );

        //ALTER is checked on upgrade only
        $install->setMode(\Galette\Core\Install::UPDATE);
        $checks = $install->checkDbPermissions($this->zdb);
        $this->assertTrue($checks['ok']);
        $this->assertContains('ALTER operation allowed', array_column($checks['checks'], 'message'));

        //installer page
        $zdb = $this->zdb; //phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- not used here, but from include
        $i18n = $this->container->get(\Galette\Core\I18n::class); //phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- not used here, but from include
        ob_start();
        include GALETTE_ROOT . '/install/steps/db_checks.php';
        $output = ob_get_clean();
        $this->assertStringContainsString('Permissions to database are OK.', $output);
        $this->assertStringContainsString('ALTER operation allowed', $output);
        $this->assertStringContainsString('<input type="hidden" name="install_dbperms_ok" value="1"/>', $output);
        $this->assertStringNotContainsString('disabled="disabled"', $output);
    }
}
