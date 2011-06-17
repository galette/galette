<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Password for galette. The original code was found
 * in includes/functions.inc.php
 *
 * PHP version 5
 *
 * Copyright © 2003-2011 The Galette Team
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
 * @category  Classes
 * @package   Galette
 *
 * @author    Frédéric Jaqcuot <unknown@unknow.com>
 * @author    Georges Khaznadar (password encryption, images) <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2003-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-02-28
 */

/**
 * Abstract authentication class for galette
 *
 * @category  Classes
 * @name      Authentication
 * @package   Galette
 * @author    Frédéric Jaqcuot <unknown@unknow.com>
 * @author    Georges Khaznadar (password encryption, images) <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-06-16
 */

class GalettePassword
{

    const TABLE = 'tmppasswds';
    const PK = Adherent::PK;
    
    /** Default password size */
    private $_size = 8;
    private $_salt = 'abcdefghjkmnpqrstuvwxyz0123456789';
    private $_hash = null;
    private $_new_password;

    /**
    * Default constructor
    */
    public function __construct()
    {
        $this->_cleanExpired();
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
        srand((double)microtime()*1000000);
        $i = 0;
        while ( $i <= $size-1 ) {
            $num = rand() % 33;
            $pass .= substr($this->_salt, $num, 1);
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
        global $log, $mdb;

        $requete = 'DELETE FROM ' . PREFIX_DB . self::TABLE . ' WHERE ' .
        self::PK . '=' . $id_adh;

        $result = $mdb->query($requete);
        if ( MDB2::isError($result) ) {
            $log->log(
                'An error has occured removing old tmppasswords ' .
                $result->getMessage() . '(' . $result->getDebugInfo() . ')',
                PEAR_LOG_ERR
            );
            return false;
        } else {
            $log->log(
                'Temporary passwords for `' . $id_adh . '` has been removed.',
                PEAR_LOG_DEBUG
            );
            return true;
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
        global $log, $mdb;

        MDB2::loadFile('Date');

        //first of all, we'll remove all existant entries for specified id
        $this->_removeOldEntries($id_adh);

        //second, generate a new password and store it in the database
        $password = $this->makeRandomPassword();
        $hash = md5($password);

        $requete = 'INSERT INTO ' . PREFIX_DB . self::TABLE . ' (' . self::PK .
        ', tmp_passwd, date_crea_tmp_passwd) VALUES (' . $id_adh . ', \'' .
        $hash . '\', \'' . MDB2_Date::mdbNow() . '\')';

        $result = $mdb->query($requete);
        if ( MDB2::isError($result) ) {
            $log->log(
                'An error has occured storing new password' .
                $result->getMessage() . '(' . $result->getDebugInfo() . ')',
                PEAR_LOG_ERR
            );
            return false;
        } else {
            $log->log(
                'New passwords temporary set for `' . $id_adh . '`.',
                PEAR_LOG_DEBUG
            );
            $this->_new_password = $password;
            $this->_hash = $hash;
            return true;
        }
    }

    private function _cleanExpired()
    {
        global $log, $mdb;

        $date = new DateTime();
        $date->sub(new DateInterval('PT24H'));

        $requete = 'DELETE FROM ' . PREFIX_DB . self::TABLE .
        ' WHERE date_crea_tmp_passwd < \'' . $date->format('Y-m-d H:i:s') . '\'';

        $result = $mdb->query($requete);
        if ( MDB2::isError($result) ) {
            $log->log(
                'An error occured deleting expired temporary passwords',
                PEAR_LOG_WARNING
            );
            return false;
        } else {
            $log->log(
                'Old Temporary passwords has been deleted.',
                PEAR_LOG_DEBUG
            );
            return true;
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
}
?>
