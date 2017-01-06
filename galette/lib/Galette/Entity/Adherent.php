<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Member class for galette
 *
 * PHP version 5
 *
 * Copyright © 2009-2014 The Galette Team
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
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-06-02
 */

namespace Galette\Entity;

use Analog\Analog;
use Zend\Db\Sql\Expression;
use Galette\Core\Db;
use Galette\Core\Picture;
use Galette\Core\GaletteMail;
use Galette\Core\Password;
use Galette\Core\Preferences;
use Galette\Core\History;
use Galette\Repository\Groups;
use Galette\Repository\Members;

/**
 * Member class for galette
 *
 * @category  Entity
 * @name      Adherent
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 02-06-2009
 */
class Adherent
{
    const TABLE = 'adherents';
    const PK = 'id_adh';

    const NC = 0;
    const MAN = 1;
    const WOMAN = 2;

    private $_id;
    //Identity
    private $_title;
    private $_company_name;
    private $_name;
    private $_surname;
    private $_nickname;
    private $_birthdate;
    private $_birth_place;
    private $_gender;
    private $_job;
    private $_language;
    private $_active;
    private $_status;
    //Contact informations
    private $_address;
    private $_address_continuation; /** TODO: remove */
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
    private $_modification_date;
    private $_due_date;
    private $_others_infos;
    private $_others_infos_admin;
    private $_picture;
    private $_oldness;
    private $_days_remaining;
    private $_groups;
    private $_managed_groups;
    private $_parent;
    private $_children;
    //
    private $_row_classes;
    //fields list and their translation
    private $_self_adh = false;
    private $_deps = array(
        'picture'   => true,
        'groups'    => true,
        'dues'      => true,
        'parent'    => false,
        'children'  => false
    );

    private $_disabled_fields = array(
        'id_adh' => 'readonly="readonly"',
        'date_crea_adh' => 'disabled="disabled"',
        'id_statut' => 'disabled="disabled"',
        'activite_adh' => 'disabled="disabled"',
        'bool_exempt_adh' => 'disabled="disabled"',
        'bool_admin_adh' => 'disabled="disabled"',
        'date_echeance' => 'disabled="disabled"',
        'info_adh' => 'disabled="disabled"'
    );
    private $_edit_disabled_fields = [];
    private $_staff_edit_disabled_fields = array(
        'bool_admin_adh' => 'disabled="disabled"'
    );
    private $_adm_edit_disabled_fields = array(
        'id_adh' => 'readonly="readonly"',
        'date_echeance' => 'disabled="disabled"'
    );

    private $zdb;
    private $preferences;
    private $fields;
    private $history;

