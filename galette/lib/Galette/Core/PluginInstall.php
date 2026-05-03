<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

/**
 * Galette plugin installation
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PluginInstall extends Install
{
    /**
     * Main constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->atTypeStep();
    }

    /**
     * Test database connection
     */
    public function testDbConnexion(): bool
    {
        //installing plugin, DB connection is already ok
        return true;
    }

    /**
     * Initialize Galette relevant objects
     *
     * @param I18n  $i18n  I18n
     * @param Db    $zdb   Database instance
     * @param Login $login Logged in instance
     */
    public function initObjects(I18n $i18n, Db $zdb, Login $login): bool
    {
        return false;
    }
}
