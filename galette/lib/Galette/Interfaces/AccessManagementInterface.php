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

namespace Galette\Interfaces;

use Galette\Core\Login;

/**
 * Access management interface for entities
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
interface AccessManagementInterface
{
    /**
     * Can current logged-in user display object?
     *
     * @param Login $login Login instance
     *
     * @return boolean
     */
    public function canShow(Login $login): bool;

    /**
     * Can current logged-in user create object?
     *
     * @param Login $login Login instance
     *
     * @return boolean
     */
    public function canCreate(Login $login): bool;

    /**
     * Can current logged-in user edit object?
     *
     * @param Login $login Login instance
     *
     * @return boolean
     */
    public function canEdit(Login $login): bool;

    /**
     * Can current logged-in user delete object?
     *
     * @param Login $login Login instance
     *
     * @return boolean
     */
    public function canDelete(Login $login): bool;
}
