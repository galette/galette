<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Default authentication class for galette
 *
 * PHP version 5
 *
 * Copyright © 2007-2011 The Galette Team
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
 * @category  Authentication
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-07-06
 */

/** @ignore */
require_once 'authentication.class.php';

/**
 * Default authentication class for galette
 *
 * @category  Authentication
 * @name      GaletteLogin
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-07-06
 */
class GaletteLogin extends Authentication
{
    const TABLE = 'adherents';
    const PK = 'login_adh';

    /**
    * Default constructor
    */
    public function __construct()
    {
    }

    /**
    * Logs in user.
    *
    * @param string $user  user's login
    * @param string $passe md5 hashed password
    *
    * @return integer state :
    *     '-1' if there were a database error
    *    '-10' if user cannot login (mistake or user doesn't exists)
    *    '1' if user were logged in successfully
    */
    public function logIn($user, $passe)
    {
        global $mdb, $log;

        $requete = 'SELECT id_adh, bool_admin_adh, nom_adh, prenom_adh, mdp_adh'.
            ', pref_lang, activite_adh FROM ' . PREFIX_DB . self::TABLE .
            ' WHERE ' . self::PK . '=\'' . $user. '\' AND mdp_adh=\'' .
            $passe . '\'';

        $result = $mdb->query($requete);
        if ( MDB2::isError($result) ) {
            return -1;
        }

        if ($result->numRows() == 0) {
            $log->log('No entry found for login `' . $user . '`', PEAR_LOG_WARNING);
            return(-10);
        } else {
            $log->log('User `' . $user . '` logged in.', PEAR_LOG_DEBUG);
            $row = $result->fetchRow();
            $this->id = $row->id_adh;
            $this->login = $row->login_adh;
            $this->passe = $row->mdp_adh;
            $this->admin = $row->bool_admin_adh;
            $this->name = $row->nom_adh;
            $this->surname = $row->prenom_adh;
            $this->lang = $row->pref_lang;
            $this->active = $row->activite_adh;
            $this->logged = true;
            //$this->upLastConn($this->login);
            return(1);
        }
    }

    /**
    * Does this login already exists ?
    * These function should be used for setting admin login into Preferences
    *
    * @param string $user the username
    *
    * @return true if the username already exists, false otherwise
    */
    public function loginExists($user)
    {
        global $mdb, $log;

        $requete = 'SELECT ' . self::PK . ' FROM ' . PREFIX_DB . self::TABLE .
            ' WHERE ' . self::PK . '=\'' . $user . '\'';

        /* If an error occurs, we consider that username already exists */
        $result = $mdb->query($requete);
        if ( MDB2::isError($result) ) {
            $log->log(
                'Unable to check if username `' . $user . '` already exists. ' .
                $stmt->getMessage() . '(' . $stmt->getDebugInfo() . ')',
                PEAR_LOG_WARNING
            );
            return true;
        }
        if ($result->numRows() == 0) {
            return false;
        } else {
            return true;
        }
    }

}
?>