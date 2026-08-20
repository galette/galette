<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use DateInterval;
use Safe\DateTime;
use Throwable;
use Analog\Analog;
use Galette\Entity\Adherent;

/**
 * Temporary password management
 *
 * @author Frédéric Jacquot <gna@logeek.com>
 * @author Georges Khaznadar (password encryption, images) <georges@unknow.org>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class Password extends AbstractPassword
{
    public const string TABLE = 'tmppasswds';
    public const string PK = Adherent::PK;

    /** @var int Overrides default password size */
    public const int DEFAULT_SIZE = 50;
    /** @var string Overrides default character set */
    protected string $chars = 'abcdefghjkmnpqrstuvwxyz0123456789&@{[]}%#+*:ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * Default constructor
     *
     * @param Db   $zdb   Database instance:
     * @param bool $clean Whether we should clean expired passwords in database
     */
    public function __construct(private readonly Db $zdb, bool $clean = true)
    {
        if ($clean === true) {
            $this->cleanExpired();
        }
    }

    /**
     * Remove all old password entries
     *
     * @param int $id_adh Member identifier
     */
    private function removeOldEntries(int $id_adh): bool
    {
        try {
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where([self::PK => $id_adh]);

            $this->zdb->execute($delete);
            Analog::log(
                'Temporary passwords for `' . $id_adh . '` has been removed.',
                Analog::DEBUG
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error has occurred removing old tmppasswords '
                . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Generates a new password for specified member
     *
     * @param int $id_adh Member identifier
     */
    public function generateNewPassword(int $id_adh): bool
    {
        //first of all, we'll remove all existant entries for specified id
        $this->removeOldEntries($id_adh);

        //second, generate a token and store its hash in the database. Only the
        //token travels, by email; the stored value cannot be used as a link.
        $token = bin2hex(random_bytes(32));
        $hash = self::hashToken($token);

        try {
            $values = [
                self::PK               => $id_adh,
                'tmp_passwd'           => $hash,
                'date_crea_tmp_passwd' => date('Y-m-d H:i:s')
            ];

            $insert = $this->zdb->insert(self::TABLE);
            $insert->values($values);

            $this->zdb->execute($insert);
            Analog::log(
                'New passwords temporary set for `' . $id_adh . '`.',
                Analog::DEBUG
            );
            $this->setToken($token);
            $this->setHash($hash);
            return true;
        } catch (Throwable $e) {
            Analog::log(
                "An error occurred trying to add temporary password entry. "
                . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Remove expired passwords queries (older than 24 hours)
     */
    public function cleanExpired(): bool
    {
        $date = new DateTime();
        $date->sub(new DateInterval('PT24H'));

        try {
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where->lessThan(
                'date_crea_tmp_passwd',
                $date->format('Y-m-d H:i:s')
            );
            $this->zdb->execute($delete);
            Analog::log(
                'Old Temporary passwords have been deleted.',
                Analog::DEBUG
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred deleting expired temporary passwords. '
                . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Hash a token the way it is stored
     *
     * @param string $token the token
     */
    private static function hashToken(string $token): string
    {
        //a plain sha256 is enough, and required to be able to look the token up:
        //the token holds 256 bits of entropy, it cannot be guessed nor brute
        //forced. This is not a password.
        return hash('sha256', $token);
    }

    /**
     * Check if requested token is valid
     *
     * @param string $token the token
     *
     * @return false|int false if token is not valid, member id otherwise
     */
    public function isTokenValid(string $token): false|int
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->columns(
                [self::PK]
            )->where(['tmp_passwd' => self::hashToken($token)]);

            $results = $this->zdb->execute($select);

            if ($results->count() > 0) {
                $result = $results->current();
                $pk = self::PK;
                return (int)$result->$pk;
            } else {
                return false;
            }
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred getting requested token. ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Remove a token that has been used (ie. once password has been updated)
     *
     * @param string $token token
     */
    public function removeToken(string $token): bool
    {
        try {
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where(
                ['tmp_passwd' => self::hashToken($token)]
            );

            $this->zdb->execute($delete);
            Analog::log(
                'Used token has been successfully removed',
                Analog::DEBUG
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred attempting to delete used token'
                . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }
}
