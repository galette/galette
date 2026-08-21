<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use ArrayObject;
use Galette\Core\AuthThrottle;
use Galette\Core\Db;
use Galette\Core\Preferences;
use Galette\Tests\GaletteTestCase;
use Safe\DateTime;

/**
 * Authentication throttling tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AuthThrottleTest extends GaletteTestCase
{
    protected int $seed = 20260821104500;

    private ?AuthThrottle $throttle = null;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->throttle = new AuthThrottle($this->zdb, $this->preferences, clean: false);
    }

    /**
     * An IPv6 end host commonly owns a whole /64: counting exact addresses
     * would leave far too much margin.
     */
    public function testNormalizeAddressGroupsIpV6(): void
    {
        //IPv4 is counted as is
        $this->assertSame('192.0.2.10', AuthThrottle::normalizeAddress('192.0.2.10'));

        //every address of a /64 lands on the same counter
        $this->assertSame(
            '2001:db8:1:2::/64',
            AuthThrottle::normalizeAddress('2001:db8:1:2:3:4:5:6')
        );
        $this->assertSame(
            AuthThrottle::normalizeAddress('2001:db8:1:2::1'),
            AuthThrottle::normalizeAddress('2001:db8:1:2:ffff:ffff:ffff:ffff')
        );

        //a neighbouring /64 stays a distinct counter
        $this->assertNotSame(
            AuthThrottle::normalizeAddress('2001:db8:1:2::1'),
            AuthThrottle::normalizeAddress('2001:db8:1:3::1')
        );

        //anything unusable is dropped rather than counted
        $this->assertSame('', AuthThrottle::normalizeAddress(''));
        $this->assertSame('', AuthThrottle::normalizeAddress('not an address'));
    }

    /**
     * The everyday counter: an account and an address together. Tripping it
     * refuses that address, and nothing else.
     */
    public function testAccountAndAddressLocks(): void
    {
        $login = $this->createMember($this->dataAdherentOne())->login;
        $threshold = $this->preferences->pref_throttle_account_ip_attempts;

        for ($i = 1; $i < $threshold; $i++) {
            $this->throttle->recordFailure($login);
            $this->assertSame(0, $this->throttle->getRetryDelay($login));
        }

        $this->throttle->recordFailure($login);
        $this->assertSame(
            $this->preferences->pref_throttle_delay,
            $this->getStoredDelay(AuthThrottle::SCOPE_ACCOUNT_IP, $login . '|127.0.0.1')
        );
        $this->assertGreaterThan(0, $this->throttle->getRetryDelay($login));

        //the account itself is counted, but nowhere near its own threshold
        $this->assertSame($threshold, $this->getFailures(AuthThrottle::SCOPE_ACCOUNT, $login));
        $this->assertSame(0, $this->getStoredDelay(AuthThrottle::SCOPE_ACCOUNT, $login));
    }

    /**
     * What the whole shape is for: a third party guessing from their own place
     * must not keep the owner of the account out of it.
     */
    public function testAnotherAddressIsNotDelayed(): void
    {
        $login = $this->createMember($this->dataAdherentOne())->login;

        $attacker = $this->throttleFrom('192.0.2.66');
        for ($i = 0; $i < $this->preferences->pref_throttle_account_ip_attempts + 5; $i++) {
            $attacker->recordFailure($login);
        }
        $this->assertGreaterThan(0, $attacker->getRetryDelay($login));

        //the owner, from anywhere else, is not made to wait
        $this->assertSame(0, $this->throttleFrom('192.0.2.67')->getRetryDelay($login));
        $this->assertSame(0, $this->throttle->getRetryDelay($login));
    }

    /**
     * And the counter that catches what the two others cannot: the same account
     * tried from a great many addresses, each one staying below its threshold.
     */
    public function testAccountScopeCatchesADistributedAttack(): void
    {
        $login = $this->createMember($this->dataAdherentOne())->login;
        $threshold = $this->preferences->pref_throttle_account_attempts;

        for ($i = 1; $i <= $threshold; $i++) {
            //one failure per address, so that neither the address counter nor
            //the account and address counter ever gets close to its own
            $this->throttleFrom('2001:db8:' . dechex($i) . '::1')->recordFailure($login);
        }

        $this->assertSame($threshold, $this->getFailures(AuthThrottle::SCOPE_ACCOUNT, $login));
        $this->assertSame(
            $this->preferences->pref_throttle_delay,
            $this->getStoredDelay(AuthThrottle::SCOPE_ACCOUNT, $login)
        );
        //from a brand new address as well: this one is not about where from
        $this->assertGreaterThan(0, $this->throttleFrom('198.51.100.4')->getRetryDelay($login));
    }

    /**
     * A successful authentication clears the account counter
     */
    public function testSuccessClearsAccountCounter(): void
    {
        $login = $this->createMember($this->dataAdherentOne())->login;

        for ($i = 0; $i < 6; $i++) {
            $this->throttle->recordFailure($login);
        }
        $this->assertGreaterThan(0, $this->throttle->getRetryDelay($login));

        $this->throttle->recordSuccess($login);

        $this->assertNull($this->getRow(AuthThrottle::SCOPE_ACCOUNT, $login));
        $this->assertNull($this->getRow(AuthThrottle::SCOPE_ACCOUNT_IP, $login . '|127.0.0.1'));
        //the address counter is deliberately kept: a success must not let an
        //attacker holding one valid account wipe its own trail
        $this->assertNotNull($this->getRow(AuthThrottle::SCOPE_IP, '127.0.0.1'));
    }

    /**
     * An unknown login is counted with its address exactly like a known one:
     * being refused must not say whether an account exists.
     *
     * The backstop is the exception -- it is meant for an attack on a real
     * account, and an invented login would only fill it with counters nobody
     * will ever read. It never trips at this scale anyway.
     */
    public function testUnknownLoginIsCountedLikeAnyOther(): void
    {
        $known = $this->createMember($this->dataAdherentOne())->login;
        $unknown = 'no.such.member' . $this->seed;
        $threshold = $this->preferences->pref_throttle_account_ip_attempts;

        for ($i = 0; $i < $threshold; $i++) {
            $this->throttleFrom('192.0.2.10')->recordFailure($known);
            $this->throttleFrom('192.0.2.11')->recordFailure($unknown);
        }

        //same threshold, same refusal, same delay
        $this->assertSame(
            $this->throttleFrom('192.0.2.10')->getRetryDelay($known),
            $this->throttleFrom('192.0.2.11')->getRetryDelay($unknown)
        );
        $this->assertGreaterThan(0, $this->throttleFrom('192.0.2.11')->getRetryDelay($unknown));
        $this->assertSame($threshold, $this->getFailures(AuthThrottle::SCOPE_ACCOUNT_IP, $unknown . '|192.0.2.11'));

        //only the backstop tells them apart, and only in the table
        $this->assertSame($threshold, $this->getFailures(AuthThrottle::SCOPE_ACCOUNT, $known));
        $this->assertNull($this->getRow(AuthThrottle::SCOPE_ACCOUNT, $unknown));
    }

    /**
     * The super administrator has no row in the members table, and still must
     * be counted
     */
    public function testSuperAdminIsCounted(): void
    {
        $this->throttle->recordFailure($this->preferences->pref_admin_login);

        $this->assertSame(
            1,
            $this->getFailures(AuthThrottle::SCOPE_ACCOUNT, $this->preferences->pref_admin_login)
        );
    }

    /**
     * Failures spread over many accounts from one place are caught by the
     * address counter, which locks once over its threshold
     */
    public function testAddressLocksOverThreshold(): void
    {
        $threshold = $this->preferences->pref_throttle_ip_attempts;

        for ($i = 1; $i < $threshold; $i++) {
            $this->throttle->recordFailure('member-' . $i . '@example.com');
        }
        $this->assertSame(0, $this->getStoredDelay(AuthThrottle::SCOPE_IP, '127.0.0.1'));

        $this->throttle->recordFailure('member-last@example.com');
        $this->assertSame(
            $this->preferences->pref_throttle_delay,
            $this->getStoredDelay(AuthThrottle::SCOPE_IP, '127.0.0.1')
        );
    }

    /**
     * The address counter forgets: past the window, the series starts over
     */
    public function testAddressWindowSlides(): void
    {
        $this->throttle->recordFailure('someone@example.com');
        $this->assertSame(1, $this->getFailures(AuthThrottle::SCOPE_IP, '127.0.0.1'));

        $this->throttle->recordFailure('someone@example.com');
        $this->assertSame(2, $this->getFailures(AuthThrottle::SCOPE_IP, '127.0.0.1'));

        //push the last failure out of the window
        $this->ageRow(AuthThrottle::SCOPE_IP, '127.0.0.1', $this->preferences->pref_throttle_ip_window + 60);

        $this->throttle->recordFailure('someone@example.com');
        $this->assertSame(1, $this->getFailures(AuthThrottle::SCOPE_IP, '127.0.0.1'));
    }

    /**
     * Asking for a new password is counted apart: what it floods is a mailbox,
     * and it must not be a way to keep a member from logging in
     */
    public function testRecoveryIsCountedApart(): void
    {
        $login = $this->createMember($this->dataAdherentOne())->login;
        $threshold = $this->preferences->pref_throttle_recovery_attempts;

        for ($i = 1; $i < $threshold; $i++) {
            $this->throttle->recordRecovery($login);
            $this->assertSame(0, $this->throttle->getRecoveryDelay($login));
        }

        $this->throttle->recordRecovery($login);
        $this->assertSame(
            $this->preferences->pref_throttle_delay,
            $this->getStoredDelay(AuthThrottle::SCOPE_RECOVERY_ACCOUNT, $login)
        );
        $this->assertGreaterThan(0, $this->throttle->getRecoveryDelay($login));

        //and the account can still be logged into: somebody asking for reset
        //mails over and over must not lock its owner out of the login form
        $this->assertSame(0, $this->throttle->getRetryDelay($login));
        $this->assertNull($this->getRow(AuthThrottle::SCOPE_ACCOUNT, $login));
        $this->assertNull($this->getRow(AuthThrottle::SCOPE_ACCOUNT_IP, $login . '|127.0.0.1'));

        //an unknown login is only counted on the address, as everywhere else
        $this->assertSame(
            $threshold,
            $this->getFailures(AuthThrottle::SCOPE_RECOVERY_IP, '127.0.0.1')
        );
        $this->throttle->recordRecovery('no.such.member' . $this->seed);
        $this->assertNull($this->getRow(AuthThrottle::SCOPE_RECOVERY_ACCOUNT, 'no.such.member' . $this->seed));
    }

    /**
     * Subscribing is counted on the address alone -- there is no account yet --
     * and must not stand in the way of anything else
     */
    public function testSubscriptionsAreCountedOnTheAddress(): void
    {
        $login = $this->createMember($this->dataAdherentOne())->login;
        $threshold = $this->preferences->pref_throttle_subscribe_attempts;

        for ($i = 1; $i < $threshold; $i++) {
            $this->throttle->recordSubscribe();
            $this->assertSame(0, $this->throttle->getSubscribeDelay());
        }

        $this->throttle->recordSubscribe();
        $this->assertSame(
            $this->preferences->pref_throttle_delay,
            $this->getStoredDelay(AuthThrottle::SCOPE_SUBSCRIBE_IP, '127.0.0.1')
        );
        $this->assertGreaterThan(0, $this->throttle->getSubscribeDelay());

        //neither logging in nor asking for a password is touched by it
        $this->assertSame(0, $this->throttle->getRetryDelay($login));
        $this->assertSame(0, $this->throttle->getRecoveryDelay($login));

        //and another address is free to subscribe
        $this->assertSame(0, $this->throttleFrom('192.0.2.80')->getSubscribeDelay());
    }

    /**
     * A scope being a name and not a number, anything else -- a plugin, on a
     * form of its own -- counts under its own, with its own limits
     */
    public function testAnythingElseCanCountItsOwn(): void
    {
        $login = $this->createMember($this->dataAdherentOne())->login;
        $scope = 'plugin-payment';

        //the table is shared with whatever else ran against this database
        $this->throttle->clearEvent($scope, '192.0.2.90');
        $this->throttle->clearEvent($scope, '192.0.2.91');

        for ($i = 0; $i < 2; $i++) {
            $this->throttle->recordEvent($scope, '192.0.2.90', 3, 600);
            $this->assertSame(0, $this->throttle->getDelayForEvent($scope, '192.0.2.90'));
        }

        $this->throttle->recordEvent($scope, '192.0.2.90', 3, 600);
        $this->assertSame(
            $this->preferences->pref_throttle_delay,
            $this->getStoredDelay($scope, '192.0.2.90')
        );
        $this->assertGreaterThan(0, $this->throttle->getDelayForEvent($scope, '192.0.2.90'));

        //what it counts is its own business: another identifier, and nothing
        //the core counts, are left alone
        $this->assertSame(0, $this->throttle->getDelayForEvent($scope, '192.0.2.91'));
        $this->assertSame(0, $this->throttle->getRetryDelay($login));

        //and it cannot turn its own limit off any more than the core can: a
        //threshold of zero is read as the floor, so it still refuses -- and
        //not before the floor either
        $this->throttle->clearEvent($scope, '192.0.2.90');
        $this->assertSame(0, $this->throttle->getDelayForEvent($scope, '192.0.2.90'));

        for ($i = 1; $i < AuthThrottle::MIN_ATTEMPTS; $i++) {
            $this->throttle->recordEvent($scope, '192.0.2.90', 0, 0);
            $this->assertSame(0, $this->throttle->getDelayForEvent($scope, '192.0.2.90'));
        }

        $this->throttle->recordEvent($scope, '192.0.2.90', 0, 0);
        $this->assertGreaterThan(0, $this->throttle->getDelayForEvent($scope, '192.0.2.90'));
    }

    /**
     * The mechanism cannot be turned off: a value written straight in the
     * database, or a form sending zeroes, is held to its floor
     */
    public function testCountersCannotBeDisabled(): void
    {
        $login = $this->createMember($this->dataAdherentOne())->login;

        $this->preferences->pref_throttle_account_ip_attempts = 0;
        $this->preferences->pref_throttle_ip_attempts = 0;
        $this->preferences->pref_throttle_account_attempts = 0;
        $this->preferences->pref_throttle_account_ip_window = 0;
        $this->preferences->pref_throttle_ip_window = 0;
        $this->preferences->pref_throttle_account_window = 0;
        $this->preferences->pref_throttle_recovery_attempts = 0;
        $this->preferences->pref_throttle_recovery_window = 0;
        $this->preferences->pref_throttle_subscribe_attempts = 0;
        $this->preferences->pref_throttle_subscribe_window = 0;
        $this->preferences->pref_throttle_delay = 0;

        for ($i = 0; $i < AuthThrottle::MIN_ATTEMPTS; $i++) {
            $this->throttle->recordFailure($login);
        }

        $this->assertSame(
            AuthThrottle::MIN_SECONDS,
            $this->getStoredDelay(AuthThrottle::SCOPE_ACCOUNT_IP, $login . '|127.0.0.1')
        );
        $this->assertSame(AuthThrottle::MIN_SECONDS, $this->throttle->getRetryDelay($login));

        //the public forms are held to the floor as well
        for ($i = 0; $i < AuthThrottle::MIN_ATTEMPTS; $i++) {
            $this->throttle->recordRecovery($login);
            $this->throttle->recordSubscribe();
        }
        $this->assertSame(AuthThrottle::MIN_SECONDS, $this->throttle->getRecoveryDelay($login));
        $this->assertSame(AuthThrottle::MIN_SECONDS, $this->throttle->getSubscribeDelay());
    }

    /**
     * And the form says so rather than storing it
     */
    public function testThresholdsBelowTheFloorAreRefused(): void
    {
        $post = $this->preferences->getDefaults();

        $this->assertFalse(
            $this->preferences->check(
                array_merge(
                    $post,
                    [
                        'pref_throttle_account_ip_attempts' => 0,
                        'pref_throttle_delay' => 5
                    ]
                ),
                $this->login
            )
        );
        $errors = $this->preferences->getErrors();
        $this->assertCount(2, $errors);
        //each one names the floor it is held to
        $this->assertStringContainsString((string)AuthThrottle::MIN_ATTEMPTS, $errors[0]);
        $this->assertStringContainsString('attempts or more', $errors[0]);
        $this->assertStringContainsString((string)AuthThrottle::MIN_SECONDS, $errors[1]);
        $this->assertStringContainsString('seconds or more', $errors[1]);

        //and the floors themselves go through
        $this->assertTrue(
            $this->preferences->check(
                array_merge(
                    $post,
                    [
                        'pref_throttle_account_ip_attempts' => AuthThrottle::MIN_ATTEMPTS,
                        'pref_throttle_delay' => AuthThrottle::MIN_SECONDS
                    ]
                ),
                $this->login
            )
        );
    }

    /**
     * Counters that can no longer delay anyone are dropped
     */
    public function testCleanExpired(): void
    {
        $this->throttle->recordFailure('someone@example.com');
        $this->assertNotNull($this->getRow(AuthThrottle::SCOPE_IP, '127.0.0.1'));

        $this->assertTrue($this->throttle->cleanExpired());
        $this->assertNotNull($this->getRow(AuthThrottle::SCOPE_IP, '127.0.0.1'));

        //rows are kept as long as the longest of the windows, the account one
        $this->ageRow(
            AuthThrottle::SCOPE_IP,
            '127.0.0.1',
            $this->preferences->pref_throttle_account_window + 60
        );
        $this->assertTrue($this->throttle->cleanExpired());
        $this->assertNull($this->getRow(AuthThrottle::SCOPE_IP, '127.0.0.1'));
    }

    /**
     * What refuses somebody can be listed and lifted, which is what an
     * administrator with a member on the telephone needs
     */
    public function testLocksAreListedAndLifted(): void
    {
        $login = $this->createMember($this->dataAdherentOne())->login;

        //the table is shared with whatever else has run against this database,
        //and it is transient by nature: start from nothing
        $this->assertTrue($this->throttle->releaseAll());
        $this->assertSame([], $this->throttle->getLocks());

        //enough to lock the pair, and the address as well
        for ($i = 0; $i < $this->preferences->pref_throttle_ip_attempts; $i++) {
            $this->throttle->recordFailure($login);
        }

        $locks = $this->throttle->getLocks();
        $this->assertCount(2, $locks);

        $scopes = array_column($locks, 'scope');
        $this->assertContains(AuthThrottle::SCOPE_ACCOUNT_IP, $scopes);
        $this->assertContains(AuthThrottle::SCOPE_IP, $scopes);

        foreach ($locks as $lock) {
            $this->assertGreaterThan(0, $lock['id']);
            $this->assertSame('127.0.0.1', $lock['address']);
            if ($lock['scope'] === AuthThrottle::SCOPE_ACCOUNT_IP) {
                //the pair reads back as two things, not as one identifier
                $this->assertSame($login, $lock['login']);
            } else {
                $this->assertNull($lock['login']);
            }
        }

        //lifting one leaves the other, and lets that one through at once
        $this->assertTrue($this->throttle->release($locks[0]['id']));
        $this->assertCount(1, $this->throttle->getLocks());
        $this->assertFalse($this->throttle->release($locks[0]['id']));

        $this->assertTrue($this->throttle->releaseAll());
        $this->assertSame([], $this->throttle->getLocks());
        $this->assertSame(0, $this->throttle->getRetryDelay($login));
    }

    /**
     * A throttle that counts as if the attempt came from a given address
     *
     * @param string $address Client address to pretend to come from
     */
    private function throttleFrom(string $address): AuthThrottle
    {
        return new class (
            zdb: $this->zdb,
            preferences: $this->preferences,
            clean: false,
            address: $address
        ) extends AuthThrottle {
            /**
             * Default constructor
             *
             * @param Db          $zdb         Database instance
             * @param Preferences $preferences Preferences instance
             * @param bool        $clean       Whether to drop stale counters
             * @param string      $address     Client address to answer
             */
            public function __construct(
                Db $zdb,
                Preferences $preferences,
                bool $clean,
                private readonly string $address
            ) {
                parent::__construct($zdb, $preferences, $clean);
            }

            /**
             * Address the attempt comes from
             */
            protected function clientAddress(): string
            {
                return $this->address;
            }
        };
    }

    /**
     * Get stored counter for a scope
     *
     * @param string $scope      Scope name
     * @param string $identifier Identifier
     *
     * @return ?ArrayObject<string, int|string>
     */
    private function getRow(string $scope, string $identifier): ?ArrayObject
    {
        $select = $this->zdb->select(AuthThrottle::TABLE);
        $select->where(['scope' => $scope, 'identifier' => $identifier]);
        $results = $this->zdb->execute($select);
        return $results->count() > 0 ? $results->current() : null;
    }

    /**
     * Failures counted so far on a scope
     *
     * @param string $scope      Scope name
     * @param string $identifier Identifier
     */
    private function getFailures(string $scope, string $identifier): int
    {
        $row = $this->getRow($scope, $identifier);
        return $row === null ? 0 : (int)$row->failures;
    }

    /**
     * Lock duration stored on a scope, computed from the stored timestamps so
     * that the assertion does not depend on when the test runs
     *
     * @param string $scope      Scope name
     * @param string $identifier Identifier
     */
    private function getStoredDelay(string $scope, string $identifier): int
    {
        $row = $this->getRow($scope, $identifier);
        if ($row === null || $row->locked_until === null) {
            return 0;
        }
        return (new DateTime((string)$row->locked_until))->getTimestamp()
            - (new DateTime((string)$row->last_failure))->getTimestamp();
    }

    /**
     * Move a stored counter back in time
     *
     * @param string $scope      Scope name
     * @param string $identifier Identifier
     * @param int    $seconds    How far back
     */
    private function ageRow(string $scope, string $identifier, int $seconds): void
    {
        $row = $this->getRow($scope, $identifier);
        $this->assertNotNull($row);

        $shift = function (?string $date) use ($seconds): ?string {
            if ($date === null) {
                return null;
            }
            return (new DateTime($date))->modify('-' . $seconds . ' seconds')->format('Y-m-d H:i:s');
        };

        $update = $this->zdb->update(AuthThrottle::TABLE);
        $update->set(
            [
                'first_failure' => $shift($row->first_failure),
                'last_failure'  => $shift($row->last_failure),
                'locked_until'  => $shift($row->locked_until)
            ]
        )->where([AuthThrottle::PK => $row->{AuthThrottle::PK}]);
        $this->zdb->execute($update);
    }
}
