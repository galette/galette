<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Safe\DateTimeImmutable;
use Galette\Core\TwoFactorAuth;
use Galette\Core\TwoFactorSecret;
use Galette\Tests\GaletteTestCase;
use Psr\Clock\ClockInterface;

/**
 * Second factor tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TwoFactorAuthTest extends GaletteTestCase
{
    protected int $seed = 20260821120000;

    /** Base32 of "12345678901234567890", the RFC 6238 SHA-1 seed */
    private const string RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private ?TwoFactorAuth $tfa = null;
    private ?ClockInterface $clock = null;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        //a clock we hold still: the whole point of injecting one is that these
        //tests do not depend on when they run, and need no libfaketime
        $this->clock = $this->frozenClockAt(0);
        $this->tfa = new TwoFactorAuth($this->preferences, $this->clock, $this->zdb);
    }

    /**
     * Codes must match the reference values of RFC 6238, which is what makes
     * any authenticator application interoperable with us
     */
    public function testMatchesRfc6238Vectors(): void
    {
        //RFC 6238 appendix B, SHA-1: the 8 digit values are 94287082 at T=59
        //and 07081804 at T=1111111109; we emit the low 6 digits of those
        $vectors = [
            59 => '287082',
            1111111109 => '081804',
            1111111111 => '050471',
            1234567890 => '005924',
            2000000000 => '279037'
        ];

        foreach ($vectors as $timestamp => $expected) {
            $this->assertSame(
                $expected,
                $this->tfa->getCodeAt(self::RFC_SECRET, $timestamp),
                'unexpected code at ' . $timestamp
            );
        }
    }

    /**
     * A code from the previous or next period is accepted, one further out is
     * not: that tolerance absorbs phone clock drift without opening a window
     */
    public function testToleranceWindowBounds(): void
    {
        $now = 1700000000;
        $tfa = new TwoFactorAuth($this->preferences, $this->frozenClockAt($now), $this->zdb);

        foreach ([-1, 0, 1] as $offset) {
            $code = $tfa->getCodeAt(self::RFC_SECRET, $now + ($offset * 30));
            $this->assertNotNull(
                $tfa->getMatchingTimeslice(self::RFC_SECRET, $code),
                'code at offset ' . $offset . ' should be accepted'
            );
        }

        foreach ([-2, 2] as $offset) {
            $code = $tfa->getCodeAt(self::RFC_SECRET, $now + ($offset * 30));
            $this->assertNull(
                $tfa->getMatchingTimeslice(self::RFC_SECRET, $code),
                'code at offset ' . $offset . ' should be refused'
            );
        }

        //and nothing else gets through
        $this->assertNull($tfa->getMatchingTimeslice(self::RFC_SECRET, '000000'));
        $this->assertNull($tfa->getMatchingTimeslice(self::RFC_SECRET, ''));
    }

    /**
     * The time slice reported is the one the code belongs to, which is what
     * replay protection is keyed on
     */
    public function testReportedTimesliceFollowsTheCode(): void
    {
        $now = 1700000000;
        $tfa = new TwoFactorAuth($this->preferences, $this->frozenClockAt($now), $this->zdb);

        $this->assertSame(
            intdiv($now, 30),
            $tfa->getMatchingTimeslice(self::RFC_SECRET, $tfa->getCodeAt(self::RFC_SECRET, $now))
        );
        $this->assertSame(
            intdiv($now - 30, 30),
            $tfa->getMatchingTimeslice(self::RFC_SECRET, $tfa->getCodeAt(self::RFC_SECRET, $now - 30))
        );
    }

    /**
     * A code is single use: it stays mathematically valid for the whole window,
     * so accepting it twice would let an intercepted code be replayed
     */
    public function testCodeCannotBeReplayed(): void
    {
        $now = 1700000000;
        $tfa = new TwoFactorAuth($this->preferences, $this->frozenClockAt($now), $this->zdb);
        $state = $this->enrolledSecret(self::RFC_SECRET);
        $code = $tfa->getCodeAt(self::RFC_SECRET, $now);

        $this->assertTrue($tfa->verify($state, $code));
        $this->assertSame(intdiv($now, 30), $state->getLastTimeslice());

        $this->assertFalse($tfa->verify($state, $code));
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Second factor code replayed for member');

        //an older code is refused too, its slice being behind the last accepted
        $this->assertFalse($tfa->verify($state, $tfa->getCodeAt(self::RFC_SECRET, $now - 30)));
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Second factor code replayed for member');

        //the next period is accepted, and moves the mark forward
        $this->assertTrue($tfa->verify($state, $tfa->getCodeAt(self::RFC_SECRET, $now + 30)));
        $this->assertSame(intdiv($now + 30, 30), $state->getLastTimeslice());
    }

    /**
     * Secrets must be usable by an authenticator application
     */
    public function testCreateSecret(): void
    {
        $secret = $this->tfa->createSecret();

        //base32, the alphabet every authenticator expects
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+=*$/', $secret);
        //160 bits, the RFC 4226 recommendation: 32 base32 characters, short
        //enough to be typed in by hand and to fit the column
        $this->assertSame(32, strlen($secret));
        $this->assertNotSame($secret, $this->tfa->createSecret());
    }

    /**
     * The provisioning URI carries what an application needs to name the entry
     */
    public function testProvisioningUri(): void
    {
        $uri = $this->tfa->getProvisioningUri(self::RFC_SECRET, 'jean.dupont');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=' . self::RFC_SECRET, $uri);
        $this->assertStringContainsString('jean.dupont', $uri);
        $this->assertStringContainsString(rawurlencode($this->preferences->pref_nom), $uri);

        //and it renders, so enrolment can show a QR code
        $this->assertStringStartsWith('data:image/svg+xml', $this->tfa->getQrCode(self::RFC_SECRET, 'jean.dupont')->getImage());
    }

    /**
     * Who has to hold a second factor depends on the configured policy
     */
    public function testPolicy(): void
    {
        $member = $this->createMember($this->dataAdherentOne());
        $this->login->logIn($member->login, 'J^B-()f');
        $this->assertFalse($this->login->isAdmin());

        $this->preferences->pref_2fa_mode = TwoFactorAuth::MODE_DISABLED;
        $this->assertFalse($this->tfa->isEnabled());
        $this->assertFalse($this->tfa->isRequiredFor($this->login));

        $this->preferences->pref_2fa_mode = TwoFactorAuth::MODE_OPTIONAL;
        $this->assertTrue($this->tfa->isEnabled());
        $this->assertFalse($this->tfa->isRequiredFor($this->login));

        $this->preferences->pref_2fa_mode = TwoFactorAuth::MODE_REQUIRED_STAFF;
        $this->assertFalse($this->tfa->isRequiredFor($this->login));

        $this->preferences->pref_2fa_mode = TwoFactorAuth::MODE_REQUIRED_ALL;
        $this->assertTrue($this->tfa->isRequiredFor($this->login));

        //an administrator is caught by the staff policy as well
        $this->login->logOut();
        $this->logSuperAdmin();
        $this->preferences->pref_2fa_mode = TwoFactorAuth::MODE_REQUIRED_STAFF;
        $this->assertTrue($this->tfa->isRequiredFor($this->login));
    }

    /**
     * A clock held at a given instant
     *
     * @param int $timestamp Instant to freeze at
     */
    private function frozenClockAt(int $timestamp): ClockInterface
    {
        return new readonly class ($timestamp) implements ClockInterface {
            /**
             * Constructor
             *
             * @param int $timestamp Instant to freeze at
             */
            public function __construct(private int $timestamp)
            {
            }

            /**
             * Frozen point in time
             */
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('@' . $this->timestamp);
            }
        };
    }

    /**
     * An enrolled second factor for a fresh member
     *
     * @param string $secret Shared secret
     */
    private function enrolledSecret(string $secret): TwoFactorSecret
    {
        $member = $this->createMember($this->dataAdherentOne());
        $state = new TwoFactorSecret($this->zdb);
        $this->assertTrue($state->create($member->id, $secret));
        $this->assertTrue($state->enable());
        return $state;
    }
    /**
     * The two mandatory policies are held back by a flag: what is stored stays
     * stored, but it applies as "optional" -- a member already enrolled keeps
     * being asked for their code, and nobody is driven to enrolment
     */
    public function testMandatoryPoliciesAreBehindAFlag(): void
    {
        $this->preferences->pref_2fa_mode = TwoFactorAuth::MODE_REQUIRED_ALL;
        $tfa = new TwoFactorAuth($this->preferences, new \Galette\Core\SystemClock(), $this->zdb);
        $this->assertSame(TwoFactorAuth::MODE_REQUIRED_ALL, $tfa->getMode());
        $this->assertTrue($tfa->isRequiredFor($this->login));

        try {
            TwoFactorAuth::forceRequiredAvailable(available: false);

            $this->assertFalse(TwoFactorAuth::isRequiredAvailable());
            $this->assertSame(TwoFactorAuth::MODE_OPTIONAL, $tfa->getMode());
            //still in use: an enrolled member is still asked for their code
            $this->assertTrue($tfa->isEnabled());
            //but nothing is compulsory any more, which is what could lock an
            //association out of its own instance
            $this->assertFalse($tfa->isRequiredFor($this->login));

            //not even for the super administrator, the account the staff
            //policy would hold first
            $this->preferences->pref_2fa_mode = TwoFactorAuth::MODE_REQUIRED_STAFF;
            $this->logSuperAdmin();
            $this->assertFalse($tfa->isRequiredFor($this->login));
            $this->login->logOut();

            //and the entry to manage one stays in the menus: members enrolled
            //under the mandatory policy must be able to reach it
            $member = $this->createMember($this->dataAdherentOne());
            $this->assertTrue($this->login->logIn($member->login, 'J^B-()f'));
            $this->assertContains('two-factor-manage', $this->myAccountRoutes());

            //a policy that is off stays off: the clamp is a ceiling, it never
            //turns anything on
            $this->preferences->pref_2fa_mode = TwoFactorAuth::MODE_DISABLED;
            $this->assertSame(TwoFactorAuth::MODE_DISABLED, $tfa->getMode());
            $this->assertFalse($tfa->isEnabled());
            $this->assertNotContains('two-factor-manage', $this->myAccountRoutes());
        } finally {
            TwoFactorAuth::forceRequiredAvailable(available: true);
        }

        $this->login->logOut();
        $this->preferences->pref_2fa_mode = TwoFactorAuth::MODE_DISABLED;
    }

    /**
     * Clamping a stored policy: a ceiling, never a switch
     */
    public function testClampIsACeiling(): void
    {
        try {
            TwoFactorAuth::forceRequiredAvailable(available: false);

            $this->assertSame(TwoFactorAuth::MODE_DISABLED, TwoFactorAuth::clampMode(TwoFactorAuth::MODE_DISABLED));
            $this->assertSame(TwoFactorAuth::MODE_OPTIONAL, TwoFactorAuth::clampMode(TwoFactorAuth::MODE_OPTIONAL));
            $this->assertSame(
                TwoFactorAuth::MODE_OPTIONAL,
                TwoFactorAuth::clampMode(TwoFactorAuth::MODE_REQUIRED_STAFF)
            );
            $this->assertSame(
                TwoFactorAuth::MODE_OPTIONAL,
                TwoFactorAuth::clampMode(TwoFactorAuth::MODE_REQUIRED_ALL)
            );
            //a stored value that is no policy at all does not read as one
            $this->assertSame(TwoFactorAuth::MODE_OPTIONAL, TwoFactorAuth::clampMode(7));
        } finally {
            TwoFactorAuth::forceRequiredAvailable(available: true);
        }

        //with the flag, what is stored is what applies
        foreach ([0, 1, 2, 3, 7] as $mode) {
            $this->assertSame($mode, TwoFactorAuth::clampMode($mode));
        }
    }

    /**
     * A preference blanked by a form that did not render it comes back as an
     * empty string, which compares unequal to every mode: read through the
     * cast, or the second factor turns itself on
     */
    public function testBlankPolicyReadsAsDisabled(): void
    {
        global $preferences;

        //written the way it happens: a row blanked in database, not a value
        //assigned through the typed property
        $update = $this->zdb->update(\Galette\Core\Preferences::TABLE);
        $update->set(['val_pref' => ''])->where(['nom_pref' => 'pref_2fa_mode']);
        $this->zdb->execute($update);

        $blanked = new \Galette\Core\Preferences($this->zdb);
        $kept = $preferences;
        $preferences = $blanked;

        try {
            $this->assertSame(TwoFactorAuth::MODE_DISABLED, TwoFactorAuth::modeFrom($blanked));

            //and the menu offers nothing: comparing the raw value would find
            //'' unequal to every mode, and show the entry
            $member = $this->createMember($this->dataAdherentOne());
            $this->assertTrue($this->login->logIn($member->login, 'J^B-()f'));
            $this->assertNotContains('two-factor-manage', $this->myAccountRoutes());
        } finally {
            $preferences = $kept;
            $this->login->logOut();
            $update = $this->zdb->update(\Galette\Core\Preferences::TABLE);
            $update->set(['val_pref' => (string)TwoFactorAuth::MODE_DISABLED])
                ->where(['nom_pref' => 'pref_2fa_mode']);
            $this->zdb->execute($update);
        }
    }

    /**
     * Routes the "my account" menu offers
     *
     * @return array<int, string>
     */
    private function myAccountRoutes(): array
    {
        $menus = \Galette\Core\Galette::getMenus();
        $routes = [];
        foreach ($menus['myaccount']['items'] ?? [] as $item) {
            $routes[] = $item['route']['name'];
        }
        return $routes;
    }
}
