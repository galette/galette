<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Member class for galette
 *
 * PHP version 5
 *
 * Copyright © 2009-2011 The Galette Team
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
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-06-02
 */

/** @ignore */
require_once 'politeness.class.php';
require_once 'status.class.php';
require_once 'fields_config.class.php';
require_once 'fields_categories.class.php';
require_once 'picture.class.php';

/**
 * Member class for galette
 *
 * @category  Classes
 * @name      Adherent
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 02-06-2009
 */
class Adherent
{
    const TABLE = 'adherents';
    const PK = 'id_adh';

    private $_id;
    //Identity
    private $_politeness;
    private $_name;
    private $_surname;
    private $_nickname;
    private $_birthdate;
    private $_job;
    private $_language;
    private $_active;
    private $_status;
    //Contact informations
    private $_adress;
    private $_adress_continuation; /** TODO: remove */
    private $_zipcode;
    private $_town;
    private $_country;
    private $_phone;
    private $_gsm;
    private $_email;
    private $_website;
    private $_msn; /** TODO: remove */
    private $_icq; /** TODO: remove */
    private $_jabber; /** TODO: remove */
    private $_gnupgid; /** TODO: remove */
    private $_fingerprint; /** TODO: remove */
    //Galette relative informations
    private $_appears_in_list;
    private $_admin;
    private $_due_free;
    private $_login;
    private $_password;
    private $_creation_date;
    private $_due_date;
    private $_others_infos;
    private $_others_infos_admin;
    private $_picture;
    private $_oldness;
    private $_days_remaining;
    //
    private $_row_classes;
    //fields list and their translation
    private $_fields;
    private $_requireds = array(
        'titre_adh',
        'nom_adh',
        'login_adh',
        'mdp_adh',
        'adresse_adh',
        'cp_adh',
        'ville_adh'
    );


