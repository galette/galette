<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Analog\Analog;
use Galette\Util\QrCode;
use OTPHP\TOTP;
use Psr\Clock\ClockInterface;

/**
 * Second authentication factor, time based (RFC 6238).
 *
 * Wraps spomky-labs/otphp. Note that TOTP::verify() is *not* used: it refuses
 * a leeway greater than or equal to the period, so the usual "one period
 * either side" tolerance -- the one that absorbs phone clock drift -- cannot be
 * expressed through it. The window is walked here instead, which is also where
 * the accepted time slice is picked up for replay protection.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TwoFactorAuth
{
    /**
     * Feature flag the two mandatory policies live behind.
     *
     * The second factor itself is always available, optional by default.
     * Making it compulsory is a feature flag the super administrator turns
     * on from the advanced configuration page: a clock
     * that drifts or a botched enrolment can put members outside their own
     * instance, and galette:twofactor:reset is the way back. Until the flag is
     * on, those two policies read as "optional" -- see clampMode().
     */
    public const string FEATURE_REQUIRED = 'two-factor-required';

    /** Forced answer of isRequiredAvailable(), for the test suite */
    private static ?bool $required_available = null;

    /** Members may enrol if they want to; the lowest policy there is */
    public const int MODE_OPTIONAL = 1;
    /** Administrators and staff must enrol */
    public const int MODE_REQUIRED_STAFF = 2;
    /** Everyone must enrol */
    public const int MODE_REQUIRED_ALL = 3;

    /** Longest suspension galette:twofactor:suspend grants, in hours */
    public const int SUSPEND_MAX_HOURS = 72;

    /** How many periods either side of the current one are accepted */
    public const int TOLERANCE = 1;

    /**
     * Shared secret length, in bytes.
     *
     * 20 bytes is the 160 bits RFC 4226 recommends, and what authenticator
     * applications are built around. The library defaults to 64 bytes, which
     * renders as 104 base32 characters: too long to type in by hand, and
     * needlessly so.
     */
    public const int SECRET_BYTES = 20;

    /**
     * Default constructor
     *
     * @param Preferences    $preferences Preferences instance
     * @param ClockInterface $clock       Clock to read "now" from
     * @param Db             $zdb         Database instance
     */
    public function __construct(
        private readonly Preferences $preferences,
        private readonly ClockInterface $clock,
        private readonly Db $zdb
    ) {
    }

    /**
     * Where the second factor of a session is kept.
     *
     * The super administrator is not a member and keeps its own in the
     * preferences; asking the member store for it would find nothing. Read
     * from the account flag and not from isSuperAdmin(), which answers false
     * while a second factor is owed.
     *
     * @param Authentication $login Session to read the store of
     */
    public function storeFor(Authentication $login): TwoFactorSecret|TwoFactorSuperAdmin
    {
        if ($login->isSuperAdminAccount()) {
            return new TwoFactorSuperAdmin($this->preferences);
        }

        $secret = new TwoFactorSecret($this->zdb);
        $secret->load((int)$login->id);
        return $secret;
    }

    /**
     * Policy in force on this instance
     */
    public function getMode(): int
    {
        return self::modeFrom($this->preferences);
    }

    /**
     * Policy in force, read from a given set of preferences.
     *
     * Static, and taking the preferences rather than reading its own, because
     * the callers that cannot go through this object -- the session's Login,
     * the menus -- must not read the stored value raw. It is the one place the
     * cast happens: a preference blanked by a form that did not render it comes
     * back as an empty string, which compares unequal to every mode.
     *
     * @param Preferences $preferences Preferences to read
     */
    public static function modeFrom(Preferences $preferences): int
    {
        return self::clampMode((int)$preferences->pref_2fa_mode);
    }

    /**
     * Policy actually applied for a stored value.
     *
     * Without the flag, anything above "optional" reads as "optional": the
     * mandatory policies are held back, but a member who enrolled while one was
     * in force keeps being asked for their code. Nothing reads lower than
     * "optional" either: the "disabled" policy is gone, and a 0 left by an
     * instance that skipped its upgrade, or a preference blanked by a form,
     * must not switch off the second factor of the members who hold one. Any
     * value that is no policy at all reads as "optional" for the same reason.
     *
     * @param int $mode Stored policy
     */
    public static function clampMode(int $mode): int
    {
        $ceiling = self::isRequiredAvailable() ? self::MODE_REQUIRED_ALL : self::MODE_OPTIONAL;
        if ($mode < self::MODE_OPTIONAL || $mode > $ceiling) {
            return self::MODE_OPTIONAL;
        }

        return $mode;
    }

    /**
     * End of the running suspension of the second factor, if any.
     *
     * galette:twofactor:suspend stops asking for codes until a given instant,
     * for when the whole instance cannot check them: a server clock that
     * drifted, an authenticator library broken by an update. Unlike a reset,
     * it leaves every enrolment in place; unlike a policy, it ends on its own
     * and cannot be forgotten switched on. Static for the same reasons as
     * modeFrom().
     *
     * @param Preferences $preferences Preferences to read
     * @param ?int        $now         Current timestamp, time() by default
     *
     * @return ?int timestamp the suspension ends at, null when none is running
     */
    public static function suspendedUntil(Preferences $preferences, ?int $now = null): ?int
    {
        $until = (int)$preferences->pref_2fa_suspended_until;
        return $until > ($now ?? time()) ? $until : null;
    }

    /**
     * Is the second factor suspended right now?
     */
    public function isSuspended(): bool
    {
        return self::suspendedUntil($this->preferences, $this->clock->now()->getTimestamp()) !== null;
    }

    /**
     * Are the mandatory policies available on this instance?
     *
     * Static because the places that have to ask are not all built by the
     * container: the session holds a Login, and the menus are assembled from a
     * static method. The manager is cheap to build -- it reads the registry, a
     * constant and one preference.
     */
    public static function isRequiredAvailable(): bool
    {
        return self::$required_available
            ?? (new FeatureFlagManager())->isEnabled(self::FEATURE_REQUIRED);
    }

    /**
     * Answer the question above without asking the flag, or stop doing so.
     *
     * For the test suite only: tests checking the mandatory policies should
     * not depend on what the test database stores, nor on the constant the
     * end to end server declares, which exercises the flag itself.
     *
     * @param ?bool $available true or false to force, null to ask the flag again
     */
    public static function forceRequiredAvailable(?bool $available): void
    {
        if (!defined('GALETTE_TESTS')) {
            //without this, any included file could lift the policy the
            //instance chose
            return;
        }

        self::$required_available = $available;
    }

    /**
     * Must this account hold a second factor?
     *
     * @param Authentication $login Authenticated user
     */
    public function isRequiredFor(Authentication $login): bool
    {
        //enrolling means typing a code, which is what cannot be done while
        //the second factor is suspended
        if ($this->isSuspended()) {
            return false;
        }

        return match ($this->getMode()) {
            self::MODE_REQUIRED_ALL => true,
            self::MODE_REQUIRED_STAFF => $login->isSuperAdmin() || $login->isAdmin() || $login->isStaff(),
            default => false,
        };
    }

    /**
     * Draw a new shared secret
     */
    public function createSecret(): string
    {
        return TOTP::generate($this->clock, self::SECRET_BYTES)->getSecret();
    }

    /**
     * URI an authenticator application reads to register the secret
     *
     * @param string $secret Shared secret
     * @param string $label  Account the secret belongs to
     */
    public function getProvisioningUri(string $secret, string $label): string
    {
        $totp = $this->getTotp($secret);
        $totp->setLabel($label);
        $totp->setIssuer($this->preferences->pref_nom);
        return $totp->getProvisioningUri();
    }

    /**
     * Enrolment QR code, ready to be dropped in an img tag
     *
     * @param string $secret Shared secret
     * @param string $label  Account the secret belongs to
     */
    public function getQrCode(string $secret, string $label): QrCode
    {
        return new QrCode(
            data: $this->getProvisioningUri($secret, $label),
            label: $label
        );
    }

    /**
     * Check a code against a secret, and report which time slice it belongs to.
     *
     * The whole tolerance window is walked without short-circuiting, so the
     * time spent does not depend on which slice matched.
     *
     * @param string $secret Shared secret
     * @param string $code   Code as typed by the member
     *
     * @return ?int matched time slice, null when the code is not valid
     */
    public function getMatchingTimeslice(string $secret, string $code): ?int
    {
        if (trim($code) === '') {
            return null;
        }

        $totp = $this->getTotp($secret);
        $period = $totp->getPeriod();
        $now = $this->clock->now()->getTimestamp();
        $matched = null;

        for ($offset = -self::TOLERANCE; $offset <= self::TOLERANCE; $offset++) {
            $timestamp = $now + ($offset * $period);
            if ($timestamp < 0) {
                continue;
            }
            if (hash_equals($totp->at($timestamp), $code)) {
                $matched = (int)floor(($timestamp - $totp->getEpoch()) / $period);
            }
        }

        return $matched;
    }

    /**
     * Check a code and consume it, so it cannot be replayed.
     *
     * A TOTP code stays valid for the whole tolerance window; accepting the
     * same slice twice would let an intercepted code be reused.
     *
     * @param TwoFactorStore $state Loaded second factor
     * @param string         $code  Code as typed by the owner
     */
    public function verify(TwoFactorStore $state, string $code): bool
    {
        if (!$state->isLoaded()) {
            throw new \RuntimeException('No second factor loaded!');
        }

        $timeslice = $this->getMatchingTimeslice($state->getSecret(), $code);
        if ($timeslice === null) {
            return false;
        }

        $last = $state->getLastTimeslice();
        if ($last !== null && $timeslice <= $last) {
            Analog::log(
                'Second factor code replayed for ' . $state->getOwner(),
                Analog::WARNING
            );
            return false;
        }

        if (!$state->setLastTimeslice($timeslice)) {
            //the member store throws on a failed write, but the preferences
            //one merely returns false: accepting the code here would leave the
            //replay protection silently gone
            Analog::log(
                'Could not record the accepted time slice of ' . $state->getOwner(),
                Analog::ERROR
            );
            return false;
        }

        return true;
    }

    /**
     * Code valid at a given instant, to drive tests and the console
     *
     * @param string $secret    Shared secret
     * @param ?int   $timestamp Instant, now by default
     */
    public function getCodeAt(string $secret, ?int $timestamp = null): string
    {
        return $this->getTotp($secret)->at($timestamp ?? $this->clock->now()->getTimestamp());
    }

    /**
     * Build a TOTP handler over a secret
     *
     * @param string $secret Shared secret
     */
    private function getTotp(string $secret): TOTP
    {
        //the clock is passed explicitly: leaving it out is deprecated since
        //otphp 11.3 and becomes an error in 12.0
        return TOTP::createFromSecret($secret, $this->clock);
    }
}
