<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Analog\Analog;
use Galette\Entity\Adherent;
use Throwable;

use function Safe\preg_match;

/**
 * Storage of a member's second factor: its shared secret, and the recovery
 * codes that let them back in without it.
 *
 * Not an AbstractEntity: that base class assumes a generated primary key --
 * it strips the key before insert and reads it back from the sequence -- while
 * here the key is the member identifier, supplied by the caller. Same shape as
 * Core\Password, which is modelled the same way.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TwoFactorSecret implements TwoFactorStore
{
    public const string TABLE = 'twofactor';
    public const string PK = Adherent::PK;
    public const string CODES_TABLE = 'twofactor_codes';

    /** How many recovery codes are handed out at once */
    public const int CODES_COUNT = 10;

    /** Shape of a recovery code, as generated below */
    private const string CODE_FORMAT = '/^[0-9A-F]{5}-[0-9A-F]{5}$/';

    private ?int $id_adh = null;
    private string $secret = '';
    private bool $enabled = false;
    private ?int $last_timeslice = null;

    /**
     * Default constructor
     *
     * @param Db $zdb Database instance
     */
    public function __construct(private readonly Db $zdb)
    {
    }

    /**
     * Load the second factor of a member, if any
     *
     * @param int $id_adh Member identifier
     */
    public function load(int $id_adh): bool
    {
        $this->reset();

        try {
            $select = $this->zdb->select(self::TABLE);
            $select->where([self::PK => $id_adh])->limit(1);
            $results = $this->zdb->execute($select);

            if ($results->count() === 0) {
                return false;
            }

            $row = $results->current();
            $this->id_adh = (int)$row->{self::PK};
            $this->secret = (string)$row->secret;
            $this->enabled = (bool)$row->enabled;
            $this->last_timeslice = $row->last_timeslice === null ? null : (int)$row->last_timeslice;
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot load second factor for member ' . $id_adh . '. ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Store a freshly generated secret, not usable until confirmed.
     *
     * Any previous secret of that member is replaced: enrolling again must not
     * leave an older secret able to authenticate.
     *
     * @param int    $id_adh Member identifier
     * @param string $secret Base32 shared secret
     */
    public function create(int $id_adh, string $secret): bool
    {
        try {
            $this->remove($id_adh);

            $insert = $this->zdb->insert(self::TABLE);
            $insert->values(
                [
                    self::PK    => $id_adh,
                    'secret'    => $secret,
                    'enabled'   => $this->zdb->isPostgres() ? 'false' : 0,
                    'date_crea' => date('Y-m-d H:i:s')
                ]
            );
            $this->zdb->execute($insert);

            $this->id_adh = $id_adh;
            $this->secret = $secret;
            $this->enabled = false;
            $this->last_timeslice = null;
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot store second factor for member ' . $id_adh . '. ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Turn the loaded second factor on, the member having proved they hold the
     * secret
     */
    public function enable(): bool
    {
        if ($this->id_adh === null) {
            throw new \RuntimeException('No second factor loaded!');
        }

        $update = $this->zdb->update(self::TABLE);
        $update->set(
            [
                'enabled'      => $this->zdb->isPostgres() ? 'true' : 1,
                'date_confirm' => date('Y-m-d H:i:s')
            ]
        )->where([self::PK => $this->id_adh]);
        $this->zdb->execute($update);

        $this->enabled = true;
        return true;
    }

    /**
     * Remember the last accepted time slice.
     *
     * This is what makes a code single use: a TOTP code stays valid for the
     * whole tolerance window, so without it an intercepted code could be
     * replayed.
     *
     * @param int $timeslice Accepted time slice
     */
    public function setLastTimeslice(int $timeslice): bool
    {
        if ($this->id_adh === null) {
            throw new \RuntimeException('No second factor loaded!');
        }

        $update = $this->zdb->update(self::TABLE);
        $update->set(['last_timeslice' => $timeslice])
            ->where([self::PK => $this->id_adh]);
        $this->zdb->execute($update);

        $this->last_timeslice = $timeslice;
        return true;
    }

    /**
     * Drop the second factor of a member, recovery codes included
     *
     * @param int|null $id_adh Member identifier, the loaded one by default
     */
    public function remove(?int $id_adh = null): bool
    {
        $id_adh ??= $this->id_adh;
        if ($id_adh === null) {
            throw new \RuntimeException('No member to remove second factor for!');
        }

        try {
            //recovery codes go first: they are meaningless without the secret,
            //and the cascade only applies when the member itself is removed
            $delete = $this->zdb->delete(self::CODES_TABLE);
            $delete->where([self::PK => $id_adh]);
            $this->zdb->execute($delete);

            $delete = $this->zdb->delete(self::TABLE);
            $delete->where([self::PK => $id_adh]);
            $this->zdb->execute($delete);

            if ($id_adh === $this->id_adh) {
                $this->reset();
            }
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot remove second factor for member ' . $id_adh . '. ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Hand out a fresh set of recovery codes, replacing any previous one.
     *
     * The cleartext codes are returned once and never stored: only their hash
     * is kept, so a read access to the table does not hand over usable codes.
     *
     * @param int $count How many codes
     *
     * @return string[] cleartext codes, to be shown once
     */
    public function generateRecoveryCodes(int $count = self::CODES_COUNT): array
    {
        if ($this->id_adh === null) {
            throw new \RuntimeException('No second factor loaded!');
        }

        $delete = $this->zdb->delete(self::CODES_TABLE);
        $delete->where([self::PK => $this->id_adh]);
        $this->zdb->execute($delete);

        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            //grouped in blocks so it can be read out loud and typed back
            $code = strtoupper(
                implode('-', str_split(bin2hex(random_bytes(5)), 5))
            );
            $codes[] = $code;

            $insert = $this->zdb->insert(self::CODES_TABLE);
            $insert->values(
                [
                    self::PK => $this->id_adh,
                    'code'   => password_hash($code, PASSWORD_BCRYPT)
                ]
            );
            $this->zdb->execute($insert);
        }

        return $codes;
    }

    /**
     * Spend a recovery code.
     *
     * A used code is dropped rather than flagged: keeping it would let a stale
     * hash sit in the table for no purpose.
     *
     * @param string $code Code as typed by the member
     */
    public function consumeRecoveryCode(string $code): bool
    {
        if ($this->id_adh === null) {
            throw new \RuntimeException('No second factor loaded!');
        }

        //anything that is not shaped like a recovery code cannot be one, and a
        //six digit code that got here is a mistyped time based one. Checking it
        //anyway would spend ten bcrypt rounds per wrong attempt, and the time
        //taken would say how many codes are left.
        $code = strtoupper(trim($code));
        if (!preg_match(self::CODE_FORMAT, $code)) {
            return false;
        }

        $select = $this->zdb->select(self::CODES_TABLE);
        $select->where([self::PK => $this->id_adh, 'date_used' => null]);

        foreach ($this->zdb->execute($select) as $row) {
            //hashes are salted, so every candidate has to be checked
            if (password_verify($code, (string)$row->code)) {
                $delete = $this->zdb->delete(self::CODES_TABLE);
                $delete->where(['id_code' => $row->id_code]);
                $this->zdb->execute($delete);
                return true;
            }
        }

        return false;
    }

    /**
     * How many recovery codes the member has left
     */
    public function countRemainingCodes(): int
    {
        if ($this->id_adh === null) {
            return 0;
        }

        $select = $this->zdb->select(self::CODES_TABLE);
        $select->where([self::PK => $this->id_adh, 'date_used' => null]);
        return $this->zdb->execute($select)->count();
    }

    /**
     * Is a second factor loaded?
     */
    public function isLoaded(): bool
    {
        return $this->id_adh !== null;
    }

    /**
     * Is the loaded second factor usable?
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Shared secret of the loaded second factor
     */
    public function getSecret(): string
    {
        return $this->secret;
    }

    /**
     * Member the loaded second factor belongs to
     */
    public function getMemberId(): ?int
    {
        return $this->id_adh;
    }

    /**
     * Who the loaded second factor belongs to, for logs
     */
    public function getOwner(): string
    {
        return 'member ' . ($this->id_adh ?? '?');
    }

    /**
     * Last accepted time slice, null when none has been recorded yet
     */
    public function getLastTimeslice(): ?int
    {
        return $this->last_timeslice;
    }

    /**
     * Forget what is loaded
     */
    private function reset(): void
    {
        $this->id_adh = null;
        $this->secret = '';
        $this->enabled = false;
        $this->last_timeslice = null;
    }
}
