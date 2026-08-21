<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Analog\Analog;
use ArrayObject;
use Galette\Entity\Adherent;
use Laminas\Db\Sql\Predicate\PredicateSet;
use Safe\DateTime;
use Throwable;

use function Safe\inet_ntop;
use function Safe\inet_pton;

/**
 * Throttling of failed authentication attempts.
 *
 * Failures are counted along three axes, each answering an attack the others
 * do not see, and all three answered the same way: attempts are refused for a
 * while.
 *
 * - **one account from one address** does the everyday work. Whoever trips it
 *   only ever refuses their own address, so the threshold can be low.
 * - **one address, whatever the accounts** catches somebody walking through a
 *   list of logins from a single place.
 * - **one account, whatever the address** is a backstop, and nothing else: it
 *   is the only counter that sees an attack spread over many addresses, where
 *   each one stays below its own threshold. But anyone can make an attempt on
 *   an account without knowing anything about it, so a low threshold here is a
 *   way to keep a member out of their own account: that is what the counter
 *   above is for. Hence a threshold in the hundreds -- the order NIST 800-63B
 *   §5.2.2 gives -- where reaching it means an attack, not a nuisance.
 *
 * The public forms are counted apart, each on its own axes: asking for a reset
 * mail, or subscribing, is not a failed attempt. What is at stake there is a
 * mailbox being filled and rows being created, not an account being guessed --
 * and there is no account yet to count a subscription against, so the address
 * is all there is.
 *
 * A scope is a name, not a number, and the ones above are simply the names the
 * core uses. Anything else -- a plugin limiting its own form, say -- counts
 * under a name of its own through recordEvent() and getDelayForEvent(),
 * carrying its own threshold and window. Names rather than numbers so that two
 * counters cannot end up sharing a row by accident, which is what would happen
 * the day two of them picked the same number.
 *
 * Counters are aggregated, one row per scope and identifier: a row per attempt
 * would make the table itself worth attacking, and the individual failures are
 * already journalised, with their address, in the history.
 *
 * Counters are stored server side: a client that sends no cookie would
 * trivially bypass a session counter.
 *
 * None of it can be turned off. The thresholds and the durations are settings,
 * and generous ones can be asked for, but every value read here is held to a
 * floor -- in the form, and again here, so that a row written straight in the
 * database cannot disable the protection either.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AuthThrottle
{
    public const string TABLE = 'auth_attempts';
    public const string PK = 'id_attempt';

    /** Failures counted for one account, from anywhere */
    public const string SCOPE_ACCOUNT = 'account';
    /** Failures counted for one client address, on any account */
    public const string SCOPE_IP = 'ip';
    /** Failures counted for one account from one client address */
    public const string SCOPE_ACCOUNT_IP = 'account-ip';
    /** Password recovery requests for one account */
    public const string SCOPE_RECOVERY_ACCOUNT = 'recovery-account';
    /** Password recovery requests from one client address */
    public const string SCOPE_RECOVERY_IP = 'recovery-ip';
    /** Self subscriptions from one client address */
    public const string SCOPE_SUBSCRIBE_IP = 'subscribe-ip';

    /**
     * Fewest attempts a counter can be set to trip on.
     *
     * Below three, an honest mistake would be enough to refuse somebody.
     */
    public const int MIN_ATTEMPTS = 3;

    /**
     * Shortest duration a counter can be set to, in seconds.
     *
     * Below a minute, neither a window nor a lock slows an automated guesser
     * down enough to be worth the name.
     */
    public const int MIN_SECONDS = 60;

    /** Separates the two parts of an account and address identifier */
    private const string IDENTIFIER_SEPARATOR = '|';

    /**
     * Default constructor
     *
     * @param Db          $zdb         Database instance
     * @param Preferences $preferences Preferences instance
     * @param bool        $clean       Whether to drop stale counters
     */
    public function __construct(
        private readonly Db $zdb,
        private readonly Preferences $preferences,
        bool $clean = true
    ) {
        if ($clean === true) {
            $this->cleanExpired();
        }
    }

    /**
     * How long the caller must wait before another attempt is accepted.
     *
     * @param string $login Submitted login
     *
     * @return int seconds to wait, 0 when nothing stands in the way
     */
    public function getRetryDelay(string $login): int
    {
        return $this->delayFor($this->getScopes($login));
    }

    /**
     * How long the caller must wait before another password recovery request
     * is accepted.
     *
     * @param string $login Submitted login or email address
     *
     * @return int seconds to wait, 0 when nothing stands in the way
     */
    public function getRecoveryDelay(string $login): int
    {
        return $this->delayFor($this->getRecoveryScopes($login));
    }

    /**
     * How long the caller must wait before another self subscription is
     * accepted.
     *
     * No account to count against -- it does not exist yet -- so the address is
     * all there is to go on.
     *
     * @return int seconds to wait, 0 when nothing stands in the way
     */
    public function getSubscribeDelay(): int
    {
        return $this->delayFor($this->getSubscribeScopes());
    }

    /**
     * Record a self subscription
     */
    public function recordSubscribe(): void
    {
        foreach ($this->getSubscribeScopes() as $scope => $identifier) {
            $this->bump($scope, $identifier);
        }
    }

    /**
     * How long the caller must wait before another attempt at something the
     * core knows nothing about is accepted.
     *
     * For a plugin limiting a form of its own: a payment page, say. The scope is
     * a name it picks, and the identifier whatever it counts by -- an address
     * grouped through normalizeAddress(), an account, or the two together.
     *
     * @param string $scope      Scope name
     * @param string $identifier What is counted
     *
     * @return int seconds to wait, 0 when nothing stands in the way
     */
    public function getDelayForEvent(string $scope, string $identifier): int
    {
        return $this->delayFor([$scope => $identifier]);
    }

    /**
     * Count one attempt at something the core knows nothing about.
     *
     * The threshold and the window belong to the caller, since nothing here
     * knows what its form is worth; both are held to the same floors as the
     * rest, so a plugin cannot turn its own limit off either. How long a lock
     * lasts is the site-wide setting: an administrator sets that once.
     *
     * @param string $scope      Scope name
     * @param string $identifier What is counted
     * @param int    $attempts   Failures before refusing
     * @param int    $window     Seconds over which they are counted
     */
    public function recordEvent(string $scope, string $identifier, int $attempts, int $window): void
    {
        $this->bump(scope: $scope, identifier: $identifier, attempts: $attempts, window: $window);
    }

    /**
     * Forget what was counted under a scope, the caller having decided it no
     * longer stands -- a success, typically
     *
     * @param string $scope      Scope name
     * @param string $identifier What was counted
     */
    public function clearEvent(string $scope, string $identifier): void
    {
        $this->remove($scope, $identifier);
    }

    /**
     * Longest wait the given scopes impose
     *
     * @param array<string, string> $scopes Scopes as scope => identifier
     *
     * @return int seconds to wait, 0 when nothing stands in the way
     */
    private function delayFor(array $scopes): int
    {
        $delay = 0;
        foreach ($scopes as $scope => $identifier) {
            $row = $this->getRow($scope, $identifier);
            if ($row === null || $row->locked_until === null) {
                continue;
            }
            $remaining = (new DateTime($row->locked_until))->getTimestamp() - (new DateTime())->getTimestamp();
            if ($remaining > $delay) {
                $delay = $remaining;
            }
        }
        return $delay;
    }

    /**
     * Record a failed attempt on every relevant scope
     *
     * @param string $login Submitted login
     */
    public function recordFailure(string $login): void
    {
        foreach ($this->getScopes($login) as $scope => $identifier) {
            $this->bump($scope, $identifier);
        }
    }

    /**
     * Record a password recovery request.
     *
     * The request itself is counted, not its outcome: what is being limited is
     * how many reset mails one can have sent, and how many logins one can walk
     * through to find out which ones exist.
     *
     * @param string $login Submitted login or email address
     */
    public function recordRecovery(string $login): void
    {
        foreach ($this->getRecoveryScopes($login) as $scope => $identifier) {
            $this->bump($scope, $identifier);
        }
    }

    /**
     * Clear counters after a successful authentication.
     *
     * The address counter is deliberately kept: letting a success reset it
     * would hand an attacker holding one valid account a way to wipe its own
     * trail between attempts. What the account holder clears is what stood in
     * their way -- their account, and their account from here.
     *
     * @param string $login Login that just authenticated
     */
    public function recordSuccess(string $login): void
    {
        foreach ($this->getScopes($login) as $scope => $identifier) {
            if ($scope !== self::SCOPE_IP) {
                $this->remove($scope, $identifier);
            }
        }
    }

    /**
     * Counters standing in somebody's way right now.
     *
     * What a member on the telephone needs is somebody able to see the lock and
     * lift it; nothing else here can be read from the interface.
     *
     * @return array<int, array{id: int, scope: string, login: ?string, address: ?string, failures: int, until: string}>
     */
    public function getLocks(): array
    {
        $locks = [];

        try {
            $select = $this->zdb->select(self::TABLE);
            $select->where->greaterThan('locked_until', (new DateTime())->format('Y-m-d H:i:s'));
            $select->order('locked_until DESC');

            foreach ($this->zdb->execute($select) as $row) {
                $scope = (string)$row->scope;
                $identifier = (string)$row->identifier;
                $login = null;
                $address = null;

                if ($scope === self::SCOPE_ACCOUNT_IP) {
                    $parts = explode(self::IDENTIFIER_SEPARATOR, $identifier);
                    $address = array_pop($parts);
                    $login = implode(self::IDENTIFIER_SEPARATOR, $parts);
                } elseif (
                    $scope === self::SCOPE_IP
                    || $scope === self::SCOPE_RECOVERY_IP
                    || $scope === self::SCOPE_SUBSCRIBE_IP
                ) {
                    $address = $identifier;
                } elseif (self::normalizeAddress($identifier) === $identifier) {
                    //a scope of its own, counting what looks like an address
                    $address = $identifier;
                } else {
                    $login = $identifier;
                }

                $locks[] = [
                    'id'       => (int)$row->{self::PK},
                    'scope'    => $scope,
                    'login'    => $login,
                    'address'  => $address,
                    'failures' => (int)$row->failures,
                    'until'    => (string)$row->locked_until
                ];
            }
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred listing authentication attempts. ' . $e->getMessage(),
                Analog::WARNING
            );
        }

        return $locks;
    }

    /**
     * Lift one counter
     *
     * @param int $id Counter identifier, as listed
     */
    public function release(int $id): bool
    {
        try {
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where([self::PK => $id]);
            return $this->zdb->execute($delete)->count() > 0;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred releasing authentication attempts. ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Lift every counter
     */
    public function releaseAll(): bool
    {
        try {
            $this->zdb->execute($this->zdb->delete(self::TABLE));
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred releasing authentication attempts. ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Drop counters that can no longer delay anyone
     */
    public function cleanExpired(): bool
    {
        try {
            //a row is of no use to anyone once its window has gone by and its
            //lock has expired
            $keep = max(
                $this->preferences->pref_throttle_account_window,
                $this->preferences->pref_throttle_account_ip_window,
                $this->preferences->pref_throttle_ip_window,
                $this->preferences->pref_throttle_recovery_window,
                $this->preferences->pref_throttle_subscribe_window,
                $this->preferences->pref_throttle_delay
            );
            $limit = new DateTime();
            $limit->modify('-' . max(1, $keep) . ' seconds');

            $delete = $this->zdb->delete(self::TABLE);
            $delete->where->lessThan('last_failure', $limit->format('Y-m-d H:i:s'));
            $delete->where->nest()
                ->isNull('locked_until')
                ->or
                ->lessThan('locked_until', $limit->format('Y-m-d H:i:s'));

            $this->zdb->execute($delete);
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred cleaning authentication attempts. ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Group a client address the way it is counted.
     *
     * An IPv6 end host commonly owns a whole /64, so counting exact addresses
     * would leave an attacker an enormous margin. IPv4 addresses are counted
     * as they are.
     *
     * @param string $address Client address
     *
     * @return string grouped address, empty when unusable
     */
    public static function normalizeAddress(string $address): string
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $address;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return '';
        }

        try {
            $packed = inet_pton($address);
            return inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8)) . '/64';
        } catch (Throwable $e) {
            Analog::log(
                'Cannot group client address ' . $address . '. ' . $e->getMessage(),
                Analog::WARNING
            );
            return '';
        }
    }

    /**
     * Scopes a submitted login is counted on, as scope => identifier.
     *
     * An account counter is only opened for a login that exists, so that
     * invented logins cannot be used to inflate the table; those are still
     * caught by the address counter.
     *
     * @param string $login Submitted login
     *
     * @return array<string, string>
     */
    private function getScopes(string $login): array
    {
        $scopes = [];
        $submitted = trim($login) !== '';
        $address = self::normalizeAddress($this->clientAddress());

        //the backstop is meant for an attack on a real account; an invented
        //login must not be able to fill it with counters nobody will ever read
        if ($submitted && $this->accountExists($login)) {
            $scopes[self::SCOPE_ACCOUNT] = $login;
        }
        if ($address !== '') {
            $scopes[self::SCOPE_IP] = $address;
        }
        if ($submitted && $address !== '') {
            //counted whether the account exists or not, so that being refused
            //says nothing about whether it does. The address counter bounds how
            //many of these one address can open before it is refused itself.
            //An address never holds the separator, so the pair reads back
            //unambiguously -- and the column is wide enough for both.
            $scopes[self::SCOPE_ACCOUNT_IP] = substr($login, 0, 200)
                . self::IDENTIFIER_SEPARATOR . $address;
        }

        return $scopes;
    }

    /**
     * Scopes a password recovery request is counted on, as scope => identifier
     *
     * @param string $login Submitted login or email address
     *
     * @return array<string, string>
     */
    private function getRecoveryScopes(string $login): array
    {
        $scopes = [];
        $address = self::normalizeAddress($this->clientAddress());

        if (trim($login) !== '' && $this->accountExists($login)) {
            $scopes[self::SCOPE_RECOVERY_ACCOUNT] = $login;
        }
        if ($address !== '') {
            $scopes[self::SCOPE_RECOVERY_IP] = $address;
        }

        return $scopes;
    }

    /**
     * Scopes a self subscription is counted on, as scope => identifier
     *
     * @return array<string, string>
     */
    private function getSubscribeScopes(): array
    {
        $address = self::normalizeAddress($this->clientAddress());

        return $address === '' ? [] : [self::SCOPE_SUBSCRIBE_IP => $address];
    }

    /**
     * Address the attempt comes from.
     *
     * Kept apart so that a test can walk through several of them: what the
     * account scope is there for cannot be exercised from a single address.
     */
    protected function clientAddress(): string
    {
        return PHP_SAPI === 'cli' ? '127.0.0.1' : History::findUserIPAddress();
    }

    /**
     * Does the submitted login designate an account?
     *
     * @param string $login Submitted login
     */
    private function accountExists(string $login): bool
    {
        if ($login === $this->preferences->pref_admin_login) {
            return true;
        }

        try {
            $select = $this->zdb->select(Adherent::TABLE);
            $select->columns([Adherent::PK])->limit(1);
            $select->where(
                [
                    'login_adh' => $login,
                    'email_adh' => $login
                ],
                PredicateSet::OP_OR
            );
            return $this->zdb->execute($select)->count() > 0;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot check whether login exists for throttling. ' . $e->getMessage(),
                Analog::WARNING
            );
            //do not open an account counter we are not sure about
            return false;
        }
    }

    /**
     * Count one more failure on a scope, and lock it when it is due
     *
     * @param string $scope      Scope name
     * @param string $identifier Login or grouped address
     */
    private function bump(string $scope, string $identifier, ?int $attempts = null, ?int $window = null): void
    {
        $row = $this->getRow($scope, $identifier);
        $now = new DateTime();

        $failures = 1;
        if ($row !== null) {
            $failures = (int)$row->failures + 1;
            if ($this->windowElapsed(scope: $scope, row: $row, now: $now, window: $window)) {
                //the sliding window moved past the whole series, start over
                $failures = 1;
            }
        }

        $duration = $this->lockDuration($scope, $failures, $attempts);
        $values = [
            'failures'      => $failures,
            'last_failure'  => $now->format('Y-m-d H:i:s'),
            'locked_until'  => $duration > 0
                ? (clone $now)->modify('+' . $duration . ' seconds')->format('Y-m-d H:i:s')
                : null
        ];

        if ($row === null || $failures === 1) {
            $values['first_failure'] = $now->format('Y-m-d H:i:s');
        }

        if ($row === null) {
            $query = $this->zdb->insert(self::TABLE);
            $query->values($values + ['scope' => $scope, 'identifier' => $identifier]);
        } else {
            $query = $this->zdb->update(self::TABLE);
            $query->set($values)->where([self::PK => $row->{self::PK}]);
        }

        try {
            $this->zdb->execute($query);
        } catch (Throwable $e) {
            if ($this->zdb->isDuplicateException($query, $e)) {
                //a concurrent attempt inserted the row first, its count stands
                return;
            }
            Analog::log(
                'An error occurred recording an authentication failure. ' . $e->getMessage(),
                Analog::WARNING
            );
        }
    }

    /**
     * Has the counting window moved past the recorded series?
     *
     * Every scope forgets, over its own window: a series of failures says
     * something about an attack only as long as it is recent. The account scope
     * is given a much longer one, since what it looks for -- an attack spread
     * over many addresses -- is also what can be spread over time.
     *
     * @param string                          $scope Scope name
     * @param ArrayObject<string, int|string> $row   Stored counter
     * @param DateTime                        $now   Current time
     */
    private function windowElapsed(string $scope, ArrayObject $row, DateTime $now, ?int $window): bool
    {
        if ($row->last_failure === null) {
            return false;
        }

        $seconds = $this->window($scope, $window);

        return (new DateTime((string)$row->last_failure))->getTimestamp() + $seconds < $now->getTimestamp();
    }

    /**
     * How long failures are counted on a scope
     *
     * @param string $scope Scope name
     *
     * @return int seconds, 0 to never forget
     */
    private function window(string $scope, ?int $override = null): int
    {
        if ($override !== null) {
            return max(self::MIN_SECONDS, $override);
        }

        return max(self::MIN_SECONDS, match ($scope) {
            self::SCOPE_ACCOUNT => $this->preferences->pref_throttle_account_window,
            self::SCOPE_IP => $this->preferences->pref_throttle_ip_window,
            self::SCOPE_RECOVERY_ACCOUNT,
            self::SCOPE_RECOVERY_IP => $this->preferences->pref_throttle_recovery_window,
            self::SCOPE_SUBSCRIBE_IP => $this->preferences->pref_throttle_subscribe_window,
            default => $this->preferences->pref_throttle_account_ip_window,
        });
    }

    /**
     * How many failures a scope takes before it locks
     *
     * @param string $scope Scope name
     *
     * @return int attempts, never below the floor
     */
    private function threshold(string $scope, ?int $override = null): int
    {
        if ($override !== null) {
            return max(self::MIN_ATTEMPTS, $override);
        }

        return max(self::MIN_ATTEMPTS, match ($scope) {
            self::SCOPE_ACCOUNT => $this->preferences->pref_throttle_account_attempts,
            self::SCOPE_IP => $this->preferences->pref_throttle_ip_attempts,
            self::SCOPE_RECOVERY_ACCOUNT,
            self::SCOPE_RECOVERY_IP => $this->preferences->pref_throttle_recovery_attempts,
            self::SCOPE_SUBSCRIBE_IP => $this->preferences->pref_throttle_subscribe_attempts,
            default => $this->preferences->pref_throttle_account_ip_attempts,
        });
    }

    /**
     * How long a scope stays locked after a given number of failures.
     *
     * One duration for the three of them, and a flat one. A delay growing with
     * each further failure used to be what kept the account scope from being a
     * way to keep a member out; that is now the job of counting an account and
     * an address together, which no third party can trip on someone else's
     * behalf.
     *
     * @param string $scope    Scope name
     * @param int    $failures Failures counted so far
     * @param ?int   $attempts Threshold of the caller, core preference when null
     *
     * @return int seconds, 0 when the threshold is not reached
     */
    private function lockDuration(string $scope, int $failures, ?int $attempts): int
    {
        if ($failures < $this->threshold($scope, $attempts)) {
            return 0;
        }

        return max(self::MIN_SECONDS, $this->preferences->pref_throttle_delay);
    }

    /**
     * Stored counter for a scope, if any
     *
     * @param string $scope      Scope name
     * @param string $identifier Login or grouped address
     *
     * @return ?ArrayObject<string, int|string>
     */
    private function getRow(string $scope, string $identifier): ?ArrayObject
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->where(['scope' => $scope, 'identifier' => $identifier])->limit(1);
            $results = $this->zdb->execute($select);
            return $results->count() > 0 ? $results->current() : null;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred getting authentication attempts. ' . $e->getMessage(),
                Analog::WARNING
            );
            return null;
        }
    }

    /**
     * Drop the counter of a scope
     *
     * @param string $scope      Scope name
     * @param string $identifier Login or grouped address
     */
    private function remove(string $scope, string $identifier): void
    {
        try {
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where(['scope' => $scope, 'identifier' => $identifier]);
            $this->zdb->execute($delete);
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred clearing authentication attempts. ' . $e->getMessage(),
                Analog::WARNING
            );
        }
    }
}
