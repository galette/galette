<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

/**
 * Abstract password
 *
 * @author Frédéric Jacquot <gna@logeek.com>
 * @author Georges Khaznadar (password encryption, images) <georges@unknow.org>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

abstract class AbstractPassword
{
    /** Default password size */
    public const int DEFAULT_SIZE = 8;

    protected string $chars = 'abcdefghjkmnpqrstuvwxyz0123456789';
    protected ?string $hash = null;
    protected string $new_password;

    /**
     * Generates a random string from the character set
     *
     * @param int|null $size Size (optional)
     *
     * @return string random string
     */
    public function makeRandomPassword(?int $size = null): string
    {
        $size ??= static::DEFAULT_SIZE;
        $pass = '';
        $i = 0;
        while ($i <= $size - 1) {
            $num = random_int(0, strlen($this->chars) - 1);
            $pass .= substr($this->chars, $num, 1);
            $i++;
        }
        return $pass;
    }

    /**
     * Generates a new password for specified member
     *
     * @param int $id_adh Member identifier
     */
    abstract public function generateNewPassword(int $id_adh): bool;

    /**
     * Remove expired passwords queries (older than 24 hours)
     */
    abstract protected function cleanExpired(): bool;

    /**
     * Retrieve new password for sending it to the user
     *
     * @return string the new password
     */
    public function getNewPassword(): string
    {
        return $this->new_password;
    }

    /**
     * Retrieve new hash
     *
     * @return string hash
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * Set password
     *
     * @param string $password Password
     */
    protected function setPassword(string $password): self
    {
        $this->new_password = $password;
        return $this;
    }

    /**
     * Set hash
     *
     * @param string $hash Hash
     */
    protected function setHash(string $hash): self
    {
        $this->hash = $hash;
        return $this;
    }
}
