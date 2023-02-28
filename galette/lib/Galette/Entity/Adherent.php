<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Member class for galette
 *
 * PHP version 5
 *
 * Copyright © 2009-2023 The Galette Team
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
 * @copyright 2009-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-06-02
 */

namespace Galette\Entity;

use ArrayObject;
use Galette\Events\GaletteEvent;
use Galette\Features\Socials;
use Throwable;
use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Galette\Core\Db;
use Galette\Core\Picture;
use Galette\Core\GaletteMail;
use Galette\Core\Password;
use Galette\Core\Preferences;
use Galette\Core\History;
use Galette\Repository\Groups;
use Galette\Core\Login;
use Galette\Repository\Members;
use Galette\Features\Dynamics;

/**
 * Member class for galette
 *
 * @category  Entity
 * @name      Adherent
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 02-06-2009
 *
 * @property integer $id
 * @property integer|Title $title Either a title id or an instance of Title
 * @property string $stitle Title label
 * @property string $company_name
 * @property string $name
 * @property string $surname
 * @property string $nickname
 * @property string $birthdate Localized birthdate
 * @property string $rbirthdate Raw birthdate
 * @property string $birth_place
 * @property integer $gender
 * @property string $sgender Gender label
 * @property string $job
 * @property string $language
 * @property integer $status
 * @property string $sstatus Status label
 * @property string $address
 * @property string $zipcode
 * @property string $town
 * @property string $country
 * @property string $phone
 * @property string $gsm
 * @property string $email
 * @property string $gnupgid
 * @property string $fingerprint
 * @property string $login
 * @property string $creation_date Localized creation date
 * @property string $modification_date Localized modification date
 * @property string $due_date Localized due date
 * @property string $others_infos
 * @property string $others_infos_admin
 * @property Picture $picture
 * @property array $groups
 * @property array $managed_groups
 * @property integer|Adherent $parent Parent id if parent dep is not loaded, Adherent instance otherwise
 * @property array $children
 * @property boolean $admin better to rely on isAdmin()
 * @property boolean $staff better to rely on isStaff()
 * @property boolean $due_free better to rely on isDueFree()
 * @property boolean $appears_in_list better to rely on appearsInMembersList()
 * @property boolean $active better to rely on isActive()
 * @property boolean $duplicate better to rely on isDuplicate()
 * @property string $sadmin yes/no
 * @property string $sstaff yes/no
 * @property string $sdue_free yes/no
 * @property string $sappears_in_list yes/no
 * @property string $sactive yes/no
 * @property string $sfullname
 * @property string $sname
 * @property string $saddress
 * @property string $contribstatus State of member contributions
 * @property string $days_remaining
 * @property-read integer $parent_id
 * @property Social $social Social networks/Contact
 * @property string $number Member number
 * @property-read bool $self_adh
 */
class Adherent
{
    use Dynamics;
    use Socials;

    public const TABLE = 'adherents';
    public const PK = 'id_adh';

    public const NC = 0;
    public const MAN = 1;
    public const WOMAN = 2;

    public const AFTER_ADD_DEFAULT = 0;
    public const AFTER_ADD_TRANS = 1;
    public const AFTER_ADD_NEW = 2;
    public const AFTER_ADD_SHOW = 3;
    public const AFTER_ADD_LIST = 4;
    public const AFTER_ADD_HOME = 5;

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
    //Contact information
    private $_address;
    private $_zipcode;
    private $_town;
    private $_country;
    private $_phone;
    private $_gsm;
    private $_email;
    private $_gnupgid;
    private $_fingerprint;
    //Galette relative information
    private $_appears_in_list;
    private $_admin;
    private $_staff = false;
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
    private $_groups = [];
    private $_managed_groups = [];
    private $_parent;
    private $_children;
    private $_duplicate = false;
    private $_socials;
    private $_number;

    private $_row_classes;

    private $_self_adh = false;
    private $_deps = array(
        'picture'   => true,
        'groups'    => true,
        'dues'      => true,
        'parent'    => false,
        'children'  => false,
        'dynamics'  => false,
        'socials'   => false
    );

    private $zdb;
    private $preferences;
    private $fields;
    private $history;

    private $parent_fields = [
        'adresse_adh',
        'cp_adh',
        'ville_adh',
        'email_adh'
    ];

    private $errors = [];

    private $sendmail = false;

