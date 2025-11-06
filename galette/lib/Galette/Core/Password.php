<?php

/**
 * Copyright © 2003-2025 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Galette\Core;

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
    public const TABLE = 'tmppasswds';
    public const PK = Adherent::PK;

    /** @var integer Overrides default password size */
    public const DEFAULT_SIZE = 50;
    /** @var string Overrides default character set */
    protected string $chars = 'abcdefghjkmnpqrstuvwxyz0123456789&@{[]}%#+*:ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * Default constructor
     *
     * @param Db      $zdb   Database instance:
     * @param boolean $clean Whether we should clean expired passwords in database
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
     *
     * @return boolean
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
     *
     * @return boolean
     */
    public function generateNewPassword(int $id_adh): bool
    {
        //first of all, we'll remove all existant entries for specified id
        $this->removeOldEntries($id_adh);

        //second, generate a new password and store it in the database
        $password = $this->makeRandomPassword();
        $hash = password_hash($password, PASSWORD_BCRYPT);

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
            $this->setPassword($password);
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
     *
     * @return boolean
     */
    public function cleanExpired(): bool
    {
        $date = new \DateTime();
        $date->sub(new \DateInterval('PT24H'));

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
     * Check if requested hash is valid
     *
     * @param string $hash the hash
     *
     * @return false|int false if hash is not valid, member id otherwise
     */
    public function isHashValid(string $hash): false|int
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->columns(
                [self::PK]
            )->where(['tmp_passwd' => $hash]);

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
                'An error occurred getting requested hash. ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Remove a hash that has been used (ie. once password has been updated)
     *
     * @param string $hash hash
     *
     * @return boolean
     */
    public function removeHash(string $hash): bool
    {
        try {
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where(
                ['tmp_passwd' => $hash]
            );

            $this->zdb->execute($delete);
            Analog::log(
                'Used hash has been successfully remove',
                Analog::DEBUG
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred attempting to delete used hash'
                . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }
}