    /**
    * Default constructor
    *
    * @param null|int|ResultSet $args Either a ResultSet row or its id for to load
    *                                   a specific member, or null to just
    *                                   instanciate object
    */
    public function __construct($args = null)
    {
        /*
        * Fields configuration. Each field is an array and must reflect:
        * array(
        *   (string)label,
        *   (boolean)required,
        *   (boolean)visible,
        *   (int)position,
        *   (int)category
        * )
        *
        * I'd prefer a static private variable for this...
        * But call to the _T function does not seems to be allowed there :/
        */
        $this->_fields = array(
            'id_adh' => array(
                'label'=>_T("Identifiant:"),
                'required'=>true,
                'visible'=>FieldsConfig::HIDDEN,
                'position'=>0,
                'category'=>FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'id_statut' => array(
                'label'=>_T("Status:"),
                'required'=>true,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>1,
                'category'=>FieldsCategories::ADH_CATEGORY_GALETTE
            ),
            'nom_adh' => array(
                'label'=>_T("Name:"),
                'required'=>true ,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>2,
                'category'=>FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'prenom_adh' => array(
                'label'=>_T("First name:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>3,
                'category'=>FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'pseudo_adh' => array(
                'label'=>_T("Nickname:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>4,
                'category'=>FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'titre_adh' => array(
                'label'=>_T("Title:"),
                'required'=>true,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>5,
                'category'=>FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'ddn_adh' => array(
                'label'=>_T("birth date:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>6,
                'category'=>FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'adresse_adh' => array(
                'label'=>_T("Address:"),
                'required'=>true,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>7,
                'category'=>FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            /** TODO remove second adress... */
            'adresse2_adh' => array(
                'label'=>_T("Address (continuation)"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>8,
                'category'=>FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'cp_adh' => array(
                'label'=>_T("Zip Code:"),
                'required'=>true,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>9,
                'category'=>FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'ville_adh' => array(
                'label'=>_T("City:"),
                'required'=>true,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>10,
                'category'=>FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'pays_adh' => array(
                'label'=>_T("Country:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>11,
                'category'=>FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'tel_adh' => array(
                'label'=>_T("Phone:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>12,
                'category'=>FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'gsm_adh' => array(
                'label'=>_T("Mobile phone:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>13,
                'category'=>FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'email_adh' => array(
                'label'=>_T("E-Mail:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>14,
                'category'=>FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'url_adh' => array(
                'label'=>_T("Website:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>15,
                'category'=>FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'icq_adh' => array(
                'label'=>_T("ICQ:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>16,
                'category'=>FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'msn_adh' => array(
                'label'=>_T("MSN:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>17,
                'category'=>FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'jabber_adh' => array(
                'label'=>_T("Jabber:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>18,
                'category'=>FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'info_adh' => array(
                'label'=>_T("Other informations (admin):"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>19,
                'category'=>FieldsCategories::ADH_CATEGORY_GALETTE
            ),
            'info_public_adh' => array(
                'label'=>_T("Other informations:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>20,
                'category'=>FieldsCategories::ADH_CATEGORY_GALETTE
            ),
            'prof_adh' => array(
                'label'=>_T("Profession:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>21,
                'category'=>FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'login_adh' => array(
                'label'=>_T("Username:"),
                'required'=>true,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>22,
                'category'=>FieldsCategories::ADH_CATEGORY_GALETTE
            ),
            'mdp_adh' => array(
                'label'=>_T("Password:"),
                'required'=>true,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>23,
                'category'=>FieldsCategories::ADH_CATEGORY_GALETTE
            ),
            'date_crea_adh' => array(
                'label'=>_T("Creation date:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>24,
                'category'=>FieldsCategories::ADH_CATEGORY_GALETTE
            ),
            'activite_adh' => array(
                'label'=>_T("Account:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>25,
                'category'=>FieldsCategories::ADH_CATEGORY_GALETTE
            ),
            'bool_admin_adh' => array(
                'label'=>_T("Galette Admin:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>26,
                'category'=>FieldsCategories::ADH_CATEGORY_GALETTE
            ),
            'bool_exempt_adh' => array(
                'label'=>_T("Freed of dues:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>27,
                'category'=>FieldsCategories::ADH_CATEGORY_GALETTE
            ),
            'bool_display_info' => array(
                'label'=>_T("Be visible in the<br /> members list:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>28,
                'category'=>FieldsCategories::ADH_CATEGORY_GALETTE
            ),
            'date_echeance' => array(
                'label'=>_T("Due date:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>29,
                'category'=>FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'pref_lang' => array(
                'label'=>_T("Language:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>30,
                'category'=>FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'lieu_naissance' => array(
                'label'=>_T("Birthplace:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>31,
                'category'=>FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'gpgid' => array(
                'label'=>_T("Id GNUpg (GPG):"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>32,
                'category'=>FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'fingerprint' => array(
                'label'=>_T("fingerprint:"),
                'required'=>false,
                'visible'=>FieldsConfig::VISIBLE,
                'position'=>33,
                'category'=>FieldsCategories::ADH_CATEGORY_CONTACT
            )
        );
        if ( $args == null || is_int($args) ) {
            $this->_active = true;
            $this->_language = I18n::DEFAULT_LANG;
            $this->_creation_date = date("Y-m-d");
            $this->_status = Status::DEFAULT_STATUS;
            $this->_politeness = Politeness::MR;
            $this->_password = makeRandomPassword(7); //Usefull ?
            $this->_picture = new Picture();
            if ( is_int($args) && $args > 0 ) {
                $this->load($args);
            }
        } elseif ( is_object($args) ) {
            $this->_loadFromRS($args);
        } elseif (is_string($args) ) {
            $this->loadFromLoginOrMail($args);
        }
    }

    /**
    * Loads a member from its id
    *
    * @param int $id the identifiant for the member to load
    *
    * @return bool true if query succeed, false otherwise
    */
    public function load($id)
    {
        global $mdb, $log;

        $requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE . ' WHERE ' .
            self::PK . '=' . $id;

        $result = $mdb->query($requete);

        if (MDB2::isError($result)) {
            $log->log(
                'Cannot load member form id `' . $id . '` | ' .
                $result->getMessage() . '(' . $result->getDebugInfo() . ')',
                PEAR_LOG_WARNING
            );
            return false;
        }

        $this->_loadFromRS($result->fetchRow());
        $result->free();

        return true;
    }

    /**
    * Loads a member from its login
    *
    * @param string $login login for the member to load
    *
    * @return bool true if query succeed, false otherwise
    */
    public function loadFromLoginOrMail($login)
    {
        global $mdb, $log;

        $requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE . ' WHERE ';

        if ( GaletteMail::isValidEmail($login) ) {
            //we got a valid email adress, use it
            $requete .= 'email_adh=\'' . $login . '\'';
        } else {
            ///we did not get an email adress, consider using login
            $requete .= 'login_adh=\'' . $login . '\'';
        }

        $result = $mdb->query($requete);

        if (MDB2::isError($result)) {
            $log->log(
                'Cannot load member form login `' . $login . '` | ' .
                $result->getMessage() . '(' . $result->getDebugInfo() . ')',
                PEAR_LOG_WARNING
            );
            return false;
        }

        $this->_loadFromRS($result->fetchRow());
        $result->free();

        return true;
    }

    /**
    * Populate object from a resultset row
    *
    * @param ResultSet $r the resultset row
    *
    * @return void
    */
    private function _loadFromRS($r)
    {
        $this->_id = $r->id_adh;
        //Identity
        $this->_politeness = $r->titre_adh;
        $this->_name = $r->nom_adh;
        $this->_surname = $r->prenom_adh;
        $this->_nickname = $r->pseudo_adh;
        $this->_birthdate = $r->ddn_adh;
        $this->_job = $r->prof_adh;
        $this->_language = $r->pref_lang;
        $this->_active = $r->activite_adh;
        $this->_status = $r->id_statut;
        //Contact informations
        $this->_adress = $r->adresse_adh;
        /** TODO: remove and merge with adress */
        $this->_adress_continuation = $r->adresse2_adh;
        $this->_zipcode = $r->cp_adh;
        $this->_town = $r->ville_adh;
        $this->_country = $r->pays_adh;
        $this->_phone = $r->tel_adh;
        $this->_gsm = $r->gsm_adh;
        $this->_email = $r->email_adh;
        $this->_website = $r->url_adh;
        /** TODO: remove */
        $this->_msn = $r->msn_adh;
        /** TODO: remove */
        $this->_icq = $r->icq_adh;
        /** TODO: remove */
        $this->_jabber = $r->jabber_adh;
        /** TODO: remove */
        $this->_gnupgid = $r->gpgid;
        /** TODO: remove */
        $this->_fingerprint = $r->fingerprint;
        //Galette relative informations
        $this->_appears_in_list = $r->bool_display_info;
        $this->_admin = $r->bool_admin_adh;
        $this->_due_free = $r->bool_exempt_adh;
        $this->_login = $r->login_adh;
        $this->_password = $r->mdp_adh;
        $this->_creation_date = $r->date_crea_adh;
        $this->_due_date = $r->date_echeance;
        $this->_others_infos = $r->info_public_adh;
        $this->_others_infos_admin = $r->info_adh;
        $this->_picture = new Picture($this->_id);
        $this->_checkDues();
    }

    /**
    * Check for dues status
    *
    * @return void
    */
    private function _checkDues()
    {
        //how many days since our beloved member has been created
        // PHP >= 5.3
        $date_now = new DateTime();
        $this->_oldness = $date_now->diff(
            new DateTime($this->_creation_date)
        )->days;

        if ( $this->isDueFree() ) {
            //no fee required, we don't care about dates
            $this->_row_classes .= ' cotis-exempt';
        } else {
            //ok, fee is required. Let's check the dates
            if ( $this->_due_date == '' ) {
                $this->_row_classes .= ' cotis-never';
            } else {
                $date_end = new DateTime($this->_due_date);
                $date_diff = $date_now->diff($date_end);
                $this->_days_remaining = ( $date_diff->invert == 1 )
                    ? $date_diff->days * -1
                    : $date_diff->days;

                if ( $this->_days_remaining == 0 ) {
                    $this->_row_classes .= ' cotis-lastday';
                } else if ( $this->_days_remaining < 0 ) {
                    $this->_row_classes .= ' cotis-late';
                } else if ( $this->_days_remaining < 30 ) {
                    $this->_row_classes .= ' cotis-soon';
                } else {
                    $this->_row_classes .= ' cotis-ok';
                }
            }
        }
    }

    /**
    * Is member admin?
    *
    * @return bool
    */
    public function isAdmin()
    {
        return $this->_admin;
    }

    /**
    * Is member freed of dues?
    *
    * @return bool
    */
    public function isDueFree()
    {
        return $this->_due_free;
    }

    /**
    * Can member appears in public members list?
    *
    * @return bool
    */
    public function appearsInMembersList()
    {
        return $this->_appears_in_list;
    }

    /**
    * Is member active?
    *
    * @return bool
    */
    public function isActive()
    {
        return $this->_active;
    }

    /**
    * Does member have uploaded a picture?
    *
    * @return bool
    */
    public function hasPicture()
    {
        return $this->_picture->hasPicture();
    }

    /**
    * Get row class related to current fee status
    *
    * @param boolean $public we want the class for public pages
    *
    * @return string the class to apply
    */
    public function getRowClass($public = false)
    {
        $strclass = ($this->isActive()) ? 'active' : 'inactive';
        if ( $public === false ) {
            $strclass .= $this->_row_classes;
        }
        return $strclass;
    }

    /**
    * Get current member due status
    *
    * @return string i18n string representing state of due
    */
    public function getDues()
    {
        $ret = '';
        if ( $this->isDueFree() ) {
                $ret = _T("Freed of dues");
        } else if ( $this->due_date == '') {
            $patterns = array('/%days/', '/%date/');
            $replace = array($this->oldness, $this->creation_date);
            $ret = preg_replace($patterns, $replace, _T("Never contributed: Registered %days days ago (since %date)"));
        } else if ( $this->days_remaining == 0 ) {
            $ret = _T("Last day!");
        } else if ( $this->days_remaining < 0 ) {
            $patterns = array('/%days/', '/%date/');
            $replace = array($this->days_remaining *-1, $this->due_date);
            $ret = preg_replace($patterns, $replace, _T("Late of %days days (since %date)"));
        } else {
            $patterns = array('/%days/', '/%date/');
            $replace = array($this->days_remaining, $this->due_date);
            $ret = preg_replace($patterns, $replace, _T("%days days remaining (ending on %date)"));
        }
        return $ret;
    }

    /**
    * Retrieve Full name and surname for the specified member id
    *
    * @param int $id member id
    *
    * @return string formatted Name and Surname
    */
    public static function getSName($id)
    {
        global $mdb, $log;
        $query = 'SELECT nom_adh, prenom_adh  FROM ' . PREFIX_DB . self::TABLE .
            ' WHERE ' . self::PK . '=' . $id;

        $result = $mdb->query($query);

        if (MDB2::isError($result)) {
            $log->log(
                'Cannot get formatted name for member form id `' . $id . '` | ' .
                $result->getMessage() . '(' . $result->getDebugInfo() . ')',
                PEAR_LOG_WARNING
            );
            return false;
        }

        $row = $result->fetchRow();
        $result->free();

        return mb_strtoupper($row->nom_adh, 'UTF-8') . ' ' . ucfirst(mb_strtolower($row->prenom_adh, 'UTF-8'));
    }

    /**
     * Change password for a given user
     *
     * @param string $id_adh Member identifier
     * @param string $pass   New password
     *
     * @return boolean
     */
    public static function updatePassword($id_adh, $pass)
    {
        global $log, $mdb;

        $query = 'UPDATE ' . PREFIX_DB . self::TABLE . ' SET mdp_adh=\'' .
        md5($pass) . '\' WHERE ' . self::PK . '=' . $id_adh;

        $result = $mdb->query($query);
        if ( MDB2::isError($result) ) {
            $log->log(
                'An error occured while updating password for `' . $id_adh . '`',
                PEAR_LOG_ERR
            );
            return false;
        } else {
            $log->log(
                'Password for `' . $id_adh . '` has been updated.',
                PEAR_LOG_DEBUG
            );
            return true;
        }
    }

    /**
    * Global getter method
    *
    * @param string $name name of the property we want to retrive
    *
    * @return false|object the called property
    */
    public function __get($name)
    {
        $forbidden = array(
            'admin', 'due_free', 'appears_in_list', 'active',  'row_classes'
        );
        $virtuals = array(
            'sadmin', 'sdue_free', 'sappears_in_list', 'sactive', 'spoliteness',
            'sstatus', 'sfullname', 'sname', 'rowclass'
        );
        $rname = '_' . $name;
        if ( !in_array($name, $forbidden) && isset($this->$rname)) {
            switch($name) {
            case 'birthdate':
            case 'creation_date':
            case 'due_date':
                if ( $this->$rname != '' ) {
                    $d = new DateTime($this->$rname);
                    return $d->format(_T("Y-m-d"));
                }
                break;
            default:
                return $this->$rname;
                break;
            }
        } else if ( !in_array($name, $forbidden) && in_array($name, $virtuals) ) {
            $real = '_' . substr($name, 1);
            switch($name) {
            case 'sadmin':
            case 'sdue_free':
            case 'sappears_in_list':
                return (($this->$real) ? _T("Yes") : _T("No"));
                break;
            case 'sactive':
                return (($this->$real) ? _T("Active") : _T("Inactive"));
                break;
            case 'spoliteness':
                return Politeness::getPoliteness($this->_politeness);
                break;
            case 'sstatus':
                return Status::getLabel($this->_status);
                break;
            case 'sfullname':
                $sfn = mb_strtoupper($this->_name, 'UTF-8') . ' ' .
                       ucwords(mb_strtolower($this->_surname, 'UTF-8'));
                if( $this->_politeness != Politeness::COMPANY ) {
                    $sfn = Politeness::getPoliteness($this->_politeness) . ' ' . $sfn;
                }
                return $sfn;
                break;
            case 'sname':
                return mb_strtoupper($this->_name, 'UTF-8') . ' ' . ucfirst(mb_strtolower($this->_surname, 'UTF-8'));
                break;
            }
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
        $forbidden = array('fields');
        /** TODO: What to do ? :-) */
        switch ( $name ) {
        case 'nom_adh':
            $this->_name = $value;
            break;
        case 'prenom_adh':
            $this->_surname = $value;
            break;
        case 'pseudo_adh':
            $this->_nickname = $value;
            break;
        case 'ddn_adh':
            /** TODO: check date validity */
            $this->_birthdate = $value;
            break;
        case 'prof_adh':
            $this->_job = $value;
            break;
        case 'pref_lang':
            $this->_language = $value;
            break;
        case 'bool_display_info':
            $this->_appears_in_list = $value;
            break;
        case 'activite_adh':
            /** TODO: When member account is activated, we should send a mail to the member (add an entry to deactivate that in the preferences) */
            $this->_active = $value;
            break;
        case 'id_statut':
            $this->_status = $value;
            break;
        case 'bool_admin_adh':
            $this->_admin = $value;
            break;
        case 'bool_exempt_adh':
            $this->_due_free = $value;
            break;
        case 'login_adh':
            $this->_login = $value;
            break;
        case 'mdp_adh':
            $this->_password = md5($value);
            break;
        case 'date_crea_adh':
            $this->_creation_date = $value;
            break;
        case 'info_adh':
            $this->_others_infos_admin = $value;
            break;
        case 'info_public_adh':
            $this->_others_infos = $value;
            break;
        case 'adresse_adh':
            $this->_adress = $value;
            break;
        case 'adresse2_adh':
            $this->_adress_continuation = $value;
            break;
        case 'cp_adh':
            $this->_zipcode = $value;
            break;
        case 'ville_adh':
            $this->_town = $value;
            break;
        case 'pays_adh':
            $this->_country = $value;
            break;
        case 'tel_adh':
            $this->_phone = $value;
            break;
        case 'gsm_adh':
            $this->_gsm = $value;
            break;
        case 'email_adh':
            /** TODO: check if mail  is valid */
            $this->_email = $value;
            break;
        case 'url_adh':
            /** TODO: check URL validity */
            $this->_website = $value;
            break;
        case 'icq_adh':
            $this->_icq = $value;
            break;
        case 'jabber_adh':
            $this->_jabber = $value;
            break;
        case 'msn_adh':
            $this->_msn = $value;
            break;
        case 'gpgid':
            /** TODO: check validity */
            $this->_gnupgid = $value;
            break;
        case 'fingerprint':
            /** Check validity */
            $this->_fingerprint = $value;
            break;
        }
    }
}
?>