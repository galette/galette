<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Enums;

/**
 * How strong a member password has to be
 *
 * Values are the ones stored in pref_password_strength and are ordered, so
 * they can be compared: the higher, the stricter.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
enum PasswordStrength: int
{
    case None = 0;
    case Weak = 1;
    case Medium = 2;
    case Strong = 3;
    case VeryStrong = 4;

    /**
     * Is that level at least the given one?
     *
     * @param self $level Level to compare to
     */
    public function isAtLeast(self $level): bool
    {
        return $this->value >= $level->value;
    }
}