    /**
     * Default constructor
     *
     * @param Db               $zdb  Database instance
     * @param mixed            $args Either a ResultSet row, its id or its
     *                               login or its email for to load s specific
     *                               member, or null to just instantiate object
     * @param false|array|null $deps Dependencies configuration, see Adherent::$_deps
     */
    public function __construct(Db $zdb, $args = null, $deps = null)
    {
        global $i18n;

        $this->zdb = $zdb;

        if ($deps !== null) {
            if (is_array($deps)) {
                $this->_deps = array_merge(
                    $this->_deps,
                    $deps
                );
            } elseif ($deps === false) {
                //no dependencies
                $this->_deps = array_fill_keys(
                    array_keys($this->_deps),
                    false
                );
            } else {
                Analog::log(
                    '$deps should be an array, ' . gettype($deps) . ' given!',
                    Analog::WARNING
                );
            }
        }

        if ($args == null || is_int($args)) {
            if (is_int($args) && $args > 0) {
                $this->load($args);
            } else {
                $this->_active = true;
                $this->_language = $i18n->getID();
                $this->_creation_date = date("Y-m-d");
                $this->_status = $this->getDefaultStatus();
                $this->_title = null;
                $this->_gender = self::NC;
                $gp = new Password($this->zdb);
                $this->_password = $gp->makeRandomPassword();
                $this->_picture = new Picture();
                $this->_admin = false;
                $this->_staff = false;
                $this->_due_free = false;
                $this->_appears_in_list = false;
                $this->_parent = null;

                if ($this->_deps['dynamics'] === true) {
                    $this->loadDynamicFields();
                }
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
     * @param int $id the identifier for the member to load
     *
     * @return bool true if query succeed, false otherwise
     */
    public function load(int $id): bool
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

            /** @var ArrayObject $result */
            $result = $results->current();
            $this->loadFromRS($result);
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot load member form id `' . $id . '` | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Loads a member from its login
     *
     * @param string $login login for the member to load
     *
     * @return boolean
     */
    public function loadFromLoginOrMail(string $login): bool
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
            if ($results->count() > 0) {
                /** @var ArrayObject $result */
                $result = $results->current();
                $this->loadFromRS($result);
            }
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot load member form login `' . $login . '` | ' .
                $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ArrayObject $r the resultset row
     *
     * @return void
     */
    private function loadFromRS(ArrayObject $r): void
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
        $this->_active = $r->activite_adh == 1;
        $this->_status = (int)$r->id_statut;
        //Contact information
        $this->_address = $r->adresse_adh;
        $this->_zipcode = $r->cp_adh;
        $this->_town = $r->ville_adh;
        $this->_country = $r->pays_adh;
        $this->_phone = $r->tel_adh;
        $this->_gsm = $r->gsm_adh;
        $this->_email = $r->email_adh;
        $this->_gnupgid = $r->gpgid;
        $this->_fingerprint = $r->fingerprint;
        //Galette relative information
        $this->_appears_in_list = $r->bool_display_info == 1;
        $this->_admin = $r->bool_admin_adh == 1;
        if (
            isset($r->priorite_statut)
            && $r->priorite_statut < Members::NON_STAFF_MEMBERS
        ) {
            $this->_staff = true;
        }
        $this->_due_free = $r->bool_exempt_adh == 1;
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
        $this->_number = $r->num_adh;

        if ($r->parent_id !== null) {
            $this->_parent = (int)$r->parent_id;
            if ($this->_deps['parent'] === true) {
                $this->loadParent();
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

        if ($this->_deps['dynamics'] === true) {
            $this->loadDynamicFields();
        }

        if ($this->_deps['socials'] === true) {
            $this->loadSocials();
        }
    }

    /**
     * Load member parent
     *
     * @return void
     */
    private function loadParent(): void
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
    private function loadChildren(): void
    {
        $this->_children = array();
        try {
            $id = self::PK;
            $select = $this->zdb->select(self::TABLE);
            $select->columns(
                array($id)
            )->where(['parent_id' => $this->_id]);

            $results = $this->zdb->execute($select);

            if ($results->count() > 0) {
                foreach ($results as $row) {
                    $deps = $this->_deps;
                    $deps['children'] = false;
                    $deps['parent'] = false;
                    $this->_children[] = new Adherent($this->zdb, (int)$row->$id, $deps);
                }
            }
        } catch (Throwable $e) {
            Analog::log(
                'Cannot load children for member #' . $this->_id . ' | ' .
                $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Load member groups
     *
     * @return void
     */
    public function loadGroups(): void
    {
        $this->_groups = Groups::loadGroups($this->_id);
        $this->_managed_groups = Groups::loadManagedGroups($this->_id);
    }

    /**
     * Load member social network/contact information
     *
     * @return void
     */
    public function loadSocials(): void
    {
        $this->_socials = Social::getListForMember($this->_id);
    }

    /**
     * Retrieve status from preferences
     *
     * @return integer
     *
     */
    private function getDefaultStatus(): int
    {
        global $preferences;
        if ($preferences->pref_statut != '') {
            return $preferences->pref_statut;
        } else {
            Analog::log(
                'Unable to get pref_statut; is it defined in preferences?',
                Analog::ERROR
            );
            return Status::DEFAULT_STATUS;
        }
    }

    /**
     * Check for dues status
     *
     * @return void
     */
    private function checkDues(): void
    {
        //how many days since our beloved member has been created
        $now = new \DateTime();
        $this->_oldness = $now->diff(
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
                // To count the days remaining, the next begin date is required.
                $due_date = new \DateTime($this->_due_date);
                $next_begin_date = clone $due_date;
                $next_begin_date->add(new \DateInterval('P1D'));
                $date_diff = $now->diff($next_begin_date);
                $this->_days_remaining = $date_diff->days;
                // Active
                if ($date_diff->invert == 0 && $date_diff->days >= 0) {
                    $this->_days_remaining = $date_diff->days;
                    if ($this->_days_remaining <= 30) {
                        if ($date_diff->days == 0) {
                            $this->_row_classes .= ' cotis-lastday';
                        }
                        $this->_row_classes .= ' cotis-soon';
                    } else {
                        $this->_row_classes .= ' cotis-ok';
                    }
                // Expired
                } elseif ($date_diff->invert == 1 && $date_diff->days >= 0) {
                    $this->_days_remaining = $date_diff->days;
                    //check if member is still active
                    $this->_row_classes .= $this->isActive() ? ' cotis-late' : ' cotis-old';
                }
            }
        }
    }

    /**
     * Is member admin?
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->_admin;
    }

    /**
     * Is user member of staff?
     *
     * @return bool
     */
    public function isStaff(): bool
    {
        return $this->_staff;
    }

    /**
     * Is member freed of dues?
     *
     * @return bool
     */
    public function isDueFree(): bool
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
    public function isGroupMember(string $group_name): bool
    {
        if (!$this->isDepEnabled('groups')) {
            $this->loadGroups();
        }

        foreach ($this->_groups as $g) {
            if ($g->getName() == $group_name) {
                return true;
            }
        }
        return false;
    }

    /**
     * Is member manager of specified group?
     *
     * @param string $group_name Group name
     *
     * @return boolean
     */
    public function isGroupManager(string $group_name): bool
    {
        if (!$this->isDepEnabled('groups')) {
            $this->loadGroups();
        }

        foreach ($this->_managed_groups as $mg) {
            if ($mg->getName() == $group_name) {
                return true;
            }
        }
        return false;
    }

    /**
     * Does current member represents a company?
     *
     * @return boolean
     */
    public function isCompany(): bool
    {
        return trim($this->_company_name ?? '') != '';
    }

    /**
     * Is current member a man?
     *
     * @return boolean
     */
    public function isMan(): bool
    {
        return (int)$this->_gender === self::MAN;
    }

    /**
     * Is current member a woman?
     *
     * @return boolean
     */
    public function isWoman(): bool
    {
        return (int)$this->_gender === self::WOMAN;
    }


    /**
     * Can member appears in public members list?
     *
     * @return bool
     */
    public function appearsInMembersList(): bool
    {
        return $this->_appears_in_list;
    }

    /**
     * Is member active?
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->_active;
    }

    /**
     * Does member have uploaded a picture?
     *
     * @return bool
     */
    public function hasPicture(): bool
    {
        return $this->_picture->hasPicture();
    }

    /**
     * Does member have a parent?
     *
     * @return bool
     */
    public function hasParent(): bool
    {
        return !empty($this->_parent);
    }

    /**
     * Does member have children?
     *
     * @return bool
     */
    public function hasChildren(): bool
    {
        if ($this->_children === null) {
            if ($this->id) {
                Analog::log(
                    'Children has not been loaded!',
                    Analog::WARNING
                );
            }
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
    public function getRowClass(bool $public = false): string
    {
        $strclass = ($this->isActive()) ? 'active-account' : 'inactive-account';
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
    public function getDues(): string
    {
        $ret = '';
        $never_contributed = false;
        $now = new \DateTime();
        // To count the days remaining, the next begin date is required.
        if ($this->_due_date === null) {
            $this->_due_date = $now->format('Y-m-d');
            $never_contributed = true;
        }
        $due_date = new \DateTime($this->_due_date);
        $next_begin_date = clone $due_date;
        $next_begin_date->add(new \DateInterval('P1D'));
        $date_diff = $now->diff($next_begin_date);
        if ($this->isDueFree()) {
            $ret = _T("Freed of dues");
        } elseif ($never_contributed === true) {
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
        // Last active or first expired day
        } elseif ($this->_days_remaining == 0) {
            if ($date_diff->invert == 0) {
                $ret = _T("Last day!");
            } else {
                $ret = _T("Late since today!");
            }
        // Active
        } elseif ($date_diff->invert == 0 && $this->_days_remaining > 0) {
            $patterns = array('/%days/', '/%date/');
            $replace = array(
                $this->_days_remaining,
                $due_date->format(__("Y-m-d"))
            );
            $ret = preg_replace(
                $patterns,
                $replace,
                _T("%days days remaining (ending on %date)")
            );
        // Expired
        } elseif ($date_diff->invert == 1 && $this->_days_remaining > 0) {
            $patterns = array('/%days/', '/%date/');
            $replace = array(
                // We need the number of days expired, not the number of days remaining.
                $this->_days_remaining + 1,
                $due_date->format(__("Y-m-d"))
            );
            if ($this->_active) {
                $ret = preg_replace(
                    $patterns,
                    $replace,
                    _T("Late of %days days (since %date)")
                );
            } else {
                $ret = _T("No longer member");
            }
        }
        return $ret;
    }

    /**
     * Retrieve Full name and surname for the specified member id
     *
     * @param Db      $zdb   Database instance
     * @param integer $id    Member id
     * @param boolean $wid   Add member id
     * @param boolean $wnick Add member nickname
     *
     * @return string formatted Name and Surname
     */
    public static function getSName(Db $zdb, int $id, bool $wid = false, bool $wnick = false): string
    {
        try {
            $select = $zdb->select(self::TABLE);
            $select->where([self::PK => $id]);

            $results = $zdb->execute($select);
            $row = $results->current();
            return self::getNameWithCase(
                $row->nom_adh,
                $row->prenom_adh,
                false,
                ($wid === true ? $row->id_adh : false),
                ($wnick === true ? $row->pseudo_adh : false)
            );
        } catch (Throwable $e) {
            Analog::log(
                'Cannot get formatted name for member form id `' . $id . '` | ' .
                $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Get member name with correct case
     *
     * @param string        $name    Member name
     * @param string        $surname Member surname
     * @param false|Title   $title   Member title to show or false
     * @param false|integer $id      Member id to display or false
     * @param false|string  $nick    Member nickname to display or false
     *
     * @return string
     */
    public static function getNameWithCase(
        ?string $name,
        ?string $surname,
        $title = false,
        $id = false,
        $nick = false
    ): string {
        $str = '';

        if ($title instanceof Title) {
            $str .= $title->tshort . ' ';
        }

        $str .= mb_strtoupper($name ?? '', 'UTF-8') . ' ' .
            ucwords(mb_strtolower($surname ?? '', 'UTF-8'), " \t\r\n\f\v-_|");

        if ($id !== false || !empty($nick)) {
            $str .= ' (';
        }
        if (!empty($nick)) {
            $str .= $nick;
        }
        if ($id !== false) {
            if (!empty($nick)) {
                $str .= ', ';
            }
            $str .= $id;
        }
        if ($id !== false || !empty($nick)) {
            $str .= ')';
        }
        return strip_tags($str);
    }

    /**
     * Change password for a given user
     *
     * @param Db      $zdb    Database instance
     * @param integer $id_adh Member identifier
     * @param string  $pass   New password
     *
     * @return boolean
     */
    public static function updatePassword(Db $zdb, int $id_adh, string $pass): bool
    {
        try {
            $cpass = password_hash($pass, PASSWORD_BCRYPT);

            $update = $zdb->update(self::TABLE);
            $update->set(
                array('mdp_adh' => $cpass)
            )->where([self::PK => $id_adh]);
            $zdb->execute($update);
            Analog::log(
                'Password for `' . $id_adh . '` has been updated.',
                Analog::DEBUG
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred while updating password for `' . $id_adh .
                '` | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Get field label
     *
     * @param string $field Field name
     *
     * @return string
     */
    private function getFieldLabel(string $field): string
    {
        $label = $this->fields[$field]['label'] ?? '';
        //replace "&nbsp;"
        $label = str_replace('&nbsp;', ' ', $label);
        //remove trailing ':' and then trim
        $label = trim(trim($label, ':'));
        return $label;
    }

    /**
     * Retrieve fields from database
     *
     * @param Db $zdb Database instance
     *
     * @return array
     */
    public static function getDbFields(Db $zdb): array
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
    public function setSelfMembership(): void
    {
        $this->_self_adh = true;
    }

    /**
     * Is member up to date?
     *
     * @return boolean
     */
    public function isUp2Date(): bool
    {
        if (!$this->isDepEnabled('dues')) {
            $this->checkDues();
        }

        if ($this->isDueFree()) {
            //member is due free, he's up to date.
            return true;
        } else {
            //let's check from due date, if present
            if ($this->_due_date == null) {
                return false;
            } else {
                $due_date = new \DateTime($this->_due_date);
                $now = new \DateTime();
                $now->setTime(0, 0, 0);
                return $due_date >= $now;
            }
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
    public function check(array $values, array $required, array $disabled)
    {
        global $login;

        $this->errors = array();

        //Sanitize
        foreach ($values as &$rawvalue) {
            if (is_string($rawvalue)) {
                $rawvalue = strip_tags($rawvalue);
            }
        }

        $fields = self::getDbFields($this->zdb);

        //reset company name if needed
        if (!isset($values['is_company'])) {
            unset($values['is_company']);
            $values['societe_adh'] = '';
        }

        //no parent if checkbox was unchecked
        if (
            !isset($values['attach'])
            && empty($this->_id)
            && isset($values['parent_id'])
        ) {
            unset($values['parent_id']);
        }

        if (isset($values['duplicate'])) {
            //if we're duplicating, keep a trace (if an error occurs)
            $this->_duplicate = true;
        }

        foreach ($fields as $key) {
            //first, let's sanitize values
            $key = strtolower($key);
            $prop = '_' . $this->fields[$key]['propname'];

            if (isset($values[$key])) {
                $value = $values[$key];
                if ($value !== true && $value !== false) {
                    //@phpstan-ignore-next-line
                    $value = trim($value ?? '');
                }
            } elseif (empty($this->_id)) {
                switch ($key) {
                    case 'bool_admin_adh':
                    case 'bool_exempt_adh':
                    case 'bool_display_info':
                        $value = 0;
                        break;
                    case 'activite_adh':
                        //values that are set at object instantiation
                        $value = true;
                        break;
                    case 'date_crea_adh':
                    case 'sexe_adh':
                    case 'titre_adh':
                    case 'id_statut':
                    case 'pref_lang':
                    case 'parent_id':
                        //values that are set at object instantiation
                        $value = $this->$prop;
                        break;
                    case self::PK:
                        $value = null;
                        break;
                    default:
                        $value = '';
                        break;
                }
            } else {
                //keep stored value on update
                if ($prop != '_password' || isset($values['mdp_adh']) && isset($values['mdp_adh2'])) {
                    $value = $this->$prop;
                } else {
                    $value = null;
                }
            }

            // if the field is enabled, check it
            if (!isset($disabled[$key])) {
                // fill up the adherent structure
                if ($value !== null && $value !== true && $value !== false && !is_object($value)) {
                    $value = stripslashes($value);
                }
                $this->$prop = $value;

                // now, check validity
                if ($value !== null && $value != '') {
                    if ($key !== 'mdp_adh') {
                        $this->validate($key, $value, $values);
                    }
                } elseif (empty($this->_id)) {
                    //ensure login and password are not empty
                    if (($key == 'login_adh' || $key == 'mdp_adh') && !isset($required[$key])) {
                        $p = new Password($this->zdb);
                        $generated_value = $p->makeRandomPassword(15);
                        if ($key == 'login_adh') {
                            //'@' is not permitted in logins
                            $this->$prop = str_replace('@', 'a', $generated_value);
                        } else {
                            $this->$prop = $generated_value;
                        }
                    }
                }
            }
        }

        //password checks need data to be previously set
        if (isset($values['mdp_adh'])) {
            $this->validate('mdp_adh', $values['mdp_adh'], $values);
        }

        // missing required fields?
        foreach ($required as $key => $val) {
            $prop = '_' . $this->fields[$key]['propname'];

            if (!isset($disabled[$key])) {
                $mandatory_missing = false;
                if (!isset($this->$prop) || $this->$prop == '') {
                    $mandatory_missing = true;
                } elseif ($key === 'titre_adh' && $this->$prop == '-1') {
                    $mandatory_missing = true;
                }

                if ($mandatory_missing === true) {
                    $this->errors[] = str_replace(
                        '%field',
                        '<a href="#' . $key . '">' . $this->getFieldLabel($key) . '</a>',
                        _T("- Mandatory field %field empty.")
                    );
                }
            }
        }

        //attach to/detach from parent
        if (isset($values['detach_parent'])) {
            $this->_parent = null;
        }

        if ($login->isGroupManager() && !$login->isAdmin() && !$login->isStaff() && $this->parent_id !== $login->id) {
            if (!isset($values['groups_adh'])) {
                $this->errors[] = _T('You have to select a group you own!');
            } else {
                $owned_group = false;
                foreach ($values['groups_adh'] as $group) {
                    list($gid) = explode('|', $group);
                    if ($login->isGroupManager($gid)) {
                        $owned_group = true;
                    }
                }
                if ($owned_group === false) {
                    $this->errors[] = _T('You have to select a group you own!');
                }
            }
        }

        $this->dynamicsCheck($values, $required, $disabled);
        $this->checkSocials($values);

        if (count($this->errors) > 0) {
            Analog::log(
                'Some errors has been thew attempting to edit/store a member' . "\n" .
                print_r($this->errors, true),
                Analog::ERROR
            );
            return $this->errors;
        } else {
            $this->checkDues();

            Analog::log(
                'Member checked successfully.',
                Analog::DEBUG
            );
            return true;
        }
    }

    /**
     * Validate data for given key
     * Set valid data in current object, also resets errors list
     *
     * @param string $field  Field name
     * @param mixed  $value  Value we want to set
     * @param array  $values All values, for some references
     *
     * @return void
     */
    public function validate(string $field, $value, array $values): void
    {
        global $preferences;

        $prop = '_' . $this->fields[$field]['propname'];

        if ($value === null || (is_string($value) && trim($value) == '')) {
            //empty values are OK
            $this->$prop = $value;
            return;
        }

        switch ($field) {
            // dates
            case 'date_crea_adh':
            case 'date_modif_adh_':
            case 'ddn_adh':
            case 'date_echeance':
                try {
                    $d = \DateTime::createFromFormat(__("Y-m-d"), $value);
                    if ($d === false) {
                        //try with non localized date
                        $d = \DateTime::createFromFormat("Y-m-d", $value);
                        if ($d === false) {
                            throw new \Exception('Incorrect format');
                        }
                    }

                    if ($field === 'ddn_adh') {
                        $now = new \DateTime();
                        $now->setTime(0, 0, 0);
                        $d->setTime(0, 0, 0);

                        $diff = $now->diff($d);
                        $days = (int)$diff->format('%R%a');
                        if ($days >= 0) {
                            $this->errors[] = _T('- Birthdate must be set in the past!');
                        }

                        $years = (int)$diff->format('%R%Y');
                        if ($years <= -200) {
                            $this->errors[] = str_replace(
                                '%years',
                                $years * -1,
                                _T('- Members must be less than 200 years old (currently %years)!')
                            );
                        }
                    }
                    $this->$prop = $d->format('Y-m-d');
                } catch (Throwable $e) {
                    Analog::log(
                        'Wrong date format. field: ' . $field .
                        ', value: ' . $value . ', expected fmt: ' .
                        __("Y-m-d") . ' | ' . $e->getMessage(),
                        Analog::INFO
                    );
                    $this->errors[] = str_replace(
                        array(
                            '%date_format',
                            '%field'
                        ),
                        array(
                            __("Y-m-d"),
                            $this->getFieldLabel($field)
                        ),
                        _T("- Wrong date format (%date_format) for %field!")
                    );
                }
                break;
            case 'titre_adh':
                if ($value !== null && $value !== '') {
                    if ($value == '-1') {
                        $this->$prop = null;
                    } elseif (!$value instanceof Title) {
                        $this->$prop = new Title((int)$value);
                    }
                } else {
                    $this->$prop = null;
                }
                break;
            case 'email_adh':
                if (!GaletteMail::isValidEmail($value)) {
                    $this->errors[] = _T("- Non-valid E-Mail address!") .
                        ' (' . $this->getFieldLabel($field) . ')';
                }
                if ($field == 'email_adh') {
                    try {
                        $select = $this->zdb->select(self::TABLE);
                        $select->columns(
                            array(self::PK)
                        )->where(array('email_adh' => $value));
                        if (!empty($this->_id)) {
                            $select->where->notEqualTo(
                                self::PK,
                                $this->_id
                            );
                        }

                        $results = $this->zdb->execute($select);
                        if ($results->count() !== 0) {
                            $this->errors[] = _T("- This E-Mail address is already used by another member!");
                        }
                    } catch (Throwable $e) {
                        Analog::log(
                            'An error occurred checking member email uniqueness.',
                            Analog::ERROR
                        );
                        $this->errors[] = _T("An error has occurred while looking if login already exists.");
                    }
                }
                break;
            case 'login_adh':
                /** FIXME: add a preference for login length */
                if (strlen($value) < 2) {
                    $this->errors[] = str_replace(
                        '%i',
                        2,
                        _T("- The username must be composed of at least %i characters!")
                    );
                } else {
                    //check if login does not contain the @ character
                    if (strpos($value, '@') != false) {
                        $this->errors[] = _T("- The username cannot contain the @ character");
                    } else {
                        //check if login is already taken
                        try {
                            $select = $this->zdb->select(self::TABLE);
                            $select->columns(
                                array(self::PK)
                            )->where(array('login_adh' => $value));
                            if (!empty($this->_id)) {
                                $select->where->notEqualTo(
                                    self::PK,
                                    $this->_id
                                );
                            }

                            $results = $this->zdb->execute($select);
                            if (
                                $results->count() !== 0
                                || $value == $preferences->pref_admin_login
                            ) {
                                $this->errors[] = _T("- This username is already in use, please choose another one!");
                            }
                        } catch (Throwable $e) {
                            Analog::log(
                                'An error occurred checking member login uniqueness.',
                                Analog::ERROR
                            );
                            $this->errors[] = _T("An error has occurred while looking if login already exists.");
                        }
                    }
                }
                break;
            case 'mdp_adh':
                if (
                    $this->_self_adh !== true
                    && (!isset($values['mdp_adh2'])
                    || $values['mdp_adh2'] != $value)
                ) {
                    $this->errors[] = _T("- The passwords don't match!");
                } elseif (
                    $this->_self_adh === true
                    && !crypt($value, $values['mdp_crypt']) == $values['mdp_crypt']
                ) {
                    $this->errors[] = _T("Password misrepeated: ");
                } else {
                    $pinfos = password_get_info($value);
                    //check if value is already a hash
                    if ($pinfos['algo'] == 0) {
                        $this->$prop = password_hash(
                            $value,
                            PASSWORD_BCRYPT
                        );

                        $pwcheck = new \Galette\Util\Password($preferences);
                        $pwcheck->setAdherent($this);
                        if (!$pwcheck->isValid($value)) {
                            $this->errors = array_merge(
                                $this->errors,
                                $pwcheck->getErrors()
                            );
                        }
                    }
                }
                break;
            case 'id_statut':
                try {
                    $this->$prop = (int)$value;
                    //check if status exists
                    $select = $this->zdb->select(Status::TABLE);
                    $select->where([Status::PK => $value]);

                    $results = $this->zdb->execute($select);
                    $result = $results->current();
                    if (!$result) {
                        $this->errors[] = str_replace(
                            '%id',
                            $value,
                            _T("Status #%id does not exists in database.")
                        );
                        break;
                    }
                } catch (Throwable $e) {
                    Analog::log(
                        'An error occurred checking status existence: ' . $e->getMessage(),
                        Analog::ERROR
                    );
                    $this->errors[] = _T("An error has occurred while looking if status does exists.");
                }
                break;
            case 'sexe_adh':
                if (in_array($value, [self::NC, self::MAN, self::WOMAN])) {
                    $this->$prop = (int)$value;
                } else {
                    $this->errors[] = _T("Gender %gender does not exists!");
                }
                break;
            case 'parent_id':
                $this->$prop = ($value instanceof Adherent) ? (int)$value->id : (int)$value;
                $this->loadParent();
                break;
        }
    }

    /**
     * Store the member
     *
     * @return boolean
     */
    public function store(): bool
    {
        global $hist, $emitter, $login;
        $event = null;

        if (!$login->isAdmin() && !$login->isStaff() && !$login->isGroupManager() && $this->id == '') {
            if ($this->preferences->pref_bool_create_member) {
                $this->_parent = $login->id;
            }
        }

        try {
            $values = array();
            $fields = self::getDbFields($this->zdb);

            foreach ($fields as $field) {
                if (
                    $field !== 'date_modif_adh'
                    || empty($this->_id)
                ) {
                    $prop = '_' . $this->fields[$field]['propname'];
                    if (
                        ($field === 'bool_admin_adh'
                        || $field === 'bool_exempt_adh'
                        || $field === 'bool_display_info'
                        || $field === 'activite_adh')
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

            if (!$this->_number) {
                $values['num_adh'] = new Expression('NULL');
            }

            //fields that cannot be null
            $notnull = [
                '_surname'  => 'prenom_adh',
                '_nickname' => 'pseudo_adh',
                '_address'  => 'adresse_adh',
                '_zipcode'  => 'cp_adh',
                '_town'     => 'ville_adh'
            ];
            foreach ($notnull as $prop => $field) {
                if ($this->$prop === null) {
                    $values[$field] = '';
                }
            }

            $success = false;
            if (empty($this->_id)) {
                //we're inserting a new member
                unset($values[self::PK]);
                //set modification date
                $this->_modification_date = date('Y-m-d');
                $values['date_modif_adh'] = $this->_modification_date;

                $insert = $this->zdb->insert(self::TABLE);
                $insert->values($values);
                $add = $this->zdb->execute($insert);
                if ($add->count() > 0) {
                    $this->_id = $this->zdb->getLastGeneratedValue($this);
                    $this->_picture = new Picture($this->_id);
                    // logging
                    if ($this->_self_adh) {
                        $hist->add(
                            _T("Self_subscription as a member: ") .
                            $this->getNameWithCase($this->_name, $this->_surname),
                            $this->sname
                        );
                    } else {
                        $hist->add(
                            _T("Member card added"),
                            $this->sname
                        );
                    }
                    $success = true;

                    $event = 'member.add';
                } else {
                    $hist->add(_T("Fail to add new member."));
                    throw new \Exception(
                        'An error occurred inserting new member!'
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
                $update->where([self::PK => $this->_id]);

                $edit = $this->zdb->execute($update);

                //edit == 0 does not mean there were an error, but that there
                //were nothing to change
                if ($edit->count() > 0) {
                    $this->updateModificationDate();
                    $hist->add(
                        _T("Member card updated"),
                        $this->sname
                    );
                }
                $success = true;
                $event = 'member.edit';
            }

            //dynamic fields
            if ($success) {
                $success = $this->dynamicsStore();
                $this->storeSocials($this->id);
            }

            //send event at the end of process, once all has been stored
            if ($event !== null) {
                $emitter->dispatch(new GaletteEvent($event, $this));
            }
            return $success;
        } catch (Throwable $e) {
            Analog::log(
                'Something went wrong :\'( | ' . $e->getMessage() . "\n" .
                $e->getTraceAsString(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Update member modification date
     *
     * @return void
     */
    private function updateModificationDate(): void
    {
        try {
            $modif_date = date('Y-m-d');
            $update = $this->zdb->update(self::TABLE);
            $update->set(
                array('date_modif_adh' => $modif_date)
            )->where([self::PK => $this->_id]);

            $edit = $this->zdb->execute($update);
            $this->_modification_date = $modif_date;
        } catch (Throwable $e) {
            Analog::log(
                'Something went wrong updating modif date :\'( | ' .
                $e->getMessage() . "\n" . $e->getTraceAsString(),
                Analog::ERROR
            );
            throw $e;
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
        $forbidden = array(
            'admin', 'staff', 'due_free', 'appears_in_list', 'active',
            'row_classes', 'oldness', 'duplicate', 'groups', 'managed_groups'
        );
        if (!defined('GALETTE_TESTS')) {
            $forbidden[] = 'password'; //keep that for tests only
        }

        $virtuals = array(
            'sadmin', 'sstaff', 'sdue_free', 'sappears_in_list', 'sactive',
            'stitle', 'sstatus', 'sfullname', 'sname', 'saddress',
            'rbirthdate', 'sgender', 'contribstatus',
        );

        $socials = array('website', 'msn', 'jabber', 'icq');

        if (in_array($name, $forbidden)) {
            Analog::log(
                'Calling property "' . $name . '" directly is discouraged.',
                Analog::WARNING
            );
            switch ($name) {
                case 'admin':
                    return $this->isAdmin();
                case 'staff':
                    return $this->isStaff();
                case 'due_free':
                    return $this->isDueFree();
                case 'appears_in_list':
                    return $this->appearsInMembersList();
                case 'active':
                    return $this->isActive();
                case 'duplicate':
                    return $this->isDuplicate();
                case 'groups':
                    return $this->getGroups();
                case 'managed_groups':
                    return $this->getManagedGroups();
                default:
                    throw new \RuntimeException("Call to __get for '$name' is forbidden!");
            }
        }

        if (in_array($name, $virtuals)) {
            if (substr($name, 0, 1) !== '_') {
                $real = '_' . substr($name, 1);
            } else {
                $real = $name;
            }
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
                    if (isset($this->_title) && $this->_title instanceof Title) {
                        return $this->_title->tshort;
                    } else {
                        return null;
                    }
                    break;
                case 'sstatus':
                    $status = new Status($this->zdb);
                    return $status->getLabel($this->_status);
                case 'sfullname':
                    return $this->getNameWithCase(
                        $this->_name,
                        $this->_surname,
                        (isset($this->_title) ? $this->title : false)
                    );
                case 'saddress':
                    $address = $this->_address;
                    return $address;
                case 'sname':
                    return $this->getNameWithCase($this->_name, $this->_surname);
                case 'rbirthdate':
                    return $this->_birthdate;
                case 'sgender':
                    switch ($this->gender) {
                        case self::MAN:
                            return _T('Man');
                        case self::WOMAN:
                            return _T('Woman');
                        default:
                            return _T('Unspecified');
                    }
                case 'contribstatus':
                    return $this->getDues();
            }
        }

        //for backward compatibility
        if (in_array($name, $socials)) {
            $values = Social::getListForMember($this->_id, $name);
            return $values[0] ?? null;
        }

        if (substr($name, 0, 1) !== '_') {
            $rname = '_' . $name;
        } else {
            $rname = $name;
        }

        switch ($name) {
            case 'id':
            case 'id_statut':
                if ($this->$rname !== null) {
                    return (int)$this->$rname;
                } else {
                    return null;
                }
            case 'address':
                return $this->$rname ?? '';
            case 'birthdate':
            case 'creation_date':
            case 'modification_date':
            case 'due_date':
                if ($this->$rname != '') {
                    try {
                        $d = new \DateTime($this->$rname);
                        return $d->format(__("Y-m-d"));
                    } catch (Throwable $e) {
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
            case 'parent_id':
                return ($this->_parent instanceof Adherent) ? (int)$this->_parent->id : (int)$this->_parent;
            default:
                if (!property_exists($this, $rname)) {
                    Analog::log(
                        "Unknown property '$rname'",
                        Analog::WARNING
                    );
                    return null;
                } else {
                    return $this->$rname;
                }
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
    public function __isset(string $name)
    {
        $forbidden = array(
            'admin', 'staff', 'due_free', 'appears_in_list', 'active',
            'row_classes', 'oldness', 'duplicate', 'groups', 'managed_groups'
        );
        if (!defined('GALETTE_TESTS')) {
            $forbidden[] = 'password'; //keep that for tests only
        }

        $virtuals = array(
            'sadmin', 'sstaff', 'sdue_free', 'sappears_in_list', 'sactive',
            'stitle', 'sstatus', 'sfullname', 'sname', 'saddress',
            'rbirthdate', 'sgender', 'contribstatus',
        );

        $socials = array('website', 'msn', 'jabber', 'icq');

        if (in_array($name, $forbidden)) {
            Analog::log(
                'Calling property "' . $name . '" directly is discouraged.',
                Analog::WARNING
            );
            switch ($name) {
                case 'admin':
                case 'staff':
                case 'due_free':
                case 'appears_in_list':
                case 'active':
                case 'duplicate':
                case 'groups':
                case 'managed_groups':
                    return true;
            }

            return false;
        }

        if (in_array($name, $virtuals)) {
            return true;
        }

        //for backward compatibility
        if (in_array($name, $socials)) {
            return true;
        }

        if (substr($name, 0, 1) !== '_') {
            $rname = '_' . $name;
        } else {
            $rname = $name;
        }

        switch ($name) {
            case 'id':
            case 'id_statut':
            case 'address':
            case 'birthdate':
            case 'creation_date':
            case 'modification_date':
            case 'due_date':
            case 'parent_id':
                return true;
            default:
                return property_exists($this, $rname);
        }

        return false;
    }

    /**
     * Get member email
     * If member does not have an email address, but is attached to
     * another member, we'll take information from its parent.
     *
     * @return string
     */
    public function getEmail(): string
    {
        $email = $this->_email;
        if (empty($email)) {
            $this->loadParent();
            $email = $this->parent->email;
        }

        //@phpstan-ignore-next-line
        return $email ?? '';
    }

    /**
     * Get member address.
     * If member does not have an address, but is attached to another member, we'll take information from its parent.
     *
     * @return string
     */
    public function getAddress(): string
    {
        $address = $this->_address;
        if (empty($address) && $this->hasParent()) {
            $this->loadParent();
            $address = $this->parent->address;
        }

        return $address ?? '';
    }

    /**
     * Get member zipcode.
     * If member does not have an address, but is attached to another member, we'll take information from its parent.
     *
     * @return string
     */
    public function getZipcode(): string
    {
        $address = $this->_address;
        $zip = $this->_zipcode;
        if (empty($address) && $this->hasParent()) {
            $this->loadParent();
            $zip = $this->parent->zipcode;
        }

        return $zip ?? '';
    }

    /**
     * Get member town.
     * If member does not have an address, but is attached to another member, we'll take information from its parent.
     *
     * @return string
     */
    public function getTown(): string
    {
        $address = $this->_address;
        $town = $this->_town;
        if (empty($address) && $this->hasParent()) {
            $this->loadParent();
            $town = $this->parent->town;
        }

        return $town ?? '';
    }

    /**
     * Get member country.
     * If member does not have an address, but is attached to another member, we'll take information from its parent.
     *
     * @return string
     */
    public function getCountry(): string
    {
        $address = $this->_address;
        $country = $this->_country;
        if (empty($address) && $this->hasParent()) {
            $this->loadParent();
            $country = $this->parent->country;
        }

        return $country ?? '';
    }

    /**
     * Get member age
     *
     * @return string
     */
    public function getAge(): string
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
            return '';
        }

        return str_replace(
            '%age',
            $d->diff(new \DateTime())->y,
            _T(' (%age years old)')
        );
    }

    /**
     * Get parent inherited fields
     *
     * @return array
     */
    public function getParentFields(): array
    {
        return $this->parent_fields;
    }

    /**
     * Handle files (photo and dynamics files)
     *
     * @param array $files Files sent
     *
     * @return array|true
     */
    public function handleFiles(array $files)
    {
        $this->errors = [];
        // picture upload
        if (isset($files['photo'])) {
            if ($files['photo']['error'] === UPLOAD_ERR_OK) {
                if ($files['photo']['tmp_name'] != '') {
                    if (is_uploaded_file($files['photo']['tmp_name'])) {
                        $res = $this->picture->store($files['photo']);
                        if ($res < 0) {
                            $this->errors[]
                                = $this->picture->getErrorMessage($res);
                        }
                    }
                }
            } elseif ($files['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                Analog::log(
                    $this->picture->getPhpErrorMessage($files['photo']['error']),
                    Analog::WARNING
                );
                $this->errors[] = $this->picture->getPhpErrorMessage(
                    $files['photo']['error']
                );
            }
        }
        $this->dynamicsFiles($files);

        if (count($this->errors) > 0) {
            Analog::log(
                'Some errors has been thew attempting to edit/store a member files' . "\n" .
                print_r($this->errors, true),
                Analog::ERROR
            );
            return $this->errors;
        } else {
            return true;
        }
    }

    /**
     * Set member as duplicate
     *
     * @return void
     */
    public function setDuplicate(): void
    {
        //mark as duplicated
        $this->_duplicate = true;
        $infos = $this->_others_infos_admin;
        $this->_others_infos_admin = str_replace(
            ['%name', '%id'],
            [$this->sname, $this->_id],
            _T('Duplicated from %name (%id)')
        );
        if (!empty($infos)) {
            $this->_others_infos_admin .= "\n" . $infos;
        }
        //drop id_adh
        $this->_id = null;
        //drop email, must be unique
        $this->_email = null;
        //drop creation date
        $this->_creation_date = date("Y-m-d");
        //drop login
        $this->_login = null;
        //reset picture
        $this->_picture = new Picture();
        //remove birthdate
        $this->_birthdate = null;
        //remove surname
        $this->_surname = null;
        //not admin
        $this->_admin = false;
        //not due free
        $this->_due_free = false;
    }

    /**
     * Get current errors
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get user groups
     *
     * @return array
     */
    public function getGroups(): array
    {
        if (!$this->isDepEnabled('groups')) {
            $this->loadGroups();
        }
        return $this->_groups;
    }

    /**
     * Get user managed groups
     *
     * @return array
     */
    public function getManagedGroups(): array
    {
        if (!$this->isDepEnabled('groups')) {
            $this->loadGroups();
        }
        return $this->_managed_groups;
    }

    /**
     * Can current logged-in user create member
     *
     * @param Login $login Login instance
     *
     * @return boolean
     */
    public function canCreate(Login $login): bool
    {
        global $preferences;

        if ($this->id && $login->id == $this->id || $login->isAdmin() || $login->isStaff()) {
            return true;
        }

        if ($preferences->pref_bool_groupsmanagers_create_member && $login->isGroupManager()) {
            return true;
        }

        if ($preferences->pref_bool_create_member && $login->isLogged()) {
            return true;
        }

        return false;
    }

    /**
     * Can current logged-in user edit member
     *
     * @param Login $login Login instance
     *
     * @return boolean
     */
    public function canEdit(Login $login): bool
    {
        global $preferences;

        //admin and staff users can edit, as well as member itself
        if ($this->id && $login->id == $this->id || $login->isAdmin() || $login->isStaff()) {
            return true;
        }

        //parent can edit their child cards
        if ($this->hasParent() && $this->parent_id === $login->id) {
            return true;
        }

        //group managers can edit members of groups they manage when pref is on
        if ($preferences->pref_bool_groupsmanagers_edit_member && $login->isGroupManager()) {
            foreach ($this->getGroups() as $g) {
                if ($login->isGroupManager($g->getId())) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Can current logged-in user display member
     *
     * @param Login $login Login instance
     *
     * @return boolean
     */
    public function canShow(Login $login): bool
    {
        //group managers can show members of groups they manage
        if ($login->isGroupManager()) {
            foreach ($this->getGroups() as $g) {
                if ($login->isGroupManager($g->getId())) {
                    return true;
                }
            }
        }

        return $this->canEdit($login);
    }

    /**
     * Are we currently duplicated a member?
     *
     * @return boolean
     */
    public function isDuplicate(): bool
    {
        return $this->_duplicate;
    }

    /**
     * Flag creation mail sending
     *
     * @param boolean $send True (default) to send creation email
     *
     * @return Adherent
     */
    public function setSendmail(bool $send = true): self
    {
        $this->sendmail = $send;
        return $this;
    }

    /**
     * Should we send administrative emails to member?
     *
     * @return boolean
     */
    public function sendEMail(): bool
    {
        return $this->sendmail;
    }

    /**
     * Set member parent
     *
     * @param integer $id Parent identifier
     *
     * @return $this
     */
    public function setParent(int $id): self
    {
        $this->_parent = $id;
        $this->loadParent();
        return $this;
    }

    /**
     * Reset dependencies to load
     *
     * @return $this
     */
    public function disableAllDeps(): self
    {
        foreach ($this->_deps as &$dep) {
            $dep = false;
        }
        return $this;
    }

    /**
     * Enable all dependencies to load
     *
     * @return $this
     */
    public function enableAllDeps(): self
    {
        foreach ($this->_deps as &$dep) {
            $dep = true;
        }
        return $this;
    }

    /**
     * Enable a load dependency
     *
     * @param string $name Dependency name
     *
     * @return $this
     */
    public function enableDep(string $name): self
    {
        if (!isset($this->_deps[$name])) {
            Analog::log(
                'dependency ' . $name . ' does not exists!',
                Analog::WARNING
            );
        } else {
            $this->_deps[$name] = true;
        }

        return $this;
    }

    /**
     * Enable a load dependency
     *
     * @param string $name Dependency name
     *
     * @return $this
     */
    public function disableDep(string $name): self
    {
        if (!isset($this->_deps[$name])) {
            Analog::log(
                'dependency ' . $name . ' does not exists!',
                Analog::WARNING
            );
        } else {
            $this->_deps[$name] = false;
        }

        return $this;
    }

    /**
     * Is load dependency enabled?
     *
     * @param string $name Dependency name
     *
     * @return boolean
     */
    protected function isDepEnabled(string $name): bool
    {
        return $this->_deps[$name];
    }
}
