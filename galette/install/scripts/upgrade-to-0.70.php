<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Updates;

use Galette\Updater\AbstractUpdater;

/**
 * Galette 0.70 upgrade script
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class UpgradeTo070 extends AbstractUpdater
{
    protected ?string $db_version = '0.700';

    /**
     * Main constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setSqlScripts('0.70');
    }

    /**
     * Update instructions
     */
    protected function update(): bool
    {
        $this->zdb->convertToUTF($this->installer->getTablesPrefix());
        return true;
    }
}
