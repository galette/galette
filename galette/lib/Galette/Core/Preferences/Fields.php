<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Preferences;

use Analog\Analog;
use Galette\Core\Galette;
use Galette\Core\GaletteMail;
use Galette\Core\Login;
use Galette\Core\Preferences;
use Galette\Core\PreferencesSchema;
use Galette\Util\Password;

use function Safe\preg_match;

/**
 * What each preference has to look like on its own
 *
 * The schema says what a preference is; this says whether a submitted value
 * qualifies, and hands back the normalised one. A rule needing more than the
 * single value belongs in Relations instead.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class Fields
{
    /** @var array<string> */
    private array $errors = [];

    /**
     * Constructor
     *
     * @param Assets $assets Assets helper, for the HTML fields
     */
    public function __construct(private readonly Assets $assets)
    {
    }

    /**
     * Check a value against what its type requires, and normalise it
     *
     * @param string      $fieldname   Preference name
     * @param mixed       $value       Submitted value
     * @param Preferences $preferences Current preferences, the password strength is judged against them
     * @param Login|null  $login       Logged in user, to tell an already taken login
     */
    public function validate(
        string $fieldname,
        mixed $value,
        Preferences $preferences,
        ?Login $login = null
    ): mixed {
        $this->errors = [];

        $entry = PreferencesSchema::get($fieldname);
        if ($entry === null) {
            //unknown preference, it may come from a plugin: leave it untouched
            return $value;
        }

        return match ($entry['type']) {
            PreferencesSchema::TYPE_EMAIL,
            PreferencesSchema::TYPE_EMAILS => $this->validateEmails($fieldname, $value),
            PreferencesSchema::TYPE_INT => $this->validateNumber($fieldname, $entry, $value),
            PreferencesSchema::TYPE_COLOR => $this->validateColor($fieldname, $value),
            PreferencesSchema::TYPE_LOGIN => $this->validateAdminLogin($entry, $value, $login),
            PreferencesSchema::TYPE_PASSWORD => $this->validateAdminPass($value, $preferences),
            PreferencesSchema::TYPE_DATE_MD => $this->validateBegMembership($value),
            PreferencesSchema::TYPE_YEAR => $this->validateCardYear($value),
            PreferencesSchema::TYPE_URL => $this->validateWebUrl($value),
            PreferencesSchema::TYPE_HTML => $this->assets->cleanHtmlValue(value: (string)$value),
            default => $value,
        };
    }

    /**
     * What the last call to validate() found wrong
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check email validity
     *
     * A TYPE_EMAILS field accepts a comma-separated list of valid addresses,
     * such as "mail@domain.com,other@mail.com".
     *
     * @param string $fieldname Field name
     * @param mixed  $value     Value to check
     */
    private function validateEmails(string $fieldname, mixed $value): mixed
    {
        $addresses = [];
        if (trim((string)$value) != '') {
            $addresses = PreferencesSchema::getType($fieldname) === PreferencesSchema::TYPE_EMAILS
                ? explode(',', (string)$value)
                : [$value];
        }

        foreach ($addresses as $address) {
            if (!GaletteMail::isValidEmail($address)) {
                $msg = str_replace('%s', $address, _T("Invalid E-Mail address: %s"));
                Analog::log($msg, Analog::WARNING);
                $this->errors[] = $msg;
            }
        }

        return $value;
    }

    /**
     * Check a number against the bounds declared in the schema
     *
     * Integers without any bound are not validated, only cast on read.
     *
     * @param string               $fieldname Field name
     * @param array<string, mixed> $entry     Schema entry
     * @param mixed                $value     Value to check
     */
    private function validateNumber(string $fieldname, array $entry, mixed $value): mixed
    {
        if (!isset($entry['min']) && !isset($entry['max'])) {
            return $value;
        }

        if (
            !is_numeric($value)
            || (isset($entry['min']) && $value < $entry['min'])
            || (isset($entry['max']) && $value > $entry['max'])
        ) {
            $this->errors[] = PreferencesSchema::getErrorMessage((string)$entry['error'], $fieldname);
        }

        return $value;
    }

    /**
     * Normalize a color
     *
     * An unparsable color is not an error: strip background colors fall back
     * to black, and the text color to white.
     *
     * @param string $fieldname Field name
     * @param mixed  $value     Value to normalize
     */
    private function validateColor(string $fieldname, mixed $value): string
    {
        $matches = [];
        if (!preg_match("/^(#)?([0-9A-F]{6})$/i", (string)$value, $matches)) {
            return $fieldname == 'pref_card_tcol' ? '#FFFFFF' : '#000000';
        }

        return '#' . $matches[2];
    }

    /**
     * Check superadmin login
     *
     * @param array<string, mixed> $entry Schema entry
     * @param mixed                $value Value to check
     * @param Login|null           $login Logged in user
     */
    private function validateAdminLogin(array $entry, mixed $value, ?Login $login): mixed
    {
        if (Galette::isDemo()) {
            Analog::log(
                'Trying to set superadmin login while in DEMO.',
                Analog::WARNING
            );
        } elseif (strlen((string)$value) < (int)$entry['minlength']) {
            $this->errors[] = PreferencesSchema::getErrorMessage((string)$entry['error']);
        } elseif ($login?->loginExists($value)) {
            //check if login is already taken
            $this->errors[] = PreferencesSchema::getErrorMessage(PreferencesSchema::ERR_LOGIN_EXISTS);
        }

        return $value;
    }

    /**
     * Check superadmin password strength
     *
     * @param mixed       $value       Value to check
     * @param Preferences $preferences Current preferences
     */
    private function validateAdminPass(mixed $value, Preferences $preferences): mixed
    {
        if (Galette::isDemo()) {
            Analog::log(
                'Trying to set superadmin pass while in DEMO.',
                Analog::WARNING
            );
            return $value;
        }

        $pwcheck = new Password($preferences);
        $pwcheck->addPersonalInformation([$preferences->pref_admin_login]);
        if (!$pwcheck->isValid($value)) {
            $this->errors = array_merge(
                $this->errors,
                $pwcheck->getErrors()
            );
        }

        return $value;
    }

    /**
     * Check beginning of membership, expressed as a day/month pair
     *
     * @param mixed $value Value to check
     */
    private function validateBegMembership(mixed $value): mixed
    {
        $beg_membership = explode("/", (string)$value);
        if (count($beg_membership) != 2) {
            $this->errors[] = PreferencesSchema::getErrorMessage(
                PreferencesSchema::ERR_BEG_MEMBERSHIP_FORMAT
            );
        } else {
            $now = getdate();
            if (!checkdate((int)$beg_membership[1], (int)$beg_membership[0], $now['year'])) {
                $this->errors[] = PreferencesSchema::getErrorMessage(
                    PreferencesSchema::ERR_BEG_MEMBERSHIP_DATE
                );
            }
        }

        return $value;
    }

    /**
     * Check year for members cards
     *
     * @param mixed $value Value to check
     */
    private function validateCardYear(mixed $value): mixed
    {
        if (
            $value !== 'DEADLINE'
            && !preg_match('/^(?:\d{4}|\d{2})(\D?)(?:\d{4}|\d{2})$/', (string)$value)
        ) {
            $this->errors[] = PreferencesSchema::getErrorMessage(PreferencesSchema::ERR_CARD_YEAR);
        }

        return $value;
    }

    /**
     * Check website URL
     *
     * @param mixed $value Value to check
     */
    private function validateWebUrl(mixed $value): mixed
    {
        if (!isValidWebUrl($value)) {
            $this->errors[] = PreferencesSchema::getErrorMessage(PreferencesSchema::ERR_WEBSITE);
        }

        return $value;
    }
}
