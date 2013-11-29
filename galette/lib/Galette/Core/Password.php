<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Password for galette. The original code was found
 * in includes/functions.inc.php
 *
 * PHP version 5
 *
 * Copyright © 2003-2013 The Galette Team
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
 * @author    Frédéric Jaqcuot <unknown@unknow.com>
 * @author    Georges Khaznadar (password encryption, images) <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2003-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-02-28
 */

namespace Galette\Core;

use Analog\Analog as Analog;
use Galette\Entity\Adherent;

/**
 * Temporary password managment
 *
 * @category  Core
 * @name      Password
 * @package   Galette
 * @author    Frédéric Jaqcuot <unknown@unknow.com>
 * @author    Georges Khaznadar (password encryption, images) <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-06-16
 */

class Password
{

    const TABLE = 'tmppasswds';
    const PK = Adherent::PK;

    /** Default password size */
    private $_size = 8;
    private $_chars = 'abcdefghjkmnpqrstuvwxyz0123456789';
    private $_hash = null;
    private $_new_password;

    /**
     * Default constructor
     *
     * @param boolean $clean Whether we should clean expired passwords in database
     */
    public function __construct($clean = true)
    {
        if ( $clean === true ) {
            $this->cleanExpired();
        }
    }

    /**
     * Generates a random passord based on default salt
     *
     * @param int $size Password size (optionnal)
     *
     * @return string random password
     */
    public function makeRandomPassword($size = null)
    {
        if ( $size === null 
            || trim($size) == ''
            || !is_int($size)
        ) {
            $size = $this->_size;
        }
        $pass = '';
        $i = 0;
        while ( $i <= $size-1 ) {
            $num = mt_rand(0, 32) % 33;
            $pass .= substr($this->_chars, $num, 1);
            $i++;
        }
        return $pass;
    }

    /**
     * Remove all old password entries
     *
     * @param int $id_adh Member identifier
     *
     * @return boolean
     */
    private function _removeOldEntries($id_adh)
    {
        global $zdb;

        try {
            $del = $zdb->db->delete(
                PREFIX_DB . self::TABLE,
                $zdb->db->quoteInto(
                    self::PK . ' = ?',
                    $id_adh
                )
            );
            if ( $del ) {
                Analog::log(
                    'Temporary passwords for `' . $id_adh . '` has been removed.',
                    Analog::DEBUG
                );
            }
        } catch (\Exception $e) {
            /** TODO */
            Analog::log(
                'An error has occured removing old tmppasswords ' .
                $e->getMessage(),
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
    public function generateNewPassword($id_adh)
    {
        global $zdb;

        //first of all, we'll remove all existant entries for specified id
        $this->_removeOldEntries($id_adh);

        //second, generate a new password and store it in the database
        $password = $this->makeRandomPassword();
        $hash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $values = array(
                self::PK               => $id_adh,
                'tmp_passwd'           => $hash,
                'date_crea_tmp_passwd' => date('Y-m-d H:i:s')
            );

            $add = $zdb->db->insert(PREFIX_DB . self::TABLE, $values);
            if ( $add ) {
                Analog::log(
                    'New passwords temporary set for `' . $id_adh . '`.',
                    Analog::DEBUG
                );
                $this->_new_password = $password;
                $this->_hash = $hash;
                return true;
            } else {
                return false;
            }
        } catch (\Zend_Db_Adapter_Exception $e) {
            Analog::log(
                'Unable to add add new password entry into database.' .
                $e->getMessage(),
                Analog::WARNING
            );
            return false;
        } catch (\Exception $e) {
            Analog::log(
                "An error occured trying to add temporary password entry. " .
                $e->getMessage(),
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
    protected function cleanExpired()
    {
        global $zdb;

        $date = new \DateTime();
        $date->sub(new \DateInterval('PT24H'));

        try {
            $del = $zdb->db->delete(
                PREFIX_DB . self::TABLE,
                $zdb->db->quoteInto(
                    'date_crea_tmp_passwd < ?',
                    $date->format('Y-m-d H:i:s')
                )
            );
            if ( $del ) {
                Analog::log(
                    'Old Temporary passwords has been deleted.',
                    Analog::DEBUG
                );
            }
        } catch (\Exception $e) {
            /** TODO */
            Analog::log(
                'An error occured deleting expired temporary passwords. ' .
                $e->getMessage(),
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
     * @return false if hash is not valid, member id otherwise
     */
    public function isHashValid($hash)
    {
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(
                PREFIX_DB . self::TABLE,
                self::PK
            )->where('tmp_passwd = ?', $hash);
            return $select->query()->fetchColumn();
        } catch (\Exception $e) {
            /** TODO */
            Analog::log(
                'An error occured getting requested hash. ' . $e->getMessage(),
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
    public function removeHash($hash)
    {
        global $zdb;

        try {
            $del = $zdb->db->delete(
                PREFIX_DB . self::TABLE,
                $zdb->db->quoteInto(
                    'tmp_passwd = ?',
                    $hash
                )
            );
            if ( $del ) {
                Analog::log(
                    'Used hash has been successfully remove',
                    Analog::DEBUG
                );
                return true;
            }
        } catch (\Exception $e) {
            /** TODO */
            Analog::log(
                'An error ocured attempting to delete used hash' .
                $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Retrieve new pasword for sending it to the user
     *
     * @return string the new password
     */
    public function getNewPassword()
    {
        return $this->_new_password;
    }

    /**
     * Retrieve new hash
     *
     * @return string hash
     */
    public function getHash()
    {
        return $this->_hash;
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
        $this->_new_password = $password;
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
        $this->_hash = $hash;
    }
}
