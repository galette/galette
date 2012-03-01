<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Default authentication class for galette
 *
 * PHP version 5
 *
 * Copyright © 2007-2012 The Galette Team
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
 * @copyright 2007-2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-07-06
 */

namespace Galette\Core;

use Galette\Repository\Groups as Groups;
use Galette\Repository\Members as Members;

/**
 * Default authentication class for galette
 *
 * @category  Authentication
 * @name      Login
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-07-06
 */
class Login extends Authentication
{
    const TABLE = \Galette\Entity\Adherent::TABLE;
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
        global $zdb, $log;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(
                array('a' => PREFIX_DB . self::TABLE),
                array(
                    'id_adh',
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
            $select->where(self::PK . ' = ?', $user);
            $select->where('mdp_adh = ?', $passe);
            $log->log(
                'Login query: ' . $select->__toString(),
                PEAR_LOG_DEBUG
            );
            $row = $zdb->db->fetchRow($select);

            if ( $row === false ) {
                $log->log(
                    'No entry found for login `' . $user . '`',
                    PEAR_LOG_WARNING
                );
                return false;
            } else {
                $log->log('User `' . $user . '` logged in.', PEAR_LOG_INFO);
                $this->id = $row->id_adh;
                $this->login = $user;
                $this->passe = $row->mdp_adh;
                $this->admin = $row->bool_admin_adh;
                $this->name = $row->nom_adh;
                $this->surname = $row->prenom_adh;
                $this->lang = $row->pref_lang;
                $this->active = $row->activite_adh;
                $this->logged = true;
                if ( $row->priorite_statut < Members::NON_STAFF_MEMBERS ) {
                    $this->staff = true;
                }
                //check if member is up to date
                if ( $row->bool_exempt_adh == true ) {
                    //member is due free, he's up to date.
                    $this->uptodate = true;
                } else {
                    //let's check from end date, if present
                    if ( $row->date_echeance == null ) {
                        $this->uptodate = false;
                    } else {
                        $ech = new DateTime($row->date_echeance);
                        $now = new DateTime();
                        $now->setTime(0, 0, 0);
                        $this->uptodate = $ech >= $now;
                    }
                }
                //staff members and admins are de facto groups managers. For all
                //others, get managed groups
                if ( !$this->isSuperAdmin()
                    && !$this->isAdmin()
                    && !$this->isStaff()
                ) {
                    $this->managed_groups = Groups::loadManagedGroups(
                        $this->id,
                        false
                    );
                }
                return true;
            }
        } catch (\Zend_Db_Adapter_Exception $e) {
            $log->log(
                'An error occured: ' . $e->getChainedException()->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log($e->getTrace(), PEAR_LOG_ERR);
            return false;
        } catch(Exception $e) {
            $log->log(
                'An error occured: ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log($e->getTrace(), PEAR_LOG_ERR);
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
        global $zdb, $log;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . self::TABLE)
                ->where(self::PK . ' = ?', $user);
            $result = $select->query()->fetchAll();

            if ( count($result) > 0 ) {
                /* We got results, user already exists */
                return true;
            } else {
                /* No results, user does not exists yet :) */
                return false;
            }
        } catch (Exception $e) {
            $log->log(
                'Cannot check if login exists | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                PEAR_LOG_ERR
            );
            /* If an error occurs, we consider that username already exists */
            return true;
        }
    }

}
?>
