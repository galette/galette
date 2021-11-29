<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Default authentication class for galette
 *
 * PHP version 5
 *
 * Copyright © 2007-2020 The Galette Team
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
 * @copyright 2007-2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-07-06
 */

namespace Galette\Core;

use Throwable;
use Galette\Repository\Groups;
use Galette\Repository\Members;
use Galette\Entity\Adherent;
use Galette\Entity\Status;
use Analog\Analog;
use RKA\Session;

/**
 * Default authentication class for galette
 *
 * @category  Authentication
 * @name      Login
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-07-06
 */
class Login extends Authentication
{
    public const TABLE = Adherent::TABLE;
    public const PK = 'login_adh';

    private $zdb;
    private $i18n;
    private $impersonated = false;

    /**
     * Instanciate object
     *
     * @param Db   $zdb  Database instance
     * @param I18n $i18n I18n instance
     */
    public function __construct(Db $zdb, I18n $i18n)
    {
        $this->zdb = $zdb;
        $this->i18n = $i18n;
    }

    /**
     * Login for the superuser
     *
     * @param string      $login       name
     * @param Preferences $preferences Preferences instance
     *
     * @return void
     */
    public function logAdmin($login, Preferences $preferences)
    {
        parent::logAdmin($login, $preferences);
        $this->impersonated = false;
    }

    /**
     * Log out user and unset variables
     *
     * @return void
     */
    public function logOut()
    {
        parent::logOut();
        $this->impersonated = false;
    }

    /**
     * Logs in user.
     *
     * @param string $user  user's login
     * @param string $passe user's password
     *
     * @return boolean
     */
    public function logIn($user, $passe)
    {
        try {
            $select = $this->select();
            $select->where(
                [
                    self::PK => $user,
                    'email_adh' => $user
                ],
                \Laminas\Db\Sql\Predicate\PredicateSet::OP_OR
            );

            $results = $this->zdb->execute($select);
            $log_suffix = '';
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $log_suffix .= ' Client ' . $_SERVER['REMOTE_ADDR'];
            }
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $log_suffix .= ' X-Forwarded-For ' . $_SERVER['HTTP_X_FORWARDED_FOR'];
            }

            if ($results->count() === 0) {
                Analog::log(
                    'No entry found for login `' . $user . '`' . $log_suffix,
                    Analog::WARNING
                );
                return false;
            } else {
                $row = $results->current();

                //check if member is active
                if (!$row->activite_adh == true) {
                    Analog::log(
                        'Member `' . $user . ' is inactive!`' . $log_suffix,
                        Analog::WARNING
                    );
                    return false;
                }

                //check if passwords matches
                $pw_checked = password_verify($passe, $row->mdp_adh);
                if (!$pw_checked) {
                    //if password did not match, we try old md5 method
                    $pw_checked = (md5($passe) === $row->mdp_adh);
                }

                if ($pw_checked === false) {
                    //Passwords mismatch. Log and return.
                    Analog::log(
                        'Passwords mismatch for login `' . $user . '`' . $log_suffix,
                        Analog::WARNING
                    );
                    return false;
                }

                $this->logUser($row);
                return true;
            }
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred: ' . $e->getMessage(),
                Analog::WARNING
            );
            Analog::log($e->getTrace(), Analog::ERROR);
            return false;
        }
    }

    /**
     * Get select query without where clause
     *
     * @return \Laminas\Db\Sql\Select
     */
    private function select()
    {
        $select = $this->zdb->select(self::TABLE, 'a');
        $select->columns(
            array(
                'id_adh',
                'login_adh',
                'bool_admin_adh',
                'nom_adh',
                'prenom_adh',
                'mdp_adh',
                'pref_lang',
                'activite_adh',
                'bool_exempt_adh',
                'date_echeance'
            )
        )->join(
            array('b' => PREFIX_DB . Status::TABLE),
            'a.' . Status::PK . '=b.' . Status::PK,
            array('priorite_statut')
        );
        return $select;
    }

    /**
     * Populate object after successful login
     *
     * @param \ArrayObject $row User information
     *
     * @return void
     */
    private function logUser(\ArrayObject $row)
    {
        Analog::log('User `' . $row->login_adh . '` logged in.', Analog::INFO);
        $this->id = $row->id_adh;
        $this->login = $row->login_adh;
        $this->passe = $row->mdp_adh;
        $this->admin = $row->bool_admin_adh;
        $this->name = $row->nom_adh;
        $this->surname = $row->prenom_adh;
        $this->lang = $row->pref_lang;
        $this->i18n->changeLanguage($this->lang);
        $this->active = $row->activite_adh;
        $this->logged = true;
        if ($row->priorite_statut < Members::NON_STAFF_MEMBERS) {
            $this->staff = true;
        }
        //check if member is up-to-date
        if ($row->bool_exempt_adh == true) {
            //member is due free, he's up-to-date.
            $this->uptodate = true;
        } else {
            //let's check from end date, if present
            if ($row->date_echeance == null) {
                $this->uptodate = false;
            } else {
                $ech = new \DateTime($row->date_echeance);
                $now = new \DateTime();
                $now->setTime(0, 0, 0);
                $this->uptodate = $ech >= $now;
            }
        }
        //staff members and admins are de facto groups managers. For all
        //others, get managed groups
        if (
            !$this->isSuperAdmin()
            && !$this->isAdmin()
            && !$this->isStaff()
        ) {
            $this->managed_groups = Groups::loadManagedGroups(
                $this->id,
                false
            );
        }
    }

    /**
     * Impersonate user
     *
     * @param int $id Member ID
     *
     * @return boolean
     */
    public function impersonate($id)
    {
        if (!$this->isSuperAdmin()) {
            throw new \RuntimeException(
                'Only superadmin can impersonate!'
            );
        }

        Analog::log('Impersonating `' . $id . '`...', Analog::INFO);
        try {
            $select = $this->select();
            $select->where(array(Adherent::PK => $id));

            $results = $this->zdb->execute($select);
            if ($results->count() == 0) {
                Analog::log(
                    'No entry found for id `' . $id . '`',
                    Analog::WARNING
                );
                return false;
            } else {
                $this->impersonated = true;
                $this->superadmin = false;
                $row = $results->current();
                $this->logUser($row);
                return true;
            }
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred: ' . $e->getMessage(),
                Analog::WARNING
            );
            Analog::log($e->getTraceAsString(), Analog::ERROR);
            return false;
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
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->where(array(self::PK => $user));
            $results = $this->zdb->execute($select);

            if ($results->count() > 0) {
                /* We got results, user already exists */
                return true;
            } else {
                /* No results, user does not exists yet :) */
                return false;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Cannot check if login exists | ' . $e->getMessage(),
                Analog::WARNING
            );
            /* If an error occurs, we consider that username already exists */
            return true;
        }
    }

    /**
     * Is impersonated
     *
     * @return boolean
     */
    public function isImpersonated()
    {
        return $this->impersonated;
    }

    /**
     * Set id
     *
     * @param int $id ID to set
     *
     * @return $this
     */
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }
}
