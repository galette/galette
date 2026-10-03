<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
     */
    public function canShow(Login $login): bool;

    /**
     * Can current logged-in user create object?
     *
     * @param Login $login Login instance
     */
    public function canCreate(Login $login): bool;

    /**
     * Can current logged-in user edit object?
     *
     * @param Login $login Login instance
     */
    public function canEdit(Login $login): bool;

    /**
     * Can current logged-in user delete object?
     *
     * @param Login $login Login instance
     */
    public function canDelete(Login $login): bool;
}
