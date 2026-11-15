<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteDbUpgradePlugin\Updates;

use Galette\Updater\AbstractUpdater;

/**
 * Upgrade class from 0.2 to 0.3: SQL script, then data
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class UpgradeTo03 extends AbstractUpdater
{
    protected ?string $db_version = '0.3';

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
        return !\GaletteDbUpgradePlugin\PluginGalettePlugdbupgrade::$upgrade_fails;
    }

    /**
     * Post stuff, once SQL script has run
     */
    protected function postUpdate(): bool
    {
        $insert = $this->zdb->insert('plugin_db_upgrade_detail_test');
        $insert->values([
            'upgrade_id'    => 1,
            'note'          => _T('Upgraded note', 'plugdbupgrade'),
            'kind'          => 'upgrade'
        ]);
        $this->zdb->execute($insert);
        return true;
    }
}
