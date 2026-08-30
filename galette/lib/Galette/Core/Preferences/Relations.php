<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Preferences;

use Galette\Core\GaletteMail;
use Galette\Core\Galette;
use Galette\Enums\ContactSource;

/**
 * The rules a single preference cannot express
 *
 * Everything here needs the whole set: a mail method deciding which other
 * fields become mandatory, two ways of dating a membership that exclude each
 * other, a contact detail read from a staff member who must then exist.
 *
 * Order matters - errors are reported in the order the rules run.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class Relations
{
    /** @var array<string> */
    private array $errors = [];

    /**
     * Run every relation, in order
     *
     * @param array<string, mixed> $values        Submitted values
     * @param array<string, mixed> $insert_values Complete set, altered in place
     * @param array<string, int>   $required      Preferences that cannot be left empty
     *
     * @return array<string> Errors, in the order they were found
     */
    public function check(array $values, array &$insert_values, array $required): array
    {
        $this->errors = [];

        $this->checkMail($insert_values);
        $this->checkMembershipDates($insert_values);
        $this->checkOfferedMonths($insert_values);
        $this->checkRequiredValues($values, $required);
        $this->checkPasswordConfirmation($values, $insert_values);
        $this->checkStaffMemberSource(
            insert_values: $insert_values,
            source_field: 'pref_postal_address',
            member_field: 'pref_postal_staff_member',
            from_staff: [ContactSource::StaffMember->value],
            error: _T("You have to select a staff member to retrieve its address")
        );
        $this->checkStaffMemberSource(
            insert_values: $insert_values,
            source_field: 'pref_org_phone',
            member_field: 'pref_org_phone_staff_member',
            from_staff: [ContactSource::StaffMember->value, ContactSource::StaffMemberMobile->value],
            error: _T("You have to select a staff member to retrieve its phone number")
        );

        return $this->errors;
    }

    /**
     * Check what sending emails requires, according to the chosen method
     *
     * @param array<string, mixed> $insert_values Complete set of values
     */
    private function checkMail(array $insert_values): void
    {
        if (
            Galette::isDemo()
            || !isset($insert_values['pref_mail_method'])
            || $insert_values['pref_mail_method'] <= GaletteMail::METHOD_DISABLED
        ) {
            return;
        }

        if (
            !isset($insert_values['pref_email_nom'])
            || $insert_values['pref_email_nom'] == ''
        ) {
            $this->errors[] = _T("- You must indicate a sender name for emails!");
        }

        if (
            !isset($insert_values['pref_email'])
            || $insert_values['pref_email'] == ''
        ) {
            $this->errors[] = _T("- You must indicate an email address Galette should use to send emails!");
        }

        if (
            $insert_values['pref_mail_method'] == GaletteMail::METHOD_SMTP
            && (!isset($insert_values['pref_mail_smtp_host']) || $insert_values['pref_mail_smtp_host'] == '')
        ) {
            $this->errors[] = _T("- You must indicate the SMTP server you want to use!");
        }

        $needs_credentials = $insert_values['pref_mail_method'] == GaletteMail::METHOD_GMAIL
            || ($insert_values['pref_mail_method'] == GaletteMail::METHOD_SMTP
            && $insert_values['pref_mail_smtp_auth']);

        if (!$needs_credentials) {
            return;
        }

        if (
            !isset($insert_values['pref_mail_smtp_user'])
            || trim((string)$insert_values['pref_mail_smtp_user']) == ''
        ) {
            $this->errors[] = _T("- You must provide a login for SMTP authentication.");
        }

        if (
            !isset($insert_values['pref_mail_smtp_password'])
            || ($insert_values['pref_mail_smtp_password']) == ''
        ) {
            $this->errors[] = _T("- You must provide a password for SMTP authentication.");
        }
    }

    /**
     * A membership either extends for a duration or starts on a fixed date
     *
     * @param array<string, mixed> $insert_values Complete set of values
     */
    private function checkMembershipDates(array $insert_values): void
    {
        $has_beginning = isset($insert_values['pref_beg_membership'])
            && $insert_values['pref_beg_membership'] != '';
        $has_extension = isset($insert_values['pref_membership_ext'])
            && $insert_values['pref_membership_ext'] != '';

        if (!$has_beginning && !$has_extension) {
            $this->errors[] = _T("- You must indicate a membership extension or a beginning of membership.");
        } elseif ($has_beginning && $has_extension) {
            $this->errors[] = _T("- Default membership extension and beginning of membership are mutually exclusive.");
        }
    }

    /**
     * Offered months only make sense along a fixed beginning of membership
     *
     * @param array<string, mixed> $insert_values Complete set of values
     */
    private function checkOfferedMonths(array $insert_values): void
    {
        if (
            isset($insert_values['pref_membership_offermonths'])
            && (int)$insert_values['pref_membership_offermonths'] > 0
            && isset($insert_values['pref_membership_ext'])
            && $insert_values['pref_membership_ext'] != ''
        ) {
            $this->errors[] = _T("- Offering months is only compatible with beginning of membership.");
        }
    }

    /**
     * Check required preferences are all filled
     *
     * Reads the submitted payload rather than the completed set: a preference
     * missing from the payload is blank there, and would not be reported.
     *
     * @param array<string, mixed> $values   Submitted values
     * @param array<string, int>   $required Preferences that cannot be left empty
     */
    private function checkRequiredValues(array $values, array $required): void
    {
        foreach (array_keys($required) as $val) {
            if (!isset($values[$val]) || is_string($values[$val]) && trim($values[$val]) == '') {
                $this->errors[] = sprintf(
                    //TRANS: parameter is a field name
                    _T('- Mandatory field %1$s empty.'),
                    $val
                );
            }
        }
    }

    /**
     * Check the superadmin password against its confirmation
     *
     * Hashing happens later, in Preferences::__set().
     *
     * @param array<string, mixed> $values        Submitted values
     * @param array<string, mixed> $insert_values Complete set of values
     */
    private function checkPasswordConfirmation(array $values, array $insert_values): void
    {
        if (
            !Galette::isDemo()
            && isset($values['pref_admin_pass_check'])
            && strcmp((string)$insert_values['pref_admin_pass'], (string)$values['pref_admin_pass_check']) != 0
        ) {
            $this->errors[] = _T("Passwords mismatch");
        }
    }

    /**
     * Check a preference taken from a staff member designates one
     *
     * When the value is read from the preferences themselves, the staff member
     * is dropped from the set rather than blanked, so the stored one survives.
     *
     * @param array<string, mixed> $insert_values Complete set, altered in place
     * @param string               $source_field  Preference telling where to read from
     * @param string               $member_field  Preference holding the staff member
     * @param array<int>           $from_staff    Values meaning "from a staff member"
     * @param string               $error         Message when no staff member is set
     */
    private function checkStaffMemberSource(
        array &$insert_values,
        string $source_field,
        string $member_field,
        array $from_staff,
        string $error
    ): void {
        if (!isset($insert_values[$source_field])) {
            return;
        }

        $value = $insert_values[$source_field];
        if ($value == ContactSource::Preferences->value) {
            unset($insert_values[$member_field]);
        } elseif (in_array($value, $from_staff)) {
            if (!isset($insert_values[$member_field]) || $insert_values[$member_field] < 1) {
                $this->errors[] = $error;
            }
        }
    }
}
