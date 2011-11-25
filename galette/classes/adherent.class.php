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
require_once 'contribution.class.php';
require_once 'galette_password.class.php';
require_once 'groups.class.php';

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
    private $_company_name;
    private $_name;
    private $_surname;
    private $_nickname;
    private $_birthdate;
    private $_birth_place;
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
    private $_groups;
    //
    private $_row_classes;
    //fields list and their translation
    private $_fields;
    private $_self_adh = false;

    private $_disabled_fields = array(
        'id_adh' => 'disabled="disabled"',
        'date_crea_adh' => 'disabled="disabled"',
        'id_statut' => 'disabled="disabled"',
        'activite_adh' => 'disabled="disabled"',
        'bool_exempt_adh' => 'disabled="disabled"',
        'bool_admin_adh' => 'disabled="disabled"',
        'date_echeance' => 'disabled="disabled"',
        'info_adh' => 'disabled="disabled"'
    );
    private $_edit_disabled_fields = array(
        'titre_adh' => 'disabled',
        'nom_adh' => 'disabled="disabled"',
        'prenom_adh' => 'disabled="disabled"',
    );
    private $_staff_edit_disabled_fields = array(
        'bool_admin_adh' => 'disabled="disabled"'
    );
    private $_adm_edit_disabled_fields = array(
        'id_adh' => 'disabled="disabled"',
        'date_echeance' => 'disabled="disabled"'
    );


    /**
    * Default constructor
    *
    * @param null|int|ResultSet $args Either a ResultSet row, its id or its 
    *                                 login or its mail for to load
    *                                 a specific member, or null to just
    *                                 instanciate object
    */
    public function __construct($args = null)
    {
        global $i18n;
        /*
        * Fields configuration. Each field is an array and must reflect:
        * array(
        *   (string)label,
        *   (string) propname,
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
                'label'    => _T("Identifiant:"),
                'propname' => 'id',
                'required' => true,
                'visible'  => FieldsConfig::HIDDEN,
                'position' => 0,
                'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'id_statut' => array(
                'label'    => _T("Status:"),
                'propname' => 'status',
                'required' => true,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 1,
                'category' => FieldsCategories::ADH_CATEGORY_GALETTE
            ),
            'nom_adh' => array(
                'label'    => _T("Name:"),
                'propname' => 'name',
                'required' => true ,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 2,
                'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'prenom_adh' => array(
                'label'    => _T("First name:"),
                'propname' => 'surname',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 3,
                'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'societe_adh' => array(
                'label'    => _T("Company name:"),
                'propname' => 'company_name',
                'required' => false ,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 4,
                'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'pseudo_adh' => array(
                'label'    => _T("Nickname:"),
                'propname' => 'nickname',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 5,
                'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'titre_adh' => array(
                'label'    => _T("Title:"),
                'propname' => 'politeness',
                'required' => true,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 6,
                'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'ddn_adh' => array(
                'label'    => _T("birth date:"),
                'propname' => 'birthdate',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 7,
                'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'adresse_adh' => array(
                'label'    => _T("Address:"),
                'propname' => 'adress',
                'required' => true,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 8,
                'category' => FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            /** TODO remove second adress... */
            'adresse2_adh' => array(
                'label'    => _T("Address (continuation)"),
                'propname' => 'adress_continuation',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 9,
                'category' => FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'cp_adh' => array(
                'label'    => _T("Zip Code:"),
                'propname' => 'zipcode',
                'required' => true,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 10,
                'category' => FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'ville_adh' => array(
                'label'    => _T("City:"),
                'propname' => 'town',
                'required' => true,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 11,
                'category' => FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'pays_adh' => array(
                'label'    => _T("Country:"),
                'propname' => 'country',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 12,
                'category' => FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'tel_adh' => array(
                'label'    => _T("Phone:"),
                'propname' => 'phone',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 13,
                'category' => FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'gsm_adh' => array(
                'label'    => _T("Mobile phone:"),
                'propname' => 'gsm',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 14,
                'category' => FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'email_adh' => array(
                'label'    => _T("E-Mail:"),
                'propname' => 'email',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 15,
                'category' => FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'url_adh' => array(
                'label'    => _T("Website:"),
                'propname' => 'website',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 16,
                'category' => FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'icq_adh' => array(
                'label'    => _T("ICQ:"),
                'propname' => 'icq',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 17,
                'category' => FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'msn_adh' => array(
                'label'    => _T("MSN:"),
                'propname' => 'msn',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 18,
                'category' => FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'jabber_adh' => array(
                'label'    => _T("Jabber:"),
                'propname' => 'jabber',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 19,
                'category' => FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'info_adh' => array(
                'label'    => _T("Other informations (admin):"),
                'propname' => 'other_infos_admin',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 20,
                'category' => FieldsCategories::ADH_CATEGORY_GALETTE
            ),
            'info_public_adh' => array(
                'label'    => _T("Other informations:"),
                'propname' => 'others_infos',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 21,
                'category' => FieldsCategories::ADH_CATEGORY_GALETTE
            ),
            'prof_adh' => array(
                'label'    => _T("Profession:"),
                'propname' => 'job',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 22,
                'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'login_adh' => array(
                'label'    => _T("Username:"),
                'propname' => 'login',
                'required' => true,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 23,
                'category' => FieldsCategories::ADH_CATEGORY_GALETTE
            ),
            'mdp_adh' => array(
                'label'    => _T("Password:"),
                'propname' => 'password',
                'required' => true,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 24,
                'category' => FieldsCategories::ADH_CATEGORY_GALETTE
            ),
            'date_crea_adh' => array(
                'label'    => _T("Creation date:"),
                'propname' => 'creation_date',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 25,
                'category' => FieldsCategories::ADH_CATEGORY_GALETTE
            ),
            'activite_adh' => array(
                'label'    => _T("Account:"),
                'propname' => 'active',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 26,
                'category' => FieldsCategories::ADH_CATEGORY_GALETTE
            ),
            'bool_admin_adh' => array(
                'label'    => _T("Galette Admin:"),
                'propname' => 'admin',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 27,
                'category' => FieldsCategories::ADH_CATEGORY_GALETTE
            ),
            'bool_exempt_adh' => array(
                'label'    => _T("Freed of dues:"),
                'propname' => 'due_free',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 28,
                'category' => FieldsCategories::ADH_CATEGORY_GALETTE
            ),
            'bool_display_info' => array(
                'label'    => _T("Be visible in the<br /> members list:"),
                'propname' => 'appears_in_list',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 29,
                'category' => FieldsCategories::ADH_CATEGORY_GALETTE
            ),
            'date_echeance' => array(
                'label'    => _T("Due date:"),
                'propname' => 'due_date',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 30,
                'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'pref_lang' => array(
                'label'    => _T("Language:"),
                'propname' => 'language',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 31,
                'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'lieu_naissance' => array(
                'label'    => _T("Birthplace:"),
                'propname' => 'birth_place',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 32,
                'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
            ),
            'gpgid' => array(
                'label'    => _T("Id GNUpg (GPG):"),
                'propname' => 'gnupgid',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 33,
                'category' => FieldsCategories::ADH_CATEGORY_CONTACT
            ),
            'fingerprint' => array(
                'label'    => _T("fingerprint:"),
                'propname' => 'fingerprint',
                'required' => false,
                'visible'  => FieldsConfig::VISIBLE,
                'position' => 34,
                'category' => FieldsCategories::ADH_CATEGORY_CONTACT
            )
        );
        if ( $args == null || is_int($args) ) {
            $this->_active = true;
            $this->_language = $i18n->getID();
            $this->_creation_date = date("Y-m-d");
            $this->_status = Status::DEFAULT_STATUS;
            $this->_politeness = Politeness::MR;
            $gp = new GalettePassword();
            $this->_password = $gp->makeRandomPassword();
            $this->_picture = new Picture();
            if ( is_int($args) && $args > 0 ) {
                $this->load($args);
            }
            $this->_admin = false;
            $this->_staff = false;
            $this->_due_free = false;
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
        global $zdb, $log;

        try {
            $select = new Zend_Db_Select($zdb->db);

            $select->from(
                array('a' => PREFIX_DB . self::TABLE)
            )->join(
                array('b' => PREFIX_DB . Status::TABLE),
                'a.' . Status::PK . '=b.' . Status::PK,
                array('priorite_statut')
            )->where(self::PK . '=?', $id);

            $result = $select->query()->fetchObject();
            $this->_loadFromRS($result);
            return true;
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                'Cannot load member form id `' . $id . '` | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                PEAR_LOG_ERR
            );
            return false;
        }
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
        global $zdb, $log;

        try {
            $select = new Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . self::TABLE);
            if ( GaletteMail::isValidEmail($login) ) {
                //we got a valid email adress, use it
                $select->where('email_adh = ?', $login);
            } else {
                ///we did not get an email adress, consider using login
                $select->where('login_adh = ?', $login);
            }
            $result = $select->query()->fetchObject();
            $this->_loadFromRS($result);
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                'Cannot load member form login `' . $login . '` | ' .
                $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                PEAR_LOG_ERR
            );
            return false;
        }
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
        $this->_self_adh = false;
        $this->_id = $r->id_adh;
        //Identity
        $this->_politeness = $r->titre_adh;
        $this->_company_name = $r->societe_adh;
        $this->_name = $r->nom_adh;
        $this->_surname = $r->prenom_adh;
        $this->_nickname = $r->pseudo_adh;
        $this->_birthdate = $r->ddn_adh;
        $this->_birth_place = $r->lieu_naissance;
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
        if ( $r->priorite_statut < Members::NON_STAFF_MEMBERS ) {
            $this->_staff = true;
        }
        $this->_due_free = $r->bool_exempt_adh;
        $this->_login = $r->login_adh;
        $this->_password = $r->mdp_adh;
        $this->_creation_date = $r->date_crea_adh;
        $this->_due_date = $r->date_echeance;
        $this->_others_infos = $r->info_public_adh;
        $this->_others_infos_admin = $r->info_adh;
        $this->_picture = new Picture($this->_id);
        $this->_groups = Groups::loadGroups($this->_id);
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
     * Is user member of staff?
     *
     * @return bool
     */
    public function isStaff()
    {
        return $this->_staff;
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
     * Is member in specified group?
     *
     * @param type $group_name Group name
     * @return boolean
     */
    public function isGroupMember($group_name)
    {
        $ak = array_keys($this->_groups);
        $res = in_array($group_name, array_keys($this->_groups));
        return in_array($group_name, array_keys($this->_groups));
    }

    /**
     * Is member manager of specified group?
     *
     * @param type $group_name Group name
     * @return boolean
     */
    public function isGroupManager($group_name)
    {
        if ( $this->isGroupMember($group_name) ) {
            return $this->_groups[$group_name];
        } else {
            return false;
        }
    }

    /**
     * Does current member represents a company?
     *
     * @return boolean
     */
    public function isCompany()
    {
        return trim($this->_company_name != '');
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
        } else if ( $this->_due_date == '') {
            $patterns = array('/%days/', '/%date/');
            $replace = array($this->_oldness, $this->_creation_date);
            $ret = preg_replace(
                $patterns,
                $replace,
                _T("Never contributed: Registered %days days ago (since %date)")
            );
        } else if ( $this->_days_remaining == 0 ) {
            $ret = _T("Last day!");
        } else if ( $this->_days_remaining < 0 ) {
            $patterns = array('/%days/', '/%date/');
            $replace = array($this->_days_remaining *-1, $this->_due_date);
            $ret = preg_replace(
                $patterns,
                $replace,
                _T("Late of %days days (since %date)")
            );
        } else {
            $patterns = array('/%days/', '/%date/');
            $replace = array($this->_days_remaining, $this->_due_date);
            $ret = preg_replace(
                $patterns,
                $replace,
                _T("%days days remaining (ending on %date)")
            );
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
        global $zdb, $log;

        try {
            $select = new Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . self::TABLE)
                ->where(self::PK . ' = ?', $id);

            $row = $select->query()->fetch();
            return mb_strtoupper($row->nom_adh, 'UTF-8') . ' ' .
                ucfirst(mb_strtolower($row->prenom_adh, 'UTF-8'));
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                'Cannot get formatted name for member form id `' . $id . '` | ' .
                $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                PEAR_LOG_ERR
            );
            return false;
        }
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
        global $zdb, $log;

        try {
            $zdb->db->update(
                PREFIX_DB . self::TABLE,
                array('mdp_adh' => md5($pass)),
                $zdb->db->quoteInto(self::PK . ' = ?', $id_adh)
            );
            $log->log(
                'Password for `' . $id_adh . '` has been updated.',
                PEAR_LOG_DEBUG
            );
            return true;
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                'An error occured while updating password for `' . $id_adh .
                '` | ' . $e->getMessage(),
                PEAR_LOG_ERR
            );
            return false;
        }
    }

    /**
     * Get field label
     *
     * @param string $field Field name
     *
     * @return string
     */
    public function getFieldName($field)
    {
        $label = $this->_fields[$field]['label'];
        //remove trailing ':' and then nbsp (for french at least)
        $label = trim(trim($label, ':'), '&nbsp;');
        return $label;
    }

    /**
     * Retrieve fields from database
     *
     * @return array
     */
    public static function getDbFields()
    {
        global $zdb;
        return array_keys($zdb->db->describeTable(PREFIX_DB . self::TABLE));
    }

    /**
     * Mark as self membership
     *
     * @return void
     */
    public function setSelfMembership()
    {
        $this->_self_adh = true;
    }

    /**
     * Check posted values validity
     *
     * @param array $values   All values to check, basically the $_POST array
     *                        after sending the form
     * @param array $required Array of required fields
     * @param array $disabled Array of disabled fields
     *
     * @return true|array
     */
    public function check($values, $required, $disabled)
    {
        global $zdb, $log;
        $errors = array();

        $fields = self::getDbFields();

        //reset company name if needeed
        if ( !isset($values['is_company']) || $values['is_company'] != 1 ) {
            unset($values['is_company']);
            unset($values['societe_adh']);
        }

        foreach ( $fields as $key ) {
            //first of all, let's sanitize values
            $key = strtolower($key);
            $prop = '_' . $this->_fields[$key]['propname'];

            if ( isset($values[$key]) ) {
                $value = trim($values[$key]);
            } else {
                switch ($key) {
                case 'bool_admin_adh':
                case 'bool_exempt_adh':
                case 'bool_display_info':
                    $value = 0;
                    break;
                default:
                    $value = '';
                }
            }

            // if the field is enabled, check it
            if ( !isset($disabled[$key]) ) {
                // fill up the adherent structure
                $this->$prop = stripslashes($value);

                // now, check validity
                if ( $value != '' ) {
                    switch ( $key ) {
                    // dates
                    case 'date_crea_adh':
                    case 'ddn_adh':
                        /** FIXME: only ok dates dd/mm/yyyy */
                        if ( !preg_match(
                            '@^([0-9]{2})/([0-9]{2})/([0-9]{4})$@',
                            $value,
                            $date
                        ) ) {
                            $errors[] = _T("- Wrong date format (dd/mm/yyyy)!");
                        } else {
                            if ( !checkdate($date[2], $date[1], $date[3]) ) {
                                $errors[] = _T("- Non valid date!");
                            } else {
                                $this->$prop = $date[3] . '-' . $date[2] . '-' .
                                    $date[1];
                            }
                        }
                        break;
                    case 'email_adh':
                    case 'msn_adh':
                        if ( !GaletteMail::isValidEmail($value) ) {
                            $errors[] = _T("- Non-valid E-Mail address!") .
                                ' (' . $this->getFieldName($key) . ')';
                        }
                        break;
                    case 'url_adh':
                        if ( $value == 'http://' ) {
                            $this->$prop = '';
                        } elseif ( !is_valid_web_url($value) ) {
                            $errors[] = _T("- Non-valid Website address! Maybe you've skipped the http:// ?");
                        }
                        break;
                    case 'login_adh':
                        if ( strlen($value) < 4 ) {
                            $errors[] = _T("- The username must be composed of at least 4 characters!");
                        } else {
                            //check if login does not contain the @ character
                            if ( strpos($value, '@') != false ) {
                                $errors[] = _T("- The username cannot contain the @ character");
                            } else {
                                //check if login is already taken
                                try {
                                    $select = new Zend_Db_Select($zdb->db);
                                    $select->from(
                                        PREFIX_DB . self::TABLE,
                                        self::PK
                                    )->where('login_adh = ?', $value);
                                    if ( $this->_id != '' && $this->_id != null ) {
                                        $select->where(
                                            self::PK . ' != ?',
                                            $this->_id
                                        );
                                    }
                                    $uniq = $select->query()->fetchAll();
                                    if ( count($uniq) !==  0
                                        || $value == $preferences->pref_admin_login
                                    ) {
                                        $errors[] = _T("- This username is already used by another member !");
                                    }
                                } catch (Exception $e) {
                                    /** FIXME: log sthing */
                                    $errors[] = _T("An error has occured while looking if login already exists.");
                                }
                            }
                        }
                        break;
                    case 'mdp_adh':
                        if ( strlen($value) < 4 ) {
                            $errors[] = _T("- The password must be of at least 4 characters!");
                        } else if ( $this->_self_adh !== true &&
                            (!isset($values['mdp_adh2'])
                            || $values['mdp_adh2'] != $value)
                        ) {
                            $errors[] = _T("- The passwords don't match!");
                        } else if ( $this->_self_adh === true &&
                            !crypt($value,$values['mdp_crypt'])==$values['mdp_crypt']
                        ) {
                            $errors[] = _T("Password misrepeated: ");
                        } else {
                            $this->$prop = md5($value);
                        }
                        break;
                    }
                }
            }
        }

        // missing required fields?
        while ( list($key, $val) = each($required) ) {
            $prop = '_' . $this->_fields[$key]['propname'];
            if ( !isset($disabled[$key])
                && (!isset($this->$prop) || trim($this->$prop) == '')
            ) {
                $errors[] = _T("- Mandatory field empty: ") .
                ' <a href="#' . $key . '">' . $this->getFieldName($key) .'</a>';
            }
        }

        if ( count($errors) > 0 ) {
            $log->log(
                'Some errors has been throwed attempting to edit/store a member' .
                print_r($errors, true),
                PEAR_LOG_DEBUG
            );
            return $errors;
        } else {
            $log->log(
                'Member checked successfully.',
                PEAR_LOG_DEBUG
            );
            return true;
        }
    }

    /**
     * Store the member
     *
     * @return boolean
     */
    public function store()
    {
        global $zdb, $log, $hist;

        try {
            $values = array();
            $fields = self::getDbFields();
            /** FIXME: quote? */
            foreach ( $fields as $field ) {
                $prop = '_' . $this->_fields[$field]['propname'];
                $values[$field] = $this->$prop;
            }

            //an empty value will cause date to be set to 1901-01-01, a null
            //will result in 0000-00-00. We want a database NULL value here.
            if ( !$this->_birthdate ) {
                $values['ddn_adh'] = new Zend_Db_Expr('NULL');
            }

            if ( !isset($this->_id) || $this->_id == '') {
                //we're inserting a new member
                unset($values[self::PK]);
                $add = $zdb->db->insert(PREFIX_DB . self::TABLE, $values);
                if ( $add > 0) {
                    $this->_id = $zdb->db->lastInsertId();
                    $this->_picture = new Picture($this->_id);
                    // logging
                    $hist->add(
                        _T("Member card added"),
                        strtoupper($this->_login)
                    );
                    return true;
                } else {
                    $hist->add('Fail to add new member.');
                    throw new Exception(
                        'An error occured inserting new member!'
                    );
                }
            } else {
                //we're editing an existing member
                if ( !$this->isDueFree() ) {
                    // deadline
                    $due_date = Contribution::getDueDate($this->_id);
                    if ( $due_date ) {
                        $values['date_echeance'] = $due_date;
                    }
                }

                if ( !$this->_password ) {
                    unset($values['mdp_adh']);
                }

                $edit = $zdb->db->update(
                    PREFIX_DB . self::TABLE,
                    $values,
                    self::PK . '=' . $this->_id
                );
                //edit == 0 does not mean there were an error, but that there
                //were nothing to change
                if ( $edit > 0 ) {
                    $hist->add(
                        _T("Member card updated"),
                        strtoupper($this->_login)
                    );
                }
                return true;
            }
            //DEBUG
            return false;
        } catch (Exception $e) {
            /** FIXME */
            $log->log(
                'Something went wrong :\'( | ' . $e->getMessage() . "\n" .
                $e->getTraceAsString(),
                PEAR_LOG_ERR
            );
            return false;
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
        global $log;
        $forbidden = array(
            'admin', 'staff', 'due_free', 'appears_in_list', 'active',
            'row_classes'
        );
        $virtuals = array(
            'sadmin', 'sstaff', 'sdue_free', 'sappears_in_list', 'sactive',
            'spoliteness', 'sstatus', 'sfullname', 'sname', 'rowclass'
        );
        $rname = '_' . $name;
        if ( !in_array($name, $forbidden) && isset($this->$rname)) {
            switch($name) {
            case 'birthdate':
            case 'creation_date':
            case 'due_date':
                if ( $this->$rname != '' ) {
                    try {
                        $d = new DateTime($this->$rname);
                        return $d->format(_T("Y-m-d"));
                    } catch (Exception $e) {
                        //oops, we've got a bad date :/
                        $log->log(
                            'Bad date (' . $this->$rname . ') | ' .
                            $e->getMessage(),
                            PER_LOG_INFO
                        );
                        return $this->$rname;
                    }
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
            case 'sstaff':
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
                    $sfn = Politeness::getPoliteness($this->_politeness) .
                        ' ' . $sfn;
                return $sfn;
                break;
            case 'sname':
                return mb_strtoupper($this->_name, 'UTF-8') .
                    ' ' . ucfirst(mb_strtolower($this->_surname, 'UTF-8'));
                break;
            }
        } else {
            return false;
        }
    }
}
?>