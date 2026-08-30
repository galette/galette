<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Enums;

use Galette\Core\Authentication;

/**
 * Who gets to see a public page
 *
 * Values are the ones stored in the pref_publicpages_visibility_* preferences,
 * and are mirrored in tests/e2e/helpers/preferences.ts: they cannot change.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
enum PublicPageVisibility: int
{
    /** Anyone, logged in or not */
    case Everyone = 0;
    /** Members whose membership is up to date, plus staff and admins */
    case UpToDateMembers = 1;
    /** Staff and admins only */
    case StaffOnly = 2;
    /** Nobody; the page is not served at all */
    case Hidden = 3;
    /** Whatever the generic right says */
    case Inherit = 4;

    /**
     * Is the page visible to that user?
     *
     * Inheriting has no answer of its own, so the caller supplies the generic
     * right it defers to.
     *
     * @param Authentication $login   Authentication instance
     * @param ?callable      $inherit Resolves the generic right
     */
    public function isVisibleFor(Authentication $login, ?callable $inherit = null): bool
    {
        return match ($this) {
            self::Everyone => true,
            self::UpToDateMembers => $login->isUp2Date() || $login->isAdmin() || $login->isStaff(),
            self::StaffOnly => $login->isAdmin() || $login->isStaff(),
            self::Hidden => false,
            self::Inherit => $inherit !== null && (bool)$inherit(),
        };
    }
}
