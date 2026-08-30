<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Preferences;

use Galette\Core\Db;
use Galette\Entity\Adherent;
use Galette\Enums\ContactSource;

use function Safe\preg_replace;

/**
 * How the association presents itself
 *
 * A postal address and a phone number, each of which the administrator may
 * either type in the preferences or borrow from a staff member. That second
 * case is why reading them needs the database, which is the whole reason they
 * do not belong among plain preference accessors.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final readonly class Identity
{
    /**
     * Constructor
     *
     * @param Db $zdb Db instance
     */
    public function __construct(private Db $zdb)
    {
    }

    /**
     * Get the association postal address
     *
     * @param array<string, mixed> $prefs Stored preference values
     */
    public function getPostalAddress(array $prefs): string
    {
        $regs = [
            '/%name/',
            '/%complement/',
            '/%address/',
            '/%zip/',
            '/%town/',
            '/%country/',
        ];

        if (ContactSource::tryFrom((int)$prefs['pref_postal_address']) === ContactSource::Preferences) {
            $_address = $prefs['pref_adresse'];
            if ($prefs['pref_adresse2']) {
                $_address .= "\n" . $prefs['pref_adresse2'];
            }
            $_country = $prefs['pref_pays'] != '' ? '- ' . $prefs['pref_pays'] : '';
            $replacements = [
                $prefs['pref_nom'],
                "\n",
                $_address,
                $prefs['pref_cp'],
                $prefs['pref_ville'],
                $_country
            ];
        } else {
            //get selected staff member address
            $adh = new Adherent($this->zdb, (int)$prefs['pref_postal_staff_member']);
            $_complement = sprintf(
                //TRANS: first parameter is name, second is status
                _T('%1$s association\'s %2$s'),
                $prefs['pref_nom'],
                $adh->sstatus,
            ) . "\n";
            $_address = $adh->address;
            $_country = $adh->country != '' ? '- ' . $adh->country : '';

            $replacements = [
                $adh->sfullname . "\n",
                $_complement,
                $_address,
                $adh->zipcode,
                $adh->town,
                $_country
            ];
        }

        return preg_replace(
            $regs,
            $replacements,
            "%name%complement%address\n%zip %town %country"
        );
    }

    /**
     * Get the association phone number
     *
     * @param array<string, mixed> $prefs Stored preference values
     */
    public function getPhoneNumber(array $prefs): string
    {
        $source = ContactSource::tryFrom((int)$prefs['pref_org_phone']);

        if ($source === null || !$source->isStaffMember()) {
            return $prefs['pref_org_phone_number'] ?? '';
        }

        //get selected staff phone number
        $adh = new Adherent($this->zdb, (int)$prefs['pref_org_phone_staff_member']);

        return ($source === ContactSource::StaffMemberMobile ? $adh->gsm : $adh->phone) ?? '';
    }
}
