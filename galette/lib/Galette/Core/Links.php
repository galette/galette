<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

use DateTime;
use Throwable;
use Analog\Analog;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;

/**
 * Temporary links for galette, to send direct links to invoices, receipts,
 * and member cards directly by email
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class Links
{
    public const TABLE = 'tmplinks';
    public const PK = 'hash';

    public const TARGET_MEMBERCARD = 1;
    public const TARGET_INVOICE    = 2;
    public const TARGET_RECEIPT    = 3;

    private Db $zdb;

    /**
     * Default constructor
     *
     * @param Db      $zdb   Database instance:
     * @param boolean $clean Whether we should clean expired links in database
     */
    public function __construct(Db $zdb, bool $clean = true)
    {
        $this->zdb = $zdb;
        if ($clean === true) {
            $this->cleanExpired();
        }
    }

    /**
     * Remove all old entry
     *
     * @param int $target Target (one of self::TARGET_* constants)
     * @param int $id     Target identifier
     *
     * @return boolean
     */
    private function removeOldEntry(int $target, int $id): bool
    {
        try {
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where([
                'target'    => $target,
                'id'        => $id
            ]);

            $this->zdb->execute($delete);
            Analog::log(
                'Temporary link for `' . $target . '-' . $id . '` has been removed.',
                Analog::DEBUG
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error has occurred removing old temporary link ' .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Generates a new link for specified target
     *
     * @param int $target Target (one of self::TARGET_* constants)
     * @param int $id     Target identifier
     *
     * @return string
     */
    public function generateNewLink(int $target, int $id): string
    {
        //first of all, we'll remove all existant entries for specified id
        $this->removeOldEntry($target, $id);

        //second, generate a new hash and store it in the database
        try {
            $select = $this->zdb->select(Adherent::TABLE);
            $select->columns([Adherent::PK, 'email_adh']);
            $id_adh = null;
            if ($target === Links::TARGET_MEMBERCARD) {
                $id_adh = $id;
            } else {
                //get member id from contribution
                $cselect = $this->zdb->select(Contribution::TABLE);
                $cselect->columns([Adherent::PK])->where([Contribution::PK => $id]);
                $cresults = $this->zdb->execute($cselect);
                $cresult = $cresults->current();
                $id_adh = $cresult->id_adh;
            }

            $select->where([Adherent::PK => $id_adh]);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $code = $result->email_adh;
            $hash = password_hash($code, PASSWORD_BCRYPT);

            $values = array(
                'target'        => $target,
                'id'            => $id,
                'creation_date' => date('Y-m-d H:i:s'),
                'hash'          => $hash
            );

            $insert = $this->zdb->insert(self::TABLE);
            $insert->values($values);

            $this->zdb->execute($insert);
            Analog::log(
                'New temporary link set for `' . $target . '-' . $id . '`.',
                Analog::DEBUG
            );
            return base64_encode($hash);
        } catch (Throwable $e) {
            Analog::log(
                "An error occurred trying to add temporary link entry. " .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Get expiration date
     *
     * @return DateTime
     */
    private function getExpirationDate(): DateTime
    {
        $date = new DateTime();
        $date->sub(new \DateInterval('P1W'));
        return $date;
    }

    /**
     * Remove expired links queries (older than 1 week)
     *
     * @return boolean
     */
    protected function cleanExpired(): bool
    {
        try {
            $date = $this->getExpirationDate();
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where->lessThan(
                'creation_date',
                $date->format('Y-m-d H:i:s')
            );
            $this->zdb->execute($delete);
            Analog::log(
                'Expired temporary links has been deleted.',
                Analog::DEBUG
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred deleting expired temporary links. ' .
                $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Check if requested hash is valid
     *
     * @param string $hash the hash, base64 encoded
     * @param string $code Code sent to validate link
     *
     * @return array<int,int>|false false if hash is not valid, array otherwise
     */
    public function isHashValid(string $hash, string $code): array|bool
    {
        try {
            $hash = base64_decode($hash);
            $select = $this->zdb->select(self::TABLE);
            $select->where(array('hash' => $hash));

            $date = $this->getExpirationDate();
            $select->where->greaterThanOrEqualTo(
                'creation_date',
                $date->format('Y-m-d')
            );

            $results = $this->zdb->execute($select);

            if ($results->count() > 0) {
                $result = $results->current();
                if (password_verify($code, $result->hash)) {
                    return [(int)$result->target, (int)$result->id];
                }
            }
            return false;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred getting requested hash. ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }
}
