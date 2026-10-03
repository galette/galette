<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Enums;

/**
 * Where the association contact details are read from
 *
 * Shared by pref_postal_address and pref_org_phone, which have always used the
 * same values for the same meanings. The mobile case only makes sense for a
 * phone number.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
enum ContactSource: int
{
    /** From the preferences themselves */
    case Preferences = 0;
    /** From the designated staff member */
    case StaffMember = 1;
    /** From the designated staff member, mobile number */
    case StaffMemberMobile = 2;

    /**
     * Does it designate a staff member?
     */
    public function isStaffMember(): bool
    {
        return $this !== self::Preferences;
    }
}
