<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Abstract authentication class for galette
 *
 * PHP version 5
 *
 * Copyright © 2009-2024 The Galette Team
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
 *
 * @category  Authentication
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.7dev - 2009-02-28
 */

namespace Galette\Core;

use Galette\Entity\Group;

/**
 * Abstract authentication class for galette
 *
 * @category  Authentication
 * @name      Authentication
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.7dev - 2009-02-28
 *
 * @property  ?string $login
 * @property  ?string $name
 * @property  ?string $surname
 * @property  ?integer $id
 * @property  string $lang
 * @property  array<int, Group|int> $managed_groups
 */

abstract class Authentication
{
    public const ACCESS_USER = 0;
    public const ACCESS_MANAGER = 1;
    public const ACCESS_STAFF = 2;
    public const ACCESS_ADMIN = 3;
    public const ACCESS_SUPERADMIN = 4;

    protected string $login;
    protected string $name;
    protected ?string $surname;
    protected bool $admin = false;
    protected int $id;
    protected string $lang;
    protected bool $logged = false;
    protected bool $active = false;
    protected bool $superadmin = false;
    protected bool $staff = false;
    protected bool $uptodate = false;
    /** @var array<int, Group|int> */
    protected array $managed_groups = [];
    protected bool $cron = false;

    /**
     * Logs in user.
     *
     * @param string $user  user's login
     * @param string $passe user's password
     *
     * @return boolean
     */
    abstract public function logIn(string $user, string $passe): bool;

    /**
     * Does this login already exists ?
     * These function should be used for setting admin login into Preferences
     *
     * @param string $user the username
     *
     * @return boolean true if the username already exists, false otherwise
     */
    abstract public function loginExists(string $user): bool;

    /**
     * Login for the superuser
     *
     * @param string      $login       name
     * @param Preferences $preferences Preferences instance
     *
     * @return bool
     */
    public function logAdmin(string $login, Preferences $preferences): bool
    {
        $this->logged = true;
        $this->name = 'Admin';
        $this->login = $login;
        $this->admin = true;
        $this->active = true;
        $this->staff = false;
        $this->uptodate = false;
        $this->id = 0;
        $this->lang = $preferences->pref_lang;
        //a flag for super admin only, since it's not a regular user
        $this->superadmin = true;
        return true;
    }

    /**
     * Authenticate from cron
     *
     * @param string      $name        Service name
     * @param Preferences $preferences Preferences instance
     *
     * @return bool
     */
    abstract public function logCron(string $name, Preferences $preferences): bool;

    /**
     * Log out user and unset variables
     *
     * @return bool
     */
    public function logOut(): bool
    {
        unset($this->id);
        $this->logged = false;
        unset($this->name);
        unset($this->login);
        $this->admin = false;
        $this->active = false;
        $this->superadmin = false;
        $this->staff = false;
        $this->uptodate = false;
        unset($this->lang);
        return true;
    }

    /**
     * Is user logged-in?
     *
     * @return bool
     */
    public function isLogged(): bool
    {
        return $this->logged;
    }

    /**
     * Is user admin?
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->admin;
    }

    /**
     * Is user super admin?
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        return $this->superadmin;
    }

    /**
     * Is user active?
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Is user member of staff?
     *
     * @return bool
     */
    public function isStaff(): bool
    {
        return $this->staff;
    }

    /**
     * is user a crontab?
     *
     * @return bool
     */
    public function isCron(): bool
    {
        return $this->cron;
    }

    /**
     * Is user a group manager?
     * If no group id is specified, check if user is manager for at
     * least one group.
     *
     * @param array<int>|int $id_group Group(s) identifier(s)
     *
     * @return boolean
     */
    public function isGroupManager(array|int $id_group = null): bool
    {
        $manager = false;
        if ($this->isAdmin() || $this->isStaff()) {
            return true;
        } else {
            if ($id_group === null) {
                $manager = count($this->managed_groups) > 0;
            } else {
                $groups = is_array($id_group) ? $id_group : (array)$id_group;

                foreach ($groups as $group) {
                    if (in_array($group, $this->managed_groups)) {
                        $manager = true;
                        break;
                    }
                }
            }
        }
        return $manager;
    }

    /**
     * Get managed groups
     *
     * @return array<int, Group|int>
     */
    public function getManagedGroups(): array
    {
        return $this->managed_groups;
    }

    /**
     * Get compact menu mode
     *
     * @return bool
     */
    public function getCompactMenu(): bool
    {
        return $this->logged && isset($_COOKIE['galette_compact_menu']) && $_COOKIE['galette_compact_menu'];
    }

    /**
     * Is dark mode enabled?
     *
     * @return bool
     */
    public function isDarkModeEnabled(): bool
    {
        return isset($_COOKIE['galette_dark_mode']) && $_COOKIE['galette_dark_mode'];
    }

    /**
     * Is user currently up to date?
     * An up-to-date member is active and either due free, or with up-to-date
     * subscription
     *
     * @return bool
     */
    public function isUp2Date(): bool
    {
        return $this->uptodate;
    }

    /**
     * Display logged in member name
     *
     * @param boolean $only_name If we want only the name without any additional text
     *
     * @return string
     */
    public function loggedInAs(bool $only_name = false): string
    {
        $n = $this->name . ' ' . ($this->surname ?? '') . ' (' . $this->login . ')';
        if ($only_name === false) {
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
     * @return mixed
     */
    public function __get(string $name)
    {
        $forbidden = array('logged', 'admin', 'active', 'superadmin', 'staff', 'cron', 'uptodate');
        if (in_array($name, $forbidden)) {
            throw new \RuntimeException('Property ' . $name . ' is forbidden!');
        }

        switch ($name) {
            case 'id':
                if (isset($this->$name)) {
                    return (int)$this->$name;
                }
                return null;
            case 'login':
            case 'lang':
                if (isset($this->$name)) {
                    return $this->$name;
                }
                return null;
            default:
                if (!isset($this->$name)) {
                    throw new \RuntimeException('Property ' . $name . ' is not set!');
                }
                return $this->$name;
        }
    }

    /**
     * Global isset method
     * Required for twig to access properties via __get
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        $forbidden = array('logged', 'admin', 'active', 'superadmin', 'staff', 'cron', 'uptodate');
        if (isset($this->$name) && !in_array($name, $forbidden)) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * get user access level
     *
     * @return integer
     */
    public function getAccessLevel(): int
    {

        if ($this->isSuperAdmin()) {
            return self::ACCESS_SUPERADMIN;
        } elseif ($this->isAdmin()) {
            return self::ACCESS_ADMIN;
        } elseif ($this->isStaff()) {
            return self::ACCESS_STAFF;
        } elseif ($this->isGroupManager()) {
            return self::ACCESS_MANAGER;
        } else {
            return self::ACCESS_USER;
        }
    }
}
