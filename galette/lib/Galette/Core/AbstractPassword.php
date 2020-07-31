<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Abstract password for galette. The original code was found
 * in includes/functions.inc.php
 *
 * PHP version 5
 *
 * Copyright © 2003-2016 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Core
 * @package   Galette
 *
 * @author    Frédéric Jacquot <unknown@unknow.com>
 * @author    Georges Khaznadar (password encryption, images) <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2003-2016 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9dev - 2016-11-08
 */

namespace Galette\Core;

use Analog\Analog;
use Laminas\Db\Adapter\Exception as AdapterException;
use Galette\Entity\Adherent;

/**
 * Abstract password
 *
 * @category  Core
 * @name      AbstractPassword
 * @package   Galette
 * @author    Frédéric Jacquot <unknown@unknow.com>
 * @author    Georges Khaznadar (password encryption, images) <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2016 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9dev - 2016-11-08
 */

abstract class AbstractPassword
{
    /** Default password size */
    public const DEFAULT_SIZE = 8;

    protected $chars = 'abcdefghjkmnpqrstuvwxyz0123456789';
    protected $hash = null;
    protected $new_password;

    /**
     * Generates a random passord based on default salt
     *
     * @param int $size Password size (optionnal)
     *
     * @return string random password
     */
    public function makeRandomPassword($size = null)
    {
        if (
            $size === null
            || trim($size) == ''
            || !is_int($size)
        ) {
            $size = self::DEFAULT_SIZE;
        }
        $pass = '';
        $i = 0;
        while ($i <= $size - 1) {
            $num = mt_rand(0, 32) % 33;
            $pass .= substr($this->chars, $num, 1);
            $i++;
        }
        return $pass;
    }

    /**
     * Generates a new password for specified member
     *
     * @param mixed $arg Any argument required
     *
     * @return boolean
     */
    abstract public function generateNewPassword($arg);

    /**
     * Remove expired passwords queries (older than 24 hours)
     *
     * @return boolean
     */
    abstract protected function cleanExpired();

    /**
     * Retrieve new pasword for sending it to the user
     *
     * @return string the new password
     */
    public function getNewPassword()
    {
        return $this->new_password;
    }

    /**
     * Retrieve new hash
     *
     * @return string hash
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * Set password
     *
     * @param string $password Password
     *
     * @return void
     */
    protected function setPassword($password)
    {
        $this->new_password = $password;
    }

    /**
     * Set hash
     *
     * @param string $hash Hash
     *
     * @return void
     */
    protected function setHash($hash)
    {
        $this->hash = $hash;
    }
}