    /**
     * Default constructor
     *
     * @param Db      $zdb  Database instance
     * @param mixed   $args Either a ResultSet row, its id or its
     *                      login or its mail for to load s specific
     *                      member, or null to just instanciate object
     * @param boolean $deps Dependencies configuration, see Adherent::$_deps
     */
    public function __construct(Db $zdb, $args = null, $deps = null)
    {
        global $i18n;

        $this->zdb = $zdb;

        if ($deps !== null && is_array($deps)) {
            $this->_deps = array_merge(
                $this->_deps,
                $deps
            );
        } elseif ($deps !== null) {
            Analog::log(
                '$deps shoud be an array, ' . gettype($deps) . ' given!',
                Analog::WARNING
            );
        }

        //disabled fields override
        $locfile = GALETTE_CONFIG_PATH . 'disabled_fields.php';
        if (file_exists($locfile)) {
            include $locfile;
            if (isset($loc_disabled_fields)
                && is_array($loc_disabled_fields)
            ) {
                $this->_disabled_fields = $loc_disabled_fields;
            }
            if (isset($loc_edit_disabled_fields)
                && is_array($loc_edit_disabled_fields)
            ) {
                $this->_edit_disabled_fields = $loc_edit_disabled_fields;
            }
            if (isset($loc_adm_edit_disabled_fields)
                && is_array($loc_adm_edit_disabled_fields)
            ) {
                $this->_adm_edit_disabled_fields = $loc_adm_edit_disabled_fields;
            }
        }

        if ($args == null || is_int($args)) {
            if (is_int($args) && $args > 0) {
                $this->load($args);
            } else {
                $this->_active = true;
                $this->_language = $i18n->getID();
                $this->_creation_date = date("Y-m-d");
                $this->_status = Status::DEFAULT_STATUS;
                $this->_title = null;
                $this->_gender = self::NC;
                $gp = new Password($this->zdb);
                $this->_password = $gp->makeRandomPassword();
                $this->_picture = new Picture();
                $this->_admin = false;
                $this->_staff = false;
                $this->_due_free = false;
                $this->_parent = null;
            }
        } elseif (is_object($args)) {
            $this->loadFromRS($args);
        } elseif (is_string($args)) {
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
        try {
            $select = $this->zdb->select(self::TABLE, 'a');

            $select->join(
                array('b' => PREFIX_DB . Status::TABLE),
                'a.' . Status::PK . '=b.' . Status::PK,
                array('priorite_statut')
            )->where(array(self::PK => $id));

            $results = $this->zdb->execute($select);

            if ($results->count() === 0) {
                return false;
            }

            $this->loadFromRS($results->current());
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot load member form id `' . $id . '` | ' . $e->getMessage(),
                Analog::WARNING
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
        try {
            $select = $this->zdb->select(self::TABLE);
            if (GaletteMail::isValidEmail($login)) {
                //we got a valid email address, use it
                $select->where(array('email_adh' => $login));
            } else {
                ///we did not get an email address, consider using login
                $select->where(array('login_adh' => $login));
            }

            $results = $this->zdb->execute($select);
            $result = $results->current();
            if ($result) {
                $this->loadFromRS($result);
            }
        } catch (\Exception $e) {
            Analog::log(
                'Cannot load member form login `' . $login . '` | ' .
                $e->getMessage(),
                Analog::WARNING
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
    private function loadFromRS($r)
    {
        $this->_self_adh = false;
        $this->_id = $r->id_adh;
        //Identity
        if ($r->titre_adh !== null) {
            $this->_title = new Title((int)$r->titre_adh);
        }
        $this->_company_name = $r->societe_adh;
        $this->_name = $r->nom_adh;
        $this->_surname = $r->prenom_adh;
        $this->_nickname = $r->pseudo_adh;
        if ($r->ddn_adh != '1901-01-01') {
            $this->_birthdate = $r->ddn_adh;
        }
        $this->_birth_place = $r->lieu_naissance;
        $this->_gender = (int)$r->sexe_adh;
        $this->_job = $r->prof_adh;
        $this->_language = $r->pref_lang;
        $this->_active = $r->activite_adh;
        $this->_status = $r->id_statut;
        //Contact informations
        $this->_address = $r->adresse_adh;
        /** TODO: remove and merge with address */
        $this->_address_continuation = $r->adresse2_adh;
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
        $this->_appears_in_list = ($r->bool_display_info == 1) ? true : false;
        $this->_admin = ($r->bool_admin_adh == 1) ? true : false;
        if (isset($r->priorite_statut)
            && $r->priorite_statut < Members::NON_STAFF_MEMBERS
        ) {
            $this->_staff = true;
        }
        $this->_due_free = ($r->bool_exempt_adh == 1) ? true : false;
        $this->_login = $r->login_adh;
        $this->_password = $r->mdp_adh;
        $this->_creation_date = $r->date_crea_adh;
        if ($r->date_modif_adh != '1901-01-01') {
            $this->_modification_date = $r->date_modif_adh;
        } else {
            $this->_modification_date = $this->_creation_date;
        }
        $this->_due_date = $r->date_echeance;
        $this->_others_infos = $r->info_public_adh;
        $this->_others_infos_admin = $r->info_adh;

        if ($r->parent_id !== null) {
            $this->_parent = $r->parent_id;
            if ($this->_deps['parent'] === true) {
                $this->loadParent($r->parent_id);
            }
        }

        if ($this->_deps['children'] === true) {
            $this->loadChildren();
        }

        if ($this->_deps['picture'] === true) {
            $this->_picture = new Picture($this->_id);
        }

        if ($this->_deps['groups'] === true) {
            $this->loadGroups();
        }

        if ($this->_deps['dues'] === true) {
            $this->checkDues();
        }
    }

    /**
     * Load member parent
     *
     * @return void
     */
    private function loadParent()
    {
        if (!$this->_parent instanceof Adherent) {
            $deps = array_fill_keys(array_keys($this->_deps), false);
            $this->_parent = new Adherent($this->zdb, (int)$this->_parent, $deps);
        }
    }

    /**
     * Load member children
     *
     * @return void
     */
    private function loadChildren()
    {
        $this->_children = array();
        try {
            $id = self::PK;
            $select = $this->zdb->select(self::TABLE);
            $select->columns(
                array($id)
            )->where(
                'parent_id = ' . $this->_id
            );

            $results = $this->zdb->execute($select);

            if ($results->count() >  0) {
                foreach ($results as $row) {
                    $deps = $this->_deps;
                    $deps['children'] = false;
                    $deps['parent'] = false;
                    $this->_children[] = new Adherent($this->zdb, (int)$row->$id, $this->_deps);
                }
            }
        } catch (\Exception $e) {
            Analog::log(
                'Cannot load children for member #' . $this->_id . ' | ' .
                $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Load member groups
     *
     * @return void
     */
    public function loadGroups()
    {
        $this->_groups = Groups::loadGroups($this->_id);
        $this->_managed_groups = Groups::loadManagedGroups($this->_id);
    }

    /**
     * Check for dues status
     *
     * @return void
     */
    private function checkDues()
    {
        //how many days since our beloved member has been created
        $date_now = new \DateTime();
        $this->_oldness = $date_now->diff(
            new \DateTime($this->_creation_date)
        )->days;

        if ($this->isDueFree()) {
            //no fee required, we don't care about dates
            $this->_row_classes .= ' cotis-exempt';
        } else {
            //ok, fee is required. Let's check the dates
            if ($this->_due_date == '') {
                $this->_row_classes .= ' cotis-never';
            } else {
                $date_end = new \DateTime($this->_due_date);
                $date_diff = $date_now->diff($date_end);
                $this->_days_remaining = ( $date_diff->invert == 1 )
                    ? $date_diff->days * -1
                    : $date_diff->days;

                if ($this->_days_remaining == 0) {
                    $this->_row_classes .= ' cotis-lastday';
                } elseif ($this->_days_remaining < 0) {
                    $this->_row_classes .= ' cotis-late';
                } elseif ($this->_days_remaining < 30) {
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
     * @param string $group_name Group name
     *
     * @return boolean
     */
    public function isGroupMember($group_name)
    {
        if (is_array($this->_groups)) {
            foreach ($this->_groups as $g) {
                if ($g->getName() == $group_name) {
                    return true;
                    break;
                }
            }
        } else {
            return false;
        }
    }

    /**
     * Is member manager of specified group?
     *
     * @param string $group_name Group name
     *
     * @return boolean
     */
    public function isGroupManager($group_name)
    {
        if (is_array($this->_managed_groups)) {
            foreach ($this->_managed_groups as $mg) {
                if ($mg->getName() == $group_name) {
                    return true;
                    break;
                }
            }
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
     * Is current member a man?
     *
     * @return boolean
     */
    public function isMan()
    {
        return (int)$this->_gender === self::MAN;
    }

    /**
     * Is current member a woman?
     *
     * @return boolean
     */
    public function isWoman()
    {
        return (int)$this->_gender === self::WOMAN;
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
     * Does member have a parent?
     *
     * @return bool
     */
    public function hasParent()
    {
        return $this->_parent !== null;
    }

    /**
     * Does member have children?
     *
     * @return bool
     */
    public function hasChildren()
    {
        if ($this->_children === null) {
            Analog::log(
                'Children has not been loaded!',
                Analog::WARNING
            );
            return false;
        } else {
            return count($this->_children) > 0;
        }
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
        if ($public === false) {
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
        if ($this->isDueFree()) {
            $ret = _T("Freed of dues");
        } elseif ($this->_due_date == '') {
            $patterns = array('/%days/', '/%date/');
            $cdate = new \DateTime($this->_creation_date);
            $replace = array(
                $this->_oldness,
                $cdate->format(__("Y-m-d"))
            );
            if ($this->_active) {
                $ret = preg_replace(
                    $patterns,
                    $replace,
                    _T("Never contributed: Registered %days days ago (since %date)")
                );
            } else {
                $ret = _T("Never contributed");
            }
        } elseif ($this->_days_remaining == 0) {
            $ret = _T("Last day!");
        } elseif ($this->_days_remaining < 0) {
            $patterns = array('/%days/', '/%date/');
            $ddate = new \DateTime($this->_due_date);
            $replace = array(
                $this->_days_remaining *-1,
                $ddate->format(__("Y-m-d"))
            );
            if ($this->_active) {
                $ret = preg_replace(
                    $patterns,
                    $replace,
                    _T("Late of %days days (since %date)")
                );
            } else {
                $ret = _T("Late");
            }
        } else {
            $patterns = array('/%days/', '/%date/');
            $ddate = new \DateTime($this->_due_date);
            $replace = array(
                $this->_days_remaining,
                $ddate->format(__("Y-m-d"))
            );
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
     * @param Db  $zdb Database instance
     * @param int $id  member id
     *
     * @return string formatted Name and Surname
     */
    public static function getSName($zdb, $id)
    {
        try {
            $select = $zdb->select(self::TABLE);
            $select->where(self::PK . ' = ' . $id);

            $results = $zdb->execute($select);
            $row = $results->current();
            return mb_strtoupper($row->nom_adh, 'UTF-8') . ' ' .
                ucfirst(mb_strtolower($row->prenom_adh, 'UTF-8'));
        } catch (\Exception $e) {
            Analog::log(
                'Cannot get formatted name for member form id `' . $id . '` | ' .
                $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Change password for a given user
     *
     * @param Db     $zdb    Database instance
     * @param string $id_adh Member identifier
     * @param string $pass   New password
     *
     * @return boolean
     */
    public static function updatePassword(Db $zdb, $id_adh, $pass)
    {
        try {
            $cpass = password_hash($pass, PASSWORD_BCRYPT);

            $update = $zdb->update(self::TABLE);
            $update->set(
                array('mdp_adh' => $cpass)
            )->where(self::PK . ' = ' . $id_adh);
            $zdb->execute($update);
            Analog::log(
                'Password for `' . $id_adh . '` has been updated.',
                Analog::DEBUG
            );
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'An error occured while updating password for `' . $id_adh .
                '` | ' . $e->getMessage(),
                Analog::ERROR
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
    private function getFieldName($field)
    {
        $label = $this->fields[$field]['label'];
        //remove trailing ':' and then nbsp (for french at least)
        $label = trim(trim($label, ':'), '&nbsp;');
        return $label;
    }

    /**
     * Retrieve fields from database
     *
     * @param Db $zdb Database instance
     *
     * @return array
     */
    public static function getDbFields(Db $zdb)
    {
        $columns = $zdb->getColumns(self::TABLE);
        $fields = array();
        foreach ($columns as $col) {
            $fields[] = $col->getName();
        }
        return $fields;
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
     * Is member up to date?
     *
     * @return boolean
     */
    public function isUp2Date()
    {
        if ($this->_deps['dues']) {
            if ($this->isDueFree()) {
                //member is due free, he's up to date.
                return true;
            } else {
                //let's check from end date, if present
                if ($this->_due_date == null) {
                    return false;
                } else {
                    $ech = new \DateTime($this->_due_date);
                    $now = new \DateTime();
                    $now->setTime(0, 0, 0);
                    return $ech >= $now;
                }
            }
        } else {
            throw new \RuntimeException(
                'Cannot check if member is up to date, dues deps is disabled!'
            );
        }
    }

    /**
     * Set dependencies
     *
     * @param Preferences $preferences Preferences instance
     * @param array       $fields      Members fields configuration
     * @param History     $history     History instance
     *
     * @return void
     */
    public function setDependencies(
        Preferences $preferences,
        array $fields,
        History $history
    ) {
        $this->preferences = $preferences;
        $this->fields = $fields;
        $this->history = $history;
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
        global $preferences;
        $errors = array();

        $fields = self::getDbFields($this->zdb);

        //reset company name if needeed
        if (!isset($values['is_company']) || $values['is_company'] != 1) {
            unset($values['is_company']);
            unset($values['societe_adh']);
        }

        foreach ($fields as $key) {
            //first of all, let's sanitize values
            $key = strtolower($key);
            $prop = '_' . $this->fields[$key]['propname'];

            if (isset($values[$key])) {
                $value = trim($values[$key]);
            } else {
                switch ($key) {
                    case 'bool_admin_adh':
                    case 'bool_exempt_adh':
                    case 'bool_display_info':
                        $value = 0;
                        break;
                    case 'activite_adh':
                        //values that are setted at object instanciation
                        $value = true;
                        break;
                    case 'date_crea_adh':
                    case 'sexe_adh':
                    case 'titre_adh':
                    case 'id_statut':
                    case 'pref_lang':
                    case 'parent_id':
                        //values that are setted at object instanciation
                        $value = $this->$prop;
                        break;
                    default:
                        $value = '';
                }
            }

            // if the field is enabled, check it
            if (!isset($disabled[$key])) {
                // fill up the adherent structure
                if ($value !== null) {
                    $this->$prop = stripslashes($value);
                }

                // now, check validity
                if ($value !== null && $value != '') {
                    switch ($key) {
                        // dates
                        case 'date_crea_adh':
                        case 'ddn_adh':
                            try {
                                $d = \DateTime::createFromFormat(__("Y-m-d"), $value);
                                if ($d === false) {
                                    //try with non localized date
                                    $d = \DateTime::createFromFormat("Y-m-d", $value);
                                    if ($d === false) {
                                        throw new \Exception('Incorrect format');
                                    }
                                }
                                $this->$prop = $d->format('Y-m-d');
                            } catch (\Exception $e) {
                                Analog::log(
                                    'Wrong date format. field: ' . $key .
                                    ', value: ' . $value . ', expected fmt: ' .
                                    __("Y-m-d") . ' | ' . $e->getMessage(),
                                    Analog::INFO
                                );
                                $errors[] = str_replace(
                                    array(
                                        '%date_format',
                                        '%field'
                                    ),
                                    array(
                                        __("Y-m-d"),
                                        $this->fields[$key]['label']
                                    ),
                                    _T("- Wrong date format (%date_format) for %field!")
                                );
                            }
                            break;
                        case 'titre_adh':
                            if ($value !== null && $value !== '') {
                                if ($value == '-1') {
                                    $this->$prop = null;
                                } else {
                                    $this->$prop = new Title((int)$value);
                                }
                            } else {
                                $this->$prop = null;
                            }
                            break;
                        case 'email_adh':
                        case 'msn_adh':
                            if (!GaletteMail::isValidEmail($value)) {
                                $errors[] = _T("- Non-valid E-Mail address!") .
                                    ' (' . $this->getFieldName($key) . ')';
                            }
                            if ($key == 'email_adh') {
                                try {
                                    $select = $this->zdb->select(self::TABLE);
                                    $select->columns(
                                        array(self::PK)
                                    )->where(array('email_adh' => $value));
                                    if ($this->_id != '' && $this->_id != null) {
                                        $select->where(
                                            self::PK . ' != ' . $this->_id
                                        );
                                    }

                                    $results = $this->zdb->execute($select);
                                    if ($results->count() !==  0) {
                                        $errors[] = _T("- This E-Mail address is already used by another member!");
                                    }
                                } catch (\Exception $e) {
                                    Analog::log(
                                        'An error occured checking member email unicity.',
                                        Analog::ERROR
                                    );
                                    $errors[] = _T("An error has occured while looking if login already exists.");
                                }
                            }
                            break;
                        case 'url_adh':
                            if ($value == 'http://') {
                                $this->$prop = '';
                            } elseif (!isValidWebUrl($value)) {
                                $errors[] = _T("- Non-valid Website address! Maybe you've skipped the http:// ?");
                            }
                            break;
                        case 'login_adh':
                            /** FIXME: add a preference for login lenght */
                            if (strlen($value) < 2) {
                                $errors[] = str_replace(
                                    '%i',
                                    2,
                                    _T("- The username must be composed of at least %i characters!")
                                );
                            } else {
                                //check if login does not contain the @ character
                                if (strpos($value, '@') != false) {
                                    $errors[] = _T("- The username cannot contain the @ character");
                                } else {
                                    //check if login is already taken
                                    try {
                                        $select = $this->zdb->select(self::TABLE);
                                        $select->columns(
                                            array(self::PK)
                                        )->where(array('login_adh' => $value));
                                        if ($this->_id != '' && $this->_id != null) {
                                            $select->where(
                                                self::PK . ' != ' . $this->_id
                                            );
                                        }

                                        $results = $this->zdb->execute($select);
                                        if ($results->count() !==  0
                                            || $value == $preferences->pref_admin_login
                                        ) {
                                            $errors[] = _T("- This username is already in use, please choose another one!");
                                        }
                                    } catch (\Exception $e) {
                                        Analog::log(
                                            'An error occured checking member login unicity.',
                                            Analog::ERROR
                                        );
                                        $errors[] = _T("An error has occured while looking if login already exists.");
                                    }
                                }
                            }
                            break;
                        case 'mdp_adh':
                            /** TODO: check password complexity, set by a preference */
                            /** TODO: add a preference for password lenght */
                            if (strlen($value) < 6) {
                                $errors[] = str_replace(
                                    '%i',
                                    6,
                                    _T("- The password must be of at least %i characters!")
                                );
                            } elseif ($this->_self_adh !== true
                                && (!isset($values['mdp_adh2'])
                                || $values['mdp_adh2'] != $value)
                            ) {
                                $errors[] = _T("- The passwords don't match!");
                            } elseif ($this->_self_adh === true
                                && !crypt($value, $values['mdp_crypt'])==$values['mdp_crypt']
                            ) {
                                $errors[] = _T("Password misrepeated: ");
                            } else {
                                $this->$prop = password_hash(
                                    $value,
                                    PASSWORD_BCRYPT
                                );
                            }
                            break;
                        case 'id_statut':
                            try {
                                //check if status exists
                                $select = $this->zdb->select(Status::TABLE);
                                $select->where(Status::PK . '= ' . $value);

                                $results = $this->zdb->execute($select);
                                $result = $results->current();
                                if ($result === false) {
                                    $errors[] = str_replace(
                                        '%id',
                                        $value,
                                        _T("Status #%id does not exists in database.")
                                    );
                                    break;
                                }

                                //check for status unicity
                                $select = $this->zdb->select(self::TABLE, 'a');
                                $select->limit(1)->join(
                                    array('b' => PREFIX_DB . Status::TABLE),
                                    'a.' . Status::PK . '=b.' . Status::PK,
                                    array('libelle_statut')
                                )->where('b.' . Status::PK . '=' . $value);
                                $select->where->lessThan(
                                    'b.priorite_statut',
                                    Members::NON_STAFF_MEMBERS
                                );

                                if ($this->_id != '' && $this->_id != null) {
                                    $select->where(
                                        'a.' . self::PK . ' != ' . $this->_id
                                    );
                                }

                                $results = $this->zdb->execute($select);
                                if ($results->count() > 0) {
                                    $result = $results->current();
                                    $errors[] = str_replace(
                                        array(
                                            '%s',
                                            '%i',
                                            '%n',
                                            '%m'
                                        ),
                                        array(
                                            $result->libelle_statut,
                                            $result->id_adh,
                                            $result->nom_adh,
                                            $result->prenom_adh
                                        ),
                                        _T("Selected status (%s) is already in use in <a href='voir_adherent.php?id_adh=%i'>%n %m's profile</a>.")
                                    );
                                }
                            } catch (\Exception $e) {
                                Analog::log(
                                    'An error occured checking status unicity: ' . $e->getMessage(),
                                    Analog::ERROR
                                );
                                $errors[] = _T("An error has occured while looking if status is already in use.");
                            }
                            break;
                    }
                } elseif (($key == 'login_adh' && !isset($required['login_adh']))
                    || ($key == 'mdp_adh' && !isset($required['mdp_adh']))
                    && !isset($this->_id)
                ) {
                    $p = new Password($this->zdb);
                    $this->$prop = $p->makeRandomPassword(15);
                }
            }
        }

        // missing required fields?
        while (list($key, $val) = each($required)) {
            $prop = '_' . $this->fields[$key]['propname'];

            if (!isset($disabled[$key])) {
                $mandatory_missing = false;
                if (!isset($this->$prop)) {
                    $mandatory_missing = true;
                } elseif ($key === 'titre_adh' && $this->$prop == '-1') {
                    $mandatory_missing = true;
                }

                if ($mandatory_missing === true) {
                    $errors[] = _T("- Mandatory field empty: ") .
                    ' <a href="#' . $key . '">' . $this->getFieldName($key) .'</a>';
                }
            }
        }

        //attach to/detach from parent
        if (isset($values['detach_parent'])) {
            $this->_parent = null;
        }

        if (count($errors) > 0) {
            Analog::log(
                'Some errors has been throwed attempting to edit/store a member' .
                print_r($errors, true),
                Analog::DEBUG
            );
            return $errors;
        } else {
            Analog::log(
                'Member checked successfully.',
                Analog::DEBUG
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
        global $hist;

        try {
            $values = array();
            $fields = self::getDbFields($this->zdb);
            /** FIXME: quote? */
            foreach ($fields as $field) {
                if ($field !== 'date_modif_adh'
                    || !isset($this->_id)
                    || $this->_id == ''
                ) {
                    $prop = '_' . $this->fields[$field]['propname'];
                    if (($field === 'bool_admin_adh'
                        || $field === 'bool_exempt_adh'
                        || $field === 'bool_display_info')
                        && $this->$prop === false
                    ) {
                        //Handle booleans for postgres ; bugs #18899 and #19354
                        $values[$field] = $this->zdb->isPostgres() ? 'false' : 0;
                    } elseif ($field === 'parent_id') {
                        //handle parents
                        if ($this->_parent === null) {
                            $values['parent_id'] = new Expression('NULL');
                        } elseif ($this->parent instanceof Adherent) {
                            $values['parent_id'] = $this->_parent->id;
                        } else {
                            $values['parent_id'] = $this->_parent;
                        }
                    } else {
                        $values[$field] = $this->$prop;
                    }
                }
            }

            //an empty value will cause date to be set to 1901-01-01, a null
            //will result in 0000-00-00. We want a database NULL value here.
            if (!$this->_birthdate) {
                $values['ddn_adh'] = new Expression('NULL');
            }
            if (!$this->_due_date) {
                $values['date_echeance'] = new Expression('NULL');
            }

            if ($this->_title instanceof Title) {
                $values['titre_adh'] = $this->_title->id;
            } else {
                $values['titre_adh'] = new Expression('NULL');
            }

            if (!$this->_parent) {
                $values['parent_id'] = new Expression('NULL');
            }

            if (!isset($this->_id) || $this->_id == '') {
                //we're inserting a new member
                unset($values[self::PK]);
                //set modification date
                $this->_modification_date = date('Y-m-d');
                $values['date_modif_adh'] = $this->_modification_date;

                $insert = $this->zdb->insert(self::TABLE);
                $insert->values($values);
                $add = $this->zdb->execute($insert);
                if ($add->count() > 0) {
                    if ($this->zdb->isPostgres()) {
                        $this->_id = $this->zdb->driver->getLastGeneratedValue(
                            PREFIX_DB . 'adherents_id_seq'
                        );
                    } else {
                        $this->_id = $this->zdb->driver->getLastGeneratedValue();
                    }
                    $this->_picture = new Picture($this->_id);
                    // logging
                    $hist->add(
                        _T("Member card added"),
                        strtoupper($this->_login)
                    );
                    return true;
                } else {
                    $hist->add(_T("Fail to add new member."));
                    throw new \Exception(
                        'An error occured inserting new member!'
                    );
                }
            } else {
                //we're editing an existing member
                if (!$this->isDueFree()) {
                    // deadline
                    $due_date = Contribution::getDueDate($this->zdb, $this->_id);
                    if ($due_date) {
                        $values['date_echeance'] = $due_date;
                    }
                }

                if (!$this->_password) {
                    unset($values['mdp_adh']);
                }

                $update = $this->zdb->update(self::TABLE);
                $update->set($values);
                $update->where(
                    self::PK . '=' . $this->_id
                );

                $edit = $this->zdb->execute($update);

                //edit == 0 does not mean there were an error, but that there
                //were nothing to change
                if ($edit->count() > 0) {
                    $this->updateModificationDate();
                    $hist->add(
                        _T("Member card updated"),
                        strtoupper($this->_login)
                    );
                }
                return true;
            }
            //DEBUG
            return false;
        } catch (\Exception $e) {
            Analog::log(
                'Something went wrong :\'( | ' . $e->getMessage() . "\n" .
                $e->getTraceAsString(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Update member modification date
     *
     * @return void
     */
    private function updateModificationDate()
    {
        try {
            $modif_date = date('Y-m-d');
            $update = $this->zdb->update(self::TABLE);
            $update->set(
                array('date_modif_adh' => $modif_date)
            )->where(self::PK . '=' . $this->_id);

            $edit = $this->zdb->execute($update);
            $this->_modification_date = $modif_date;
        } catch (\Exception $e) {
            Analog::log(
                'Something went wrong updating modif date :\'( | ' .
                $e->getMessage() . "\n" . $e->getTraceAsString(),
                Analog::ERROR
            );
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
        global $log, $login;

        $forbidden = array(
            'admin', 'staff', 'due_free', 'appears_in_list', 'active',
            'row_classes'
        );
        $virtuals = array(
            'sadmin', 'sstaff', 'sdue_free', 'sappears_in_list', 'sactive',
            'stitle', 'sstatus', 'sfullname', 'sname', 'rowclass', 'saddress'
        );
        $rname = '_' . $name;
        if (!in_array($name, $forbidden) && isset($this->$rname)) {
            switch ($name) {
                case 'birthdate':
                case 'creation_date':
                case 'modification_date':
                case 'due_date':
                    if ($this->$rname != '') {
                        try {
                            $d = new \DateTime($this->$rname);
                            return $d->format(__("Y-m-d"));
                        } catch (\Exception $e) {
                            //oops, we've got a bad date :/
                            Analog::log(
                                'Bad date (' . $this->$rname . ') | ' .
                                $e->getMessage(),
                                Analog::INFO
                            );
                            return $this->$rname;
                        }
                    }
                    break;
                default:
                    return $this->$rname;
                    break;
            }
        } elseif (!in_array($name, $forbidden) && in_array($name, $virtuals)) {
            $real = '_' . substr($name, 1);
            switch ($name) {
                case 'sadmin':
                case 'sdue_free':
                case 'sappears_in_list':
                case 'sstaff':
                    return (($this->$real) ? _T("Yes") : _T("No"));
                    break;
                case 'sactive':
                    return (($this->$real) ? _T("Active") : _T("Inactive"));
                    break;
                case 'stitle':
                    if (isset($this->_title)) {
                        return $this->_title->tshort;
                    } else {
                        return null;
                    }
                    break;
                case 'sstatus':
                    $status = new Status($this->zdb);
                    return $status->getLabel($this->_status);
                    break;
                case 'sfullname':
                    $sfn = mb_strtoupper($this->_name, 'UTF-8') . ' ' .
                        ucwords(mb_strtolower($this->_surname, 'UTF-8'));
                    if (isset($this->_title)) {
                        $sfn = $this->_title->tshort . ' ' . $sfn;
                    }
                    return $sfn;
                    break;
                case 'saddress':
                    $address = $this->_address;
                    if ($this->_address_continuation !== '') {
                        $address .= "\n" . $this->_address_continuation;
                    }
                    return $address;
                    break;
                case 'sname':
                    return mb_strtoupper($this->_name, 'UTF-8') .
                        ' ' . ucwords(mb_strtolower($this->_surname, 'UTF-8'));
                    break;
            }
        } else {
            return false;
        }
    }

    /**
     * Get member email
     * If member does not have an email address, but is attached to
     * another member, we'll take informations from its parent.
     *
     * @return string
     */
    public function getEmail()
    {
        $email = $this->_email;
        if (empty($email)) {
            $this->loadParent();
            $email = $this->parent->email;
        }

        return $email;
    }

    /**
     * Get member address.
     * If member does not have an address, but is attached to another member, we'll take informations from its parent.
     *
     * @return string
     */
    public function getAddress()
    {
        $address = $this->_address;
        if (empty($address) && $this->hasParent()) {
            $this->loadParent();
            $address = $this->parent->address;
        }

        return $address;
    }

    /**
     * Get member address continuation.
     * If member does not have an address, but is attached to another member, we'll take informations from its parent.
     *
     * @return string
     */
    public function getAddressContinuation()
    {
        $address = $this->_address;
        $address_continuation = $this->_address_continuation;
        if (empty($address) && $this->hasParent()) {
            $this->loadParent();
            $address_continuation = $this->parent->address_continuation;
        }

        return $address_continuation;
    }

    /**
     * Get member zipcode.
     * If member does not have an address, but is attached to another member, we'll take informations from its parent.
     *
     * @return string
     */
    public function getZipcode()
    {
        $address = $this->_address;
        $zip = $this->_zipcode;
        if (empty($address) && $this->hasParent()) {
            $this->loadParent();
            $zip = $this->parent->zipcode;
        }

        return $zip;
    }

    /**
     * Get member town.
     * If member does not have an address, but is attached to another member, we'll take informations from its parent.
     *
     * @return string
     */
    public function getTown()
    {
        $address = $this->_address;
        $town = $this->_town;
        if (empty($address) && $this->hasParent()) {
            $this->loadParent();
            $town = $this->parent->town;
        }

        return $town;
    }

    /**
     * Get member country.
     * If member does not have an address, but is attached to another member, we'll take informations from its parent.
     *
     * @return string
     */
    public function getCountry()
    {
        $address = $this->_address;
        $country = $this->_country;
        if (empty($address) && $this->hasParent()) {
            $this->loadParent();
            $country = $this->parent->country;
        }

        return $country;
    }

    /**
     * Get member age
     *
     * @return string
     */
    public function getAge()
    {
        if ($this->_birthdate == null) {
            return '';
        }

        $d = \DateTime::createFromFormat('Y-m-d', $this->_birthdate);
        if ($d === false) {
            Analog::log(
                'Invalid birthdate: ' . $this->_birthdate,
                Analog::ERROR
            );
            return;
        }

        return str_replace(
            '%age',
            $d->diff(new \DateTime())->y,
            _T(' (%age years old)')
        );
    }
}
