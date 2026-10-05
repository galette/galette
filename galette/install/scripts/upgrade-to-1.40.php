<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Updates;

use Galette\Access\Permissions;
use Galette\Access\Roles;
use Galette\Core\Preferences;
use Galette\Updater\AbstractUpdater;

/**
 * Galette 1.4.0 upgrade script
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class UpgradeTo140 extends AbstractUpdater
{
    protected ?string $db_version = '1.40';

    /**
     * Main constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setSqlScripts($this->db_version);
    }

    /**
     * Update instructions
     */
    protected function update(): bool
    {
        return true;
    }

    /**
     * Post stuff, if any.
     * Will be executed at the end.
     */
    protected function postUpdate(): bool
    {
        //system roles get what current groups managers preferences allow
        $roles = new Roles($this->zdb, new Permissions());
        $roles->installInit(new Preferences($this->zdb));
        $this->addReportEntry(_T('System roles have been created.'), self::REPORT_SUCCESS);
        return true;
    }
}
