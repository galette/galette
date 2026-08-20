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
    /** Characters a generated login is drawn from: no '@', no ambiguous glyph */
    public const string LOGIN_CHARS = 'abcdefghjkmnpqrstuvwxyz0123456789';

    protected ?string $hash = null;
    protected string $token = '';

    /**
     * Generates a login, to fill up an account that has none - for instance a
     * member imported from a file that does not carry logins.
     *
     * @param int $size Login size
     *
     * @return string random login
     */
    public function makeRandomLogin(int $size = 15): string
    {
        return $this->makeRandomString(self::LOGIN_CHARS, $size);
    }

    /**
     * Generates a hash no password can match, to fill up an account that has no
     * password. The cleartext value is discarded on purpose: such an account
     * stays unusable until a password is set for it, its owner recovering it
     * through the lost password procedure.
     *
     * @return string password hash
     */
    public function makeUnusablePasswordHash(): string
    {
        return password_hash(base64_encode(random_bytes(32)), PASSWORD_BCRYPT);
    }

    /**
     * Draws a random string from a character set
     *
     * @param string $chars Characters to draw from
     * @param int    $size  Expected size
     *
     * @return string random string
     */
    private function makeRandomString(string $chars, int $size): string
    {
        $string = '';
        for ($i = 0; $i < $size; $i++) {
            $string .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $string;
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
     * Retrieve the token to send to the user
     *
     * @return string the token
     */
    public function getToken(): string
    {
        return $this->token;
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
     * Set token
     *
     * @param string $token Token
     */
    protected function setToken(string $token): self
    {
        $this->token = $token;
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
