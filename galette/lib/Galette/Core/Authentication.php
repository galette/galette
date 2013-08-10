<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Abstract authentication class for galette
 *
 * PHP version 5
 *
 * Copyright © 2009-2013 The Galette Team
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
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-02-28
 */

namespace Galette\Core;

/**
 * Abstract authentication class for galette
 *
 * @category  Authentication
 * @name      Authentication
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-02-28
 */

abstract class Authentication
{
    private $_login;
    private $_passe;
    private $_name;
    private $_surname;
    private $_admin = false;
    private $_id;
    private $_lang;
    private $_logged = false;
    private $_active = false;
    private $_superadmin = false;
    private $_staff = false;
    private $_uptodate = false;
    private $_managed_groups;
    private $_cron = false;

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
    * @param string $passe user's password
    *
    * @return boolean
    */
    abstract public function logIn($user, $passe);

    /**
    * Does this login already exists ?
    * These function should be used for setting admin login into Preferences
    *
    * @param string $user the username
    *
    * @return true if the username already exists, false otherwise
    */
    abstract public function loginExists($user);

    /**
    * Login for the superuser
    *
    * @param string $login name
    *
    * @return void
    */
    public function logAdmin($login)
    {
        global $preferences;

        $this->_logged = true;
        $this->_name = 'Admin';
        $this->_login = $login;
        $this->_admin = true;
        $this->_active = true;
        $this->_staff = false;
        $this->_uptodate = false;
        $this->_id = 0;
        $this->_lang = $preferences->pref_lang;
        //a flag for super admin only, since it's not a regular user
        $this->_superadmin = true;
    }

    /**
     * Authenticate from cron
     *
     * @param string $name Service name
     *
     * @return void
     */
    public function logCron($name)
    {
        //known cronable files
        $ok = array('reminder');

        if ( in_array($name, $ok) ) {
            $this->_logged = true;
            $this->_cron = true;
            $this->login = 'cron';
        } else {
            die('Not authorized!');
        }
    }

    /**
    * Log out user and unset variables
    *
    * @return void
    */
    public function logOut()
    {
        $this->_id = null;
        $this->_logged = false;
        $this->_name = null;
        $this->_login = null;
        $this->_admin = false;
        $this->_active = false;
        $this->_superadmin = false;
        $this->_staff = false;
        $this->_uptodate = false;
    }

    /**
    * Is user logged-in?
    *
    * @return bool
    */
    public function isLogged()
    {
        return $this->_logged;
    }

    /**
    * Is user admin?
    *
    * @return bool
    */
    public function isAdmin()
    {
        return $this->_admin;
    }

    /**
    * Is user super admin?
    *
    * @return bool
    */
    public function isSuperAdmin()
    {
        return $this->_superadmin;
    }

    /**
    * Is user active?
    *
    * @return bool
    */
    public function isActive()
    {
        return $this->_active;
    }

    /**
     * Is user member of staff?
     *
     * @return bool
     */
    public function isStaff()
    {
        return $this->_staff;
    }

    /**
     * is user a crontab?
     *
     * @return bool
     */
    public function isCron()
    {
        return $this->_cron;
    }

    /**
     * Is user a group manager?
     * If no group id is specified, check if user is manager for at
     * least one group.
     *
     * @param int $id_group Group identifier
     *
     * @return boolean
     */
    public function isGroupManager($id_group = null)
    {
        if ( $this->isAdmin() || $this->isStaff() ) {
            return true;
        } else {
            if ( $id_group === null ) {
                return count($this->_managed_groups) > 0;
            } else {
                return in_array($id_group, $this->_managed_groups);
            }
        }
    }

    /**
     * Is user currently up to date?
     * An up to date member is active and either due free, or with up to date
     * subscription
     *
     * @return bool
     */
    public function isUp2Date()
    {
        return $this->_uptodate;
    }

    /**
     * Display logged in member name
     *
     * @param boolean $only_name If we want only the name without any additional text
     *
     * @return String
     */
    public function loggedInAs($only_name=false)
    {
        $n = $this->_name . ' ' . $this->_surname . ' (' . $this->_login . ')';
        if ( $only_name === false ) {
            return str_replace(
                '%login',
                $n,
                _T("Logged in as:<br/>%login")
            );
        } else {
            return $n;
        }
    }

    /**
    * Global getter method
    *
    * @param string $name name of the property we want to retrieve
    *
    * @return false|object the called property
    */
    public function __get($name)
    {
        $forbidden = array('logged', 'admin', 'active');
        $rname = '_' . $name;
        if ( !in_array($name, $forbidden) && isset($this->$rname) ) {
            return $this->$rname;
        } else {
            return false;
        }
    }

    /**
    * Global setter method
    *
    * @param string $name  name of the property we want to assign a value to
    * @param object $value a relevant value for the property
    *
    * @return void
    */
    public function __set($name, $value)
    {
        $name = '_' . $name;
        $this->$name = $value;
    }
}
