<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

declare(strict_types=1);

namespace Galette\Core;

use Analog\Analog;

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
     *
     * @return boolean
     */
    public function testDbConnexion(): bool
    {
        //installing plugin, DB connection is already ok
        return true;
    }

    /**
     * Go back to previous step
     *
     * @return void
     */
    public function atPreviousStep(): void
    {
        if ($this->step > 0) {
            if (
                $this->step - 1 !== self::STEP_DB_INSTALL
                && $this->step !== self::STEP_END
            ) {
                if ($this->step === self::STEP_DB_INSTALL) {
                    $this->step = self::STEP_DB_CHECKS;
                } else {
                    if ($this->step === self::STEP_DB_UPGRADE) {
                        $this->setInstalledVersion(null);
                    }
                    $this->step -= 1;
                }
            } else {
                $msg = null;
                if ($this->step === self::STEP_END) {
                    $msg = 'Ok man, install is finished already!';
                } else {
                    $msg = 'It is forbidden to rerun database install!';
                }
                Analog::log($msg, Analog::WARNING);
            }
        }
    }

    /**
     * Initialize Galette relevant objects
     *
     * @param I18n  $i18n  I18n
     * @param Db    $zdb   Database instance
     * @param Login $login Logged in instance
     *
     * @return boolean
     */
    public function initObjects(I18n $i18n, Db $zdb, Login $login): bool
    {
        return false;
    }
}
