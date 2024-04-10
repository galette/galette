<?php

/**
 * Copyright © 2003-2024 The Galette Team
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
 */

namespace Galette\Entity;

use ArrayObject;
use DateInterval;
use DateTime;
use Galette\Core\I18n;
use Galette\Events\GaletteEvent;
use Galette\Features\HasEvent;
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
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property ?integer $id
 * @property integer|Title|null $title Either a title id or an instance of Title
 * @property string $stitle Title label
 * @property string $company_name
 * @property string $name
 * @property ?string $surname
 * @property string $nickname
 * @property ?string $birthdate Localized birthdate
 * @property ?string $rbirthdate Raw birthdate
 * @property string $birth_place
 * @property integer $gender
 * @property string $sgender Gender label
 * @property string $job
 * @property string $language
 * @property integer $status
 * @property string $sstatus Status label
 * @property ?string $address
 * @property ?string $zipcode
 * @property ?string $town
 * @property ?string $country
 * @property string $phone
 * @property string $gsm
 * @property ?string $email
 * @property string $gnupgid
 * @property string $fingerprint
 * @property ?string $login
 * @property string $creation_date Localized creation date
 * @property string $modification_date Localized modification date
 * @property string $due_date Localized due date
 * @property string $others_infos
 * @property string $others_infos_admin
 * @property Picture $picture
 * @property array $groups
 * @property array $managed_groups
 * @property integer|Adherent|null $parent Parent id if parent dep is not loaded, Adherent instance otherwise
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
 * @property integer $days_remaining
 * @property-read integer $parent_id
 * @property Social $social Social networks/Contact
 * @property string $number Member number
 * @property-read bool $self_adh
 * @property ?string $region
 */
class Adherent
{
    use Dynamics;
    use Socials;
    use HasEvent;

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

    private ?int $id;
    //Identity
    private Title|string|null $title = null;
    private ?string $company_name;
    private ?string $name;
    private ?string $surname;
    private ?string $nickname;
    private ?string $birthdate;
    private ?string $birth_place;
    private int $gender;
    private string $job;
    private string $language;
    private bool $active;
    private int $status;
    //Contact information
    private ?string $address = null;
    private ?string $zipcode = null;
    private ?string $town = null;
    private ?string $country = null;
    private ?string $phone;
    private ?string $gsm;
    private ?string $email;
    private ?string $gnupgid;
    private ?string $fingerprint;
    //Galette relative information
    private bool $appears_in_list;
    private bool $admin;
    private bool $staff = false;
    private bool $due_free;
    private ?string $login;
    private ?string $password;
    private string $creation_date;
    private string $modification_date;
    private ?string $due_date;
    private string $others_infos;
    private ?string $others_infos_admin;
    private ?Picture $picture = null;
    private int $oldness;
    private ?int $days_remaining = null;
    /** @var array<int, Group> */
    private array $groups = [];
    /** @var array<int, Group> */
    private array $managed_groups = [];
    private int|Adherent|null $parent;
    /** @var array<int, Adherent>|null */
    private ?array $children = [];
    private bool $duplicate = false;
    /** @var array<int,Social> */
    private array $socials;
    private ?string $number = null;
    private ?string $region = null;

    private string $row_classes;

    private bool $self_adh = false;

    private Db $zdb;
    private Preferences $preferences;
    /** @var array<string, mixed> */
    private array $fields;
    private History $history;
    private int $due_status = Contribution::STATUS_UNKNOWN;

    /** @var array<string> */
    private array $parent_fields = [
        'adresse_adh',
        'cp_adh',
        'ville_adh',
        'email_adh'
    ];

    /** @var array<string> */
    private array $errors = [];

    private bool $sendmail = false;

    /**
     * Default constructor
     *
     * @param Db                                              $zdb  Database instance
     * @param ArrayObject<string, int|string>|string|int|null $args Either a ResultSet row, its id or its
     *                                                              login or its email for to load s specific
     *                                                              member, or null to just instantiate object
     * @param false|array<string,bool>|null                   $deps Dependencies configuration, see Adherent::$_deps
     */
    public function __construct(Db $zdb, ArrayObject|int|string $args = null, array|false $deps = null)
    {
        /** @var I18n $i18n */
        global $i18n;

        $this->zdb = $zdb;

        if ($deps !== null) {
            if (is_array($deps)) {
                $this->setDeps($deps);
            } elseif ($deps === false) {
                //no dependencies
                $this->disableAllDeps();
            } else {
                Analog::log(
                    '$deps should be an array, ' . gettype($deps) . ' given!',
                    Analog::WARNING
                );
            }
        }

        $this
            ->withAddEvent()
            ->withEditEvent()
            ->withoutDeleteEvent()
            ->activateEvents();

        if ($args == null || is_int($args)) {
            if (is_int($args) && $args > 0) {
                $this->load($args);
            } else {
                $this->active = true;
                $this->language = $i18n->getID();
                $this->creation_date = date("Y-m-d");
                $this->status = $this->getDefaultStatus();
                $this->title = null;
                $this->gender = self::NC;
                $gp = new Password($this->zdb);
                $this->password = $gp->makeRandomPassword();
                $this->picture = new Picture();
                $this->admin = false;
                $this->staff = false;
                $this->due_free = false;
                $this->appears_in_list = false;
                $this->parent = null;

                if ($this->deps['dynamics'] === true) {
                    $this->loadDynamicFields();
                }
            }
        } elseif ($args instanceof ArrayObject) {
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

            /** @var ArrayObject<string, int|string> $result */
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
                /** @var ArrayObject<string, int|string> $result */
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
     * @param ArrayObject<string, int|string> $r the resultset row
     *
     * @return void
     */
    private function loadFromRS(ArrayObject $r): void
    {
        $this->self_adh = false;
        $this->id = $r->id_adh;
        //Identity
        if ($r->titre_adh !== null) {
            $this->title = new Title((int)$r->titre_adh);
        }
        $this->company_name = $r->societe_adh;
        $this->name = $r->nom_adh;
        $this->surname = $r->prenom_adh;
        $this->nickname = $r->pseudo_adh;
        if ($r->ddn_adh != '1901-01-01') {
            $this->birthdate = $r->ddn_adh;
        }
        $this->birth_place = $r->lieu_naissance;
        $this->gender = (int)$r->sexe_adh;
        $this->job = $r->prof_adh;
        $this->language = $r->pref_lang;
        $this->active = $r->activite_adh == 1;
        $this->status = (int)$r->id_statut;
        //Contact information
        $this->address = $r->adresse_adh;
        $this->zipcode = $r->cp_adh;
        $this->town = $r->ville_adh;
        $this->region = $r->region_adh;
        $this->country = $r->pays_adh;
        $this->phone = $r->tel_adh;
        $this->gsm = $r->gsm_adh;
        $this->email = $r->email_adh;
        $this->gnupgid = $r->gpgid;
        $this->fingerprint = $r->fingerprint;
        //Galette relative information
        $this->appears_in_list = $r->bool_display_info == 1;
        $this->admin = $r->bool_admin_adh == 1;
        if (
            isset($r->priorite_statut)
            && $r->priorite_statut < Members::NON_STAFF_MEMBERS
        ) {
            $this->staff = true;
        }
        $this->due_free = $r->bool_exempt_adh == 1;
        $this->login = $r->login_adh;
        $this->password = $r->mdp_adh;
        $this->creation_date = $r->date_crea_adh;
        if ($r->date_modif_adh != '1901-01-01') {
            $this->modification_date = $r->date_modif_adh;
        } else {
            $this->modification_date = $this->creation_date;
        }
        $this->due_date = $r->date_echeance;
        $this->others_infos = $r->info_public_adh;
        $this->others_infos_admin = $r->info_adh;
        $this->number = $r->num_adh;

        if ($r->parent_id !== null) {
            $this->parent = (int)$r->parent_id;
            if ($this->deps['parent'] === true) {
                $this->loadParent();
            }
        }

        if ($this->deps['children'] === true) {
            $this->loadChildren();
        }

        if ($this->deps['picture'] === true) {
            $this->picture = new Picture($this->id);
        }

        if ($this->deps['groups'] === true) {
            $this->loadGroups();
        }

        if ($this->deps['dues'] === true) {
            $this->checkDues();
        }

        if ($this->deps['dynamics'] === true) {
            $this->loadDynamicFields();
        }

        if ($this->deps['socials'] === true) {
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
        if (isset($this->parent) && !$this->parent instanceof Adherent) {
            $deps = array_fill_keys(array_keys($this->deps), false);
            $this->parent = new Adherent($this->zdb, (int)$this->parent, $deps);
        }
    }

    /**
     * Load member children
     *
     * @return void
     */
    private function loadChildren(): void
    {
        $this->children = array();
        try {
            $id = self::PK;
            $select = $this->zdb->select(self::TABLE);
            $select->columns(
                array($id)
            )->where(['parent_id' => $this->id]);

            $results = $this->zdb->execute($select);

            if ($results->count() > 0) {
                foreach ($results as $row) {
                    $deps = $this->deps;
                    $deps['children'] = false;
                    $deps['parent'] = false;
                    $this->children[] = new Adherent($this->zdb, (int)$row->$id, $deps);
                }
            }
        } catch (Throwable $e) {
            Analog::log(
                'Cannot load children for member #' . $this->id . ' | ' .
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
        $this->groups = Groups::loadGroups($this->id);
        $this->managed_groups = Groups::loadManagedGroups($this->id);
    }

    /**
     * Load member social network/contact information
     *
     * @return void
     */
    public function loadSocials(): void
    {
        $this->socials = Social::getListForMember($this->id);
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
        $now = new DateTime();
        $this->oldness = $now->diff(
            new DateTime($this->creation_date)
        )->days;

        $this->row_classes = '';
        if ($this->isDueFree()) {
            //no fee required, we don't care about dates
            $this->row_classes .= ' cotis-exempt';
            $this->due_status = Contribution::STATUS_DUEFREE;
        } else {
            //ok, fee is required. Let's check the dates
            if ($this->due_date == '') {
                $this->row_classes .= ' cotis-never';
                $this->due_status = Contribution::STATUS_NEVER;
            } else {
                // To count the days remaining, the next begin date is required.
                $due_date = new DateTime($this->due_date);
                $next_begin_date = clone $due_date;
                $next_begin_date->add(new DateInterval('P1D'));
                $date_diff = $now->diff($next_begin_date);
                $this->days_remaining = $date_diff->days;
                // Active
                if ($date_diff->invert == 0 && $date_diff->days >= 0) {
                    $this->days_remaining = $date_diff->days;
                    if ($this->days_remaining <= 30) {
                        if ($date_diff->days == 0) {
                            $this->row_classes .= ' cotis-lastday';
                        }
                        $this->row_classes .= ' cotis-soon';
                        $this->due_status = Contribution::STATUS_IMPENDING;
                    } else {
                        $this->row_classes .= ' cotis-ok';
                        $this->due_status = Contribution::STATUS_UPTODATE;
                    }
                // Expired
                } elseif ($date_diff->invert == 1 && $date_diff->days >= 0) {
                    $this->days_remaining = $date_diff->days;
                    //check if member is still active
                    if ($this->isActive()) {
                        $this->row_classes .= ' cotis-late';
                        $this->due_status = Contribution::STATUS_LATE;
                    } else {
                        $this->row_classes .= ' cotis-old';
                        $this->due_status = Contribution::STATUS_OLD;
                    }
                }
            }
        }

        if (!$this->isActive()) {
            //anyway, if a member is no longer active, its due status is old.
            $this->due_status = Contribution::STATUS_OLD;
        }

        if ($this->due_status === Contribution::STATUS_UNKNOWN) {
            throw new \RuntimeException(
                'Unable to determine due status for member #' . $this->id
            );
        }
    }

    /**
     * Is member admin?
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->admin;
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
     * Is member freed of dues?
     *
     * @return bool
     */
    public function isDueFree(): bool
    {
        return $this->due_free;
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

        foreach ($this->groups as $g) {
            if ($g->getName() == $group_name) {
                return true;
            }
        }
        return false;
    }

    /**
     * Is member manager of specified group?
     *
     * @param ?string $group_name Group name
     *
     * @return boolean
     */
    public function isGroupManager(?string $group_name): bool
    {
        if (!$this->isDepEnabled('groups')) {
            $this->loadGroups();
        }

        if ($group_name !== null) {
            foreach ($this->managed_groups as $mg) {
                if ($mg->getName() == $group_name) {
                    return true;
                }
            }
            return false;
        } else {
            if ($this->isAdmin() || $this->isStaff()) {
                return true;
            } else {
                return count($this->managed_groups) > 0;
            }
        }
    }

    /**
     * Does current member represents a company?
     *
     * @return boolean
     */
    public function isCompany(): bool
    {
        return trim($this->company_name ?? '') != '';
    }

    /**
     * Is current member a man?
     *
     * @return boolean
     */
    public function isMan(): bool
    {
        return $this->gender === self::MAN;
    }

    /**
     * Is current member a woman?
     *
     * @return boolean
     */
    public function isWoman(): bool
    {
        return $this->gender === self::WOMAN;
    }


    /**
     * Can member appears in public members list?
     *
     * @return bool
     */
    public function appearsInMembersList(): bool
    {
        return $this->appears_in_list;
    }

    /**
     * Is member active?
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Does member have uploaded a picture?
     *
     * @return bool
     */
    public function hasPicture(): bool
    {
        return $this->picture->hasPicture();
    }

    /**
     * Does member have a parent?
     *
     * @return bool
     */
    public function hasParent(): bool
    {
        return !empty($this->parent);
    }

    /**
     * Does member have children?
     *
     * @return bool
     */
    public function hasChildren(): bool
    {
        if (!isset($this->children) || $this->children === null) {
            if ($this->id) {
                Analog::log(
                    'Children has not been loaded!',
                    Analog::WARNING
                );
            }
            return false;
        } else {
            return count($this->children) > 0;
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
            $strclass .= $this->row_classes ?? '';
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
        $now = new DateTime();
        // To count the days remaining, the next begin date is required.
        if (!isset($this->due_date)) {
            $this->due_date = $now->format('Y-m-d');
            $never_contributed = true;
        }
        $due_date = new DateTime($this->due_date);
        $next_begin_date = clone $due_date;
        $next_begin_date->add(new DateInterval('P1D'));
        $date_diff = $now->diff($next_begin_date);
        if ($this->isDueFree()) {
            $ret = _T("Freed of dues");
        } elseif ($never_contributed === true) {
            if ($this->active) {
                $patterns = array('/%days/', '/%date/');
                $cdate = new DateTime($this->creation_date);
                if (!isset($this->oldness)) {
                    $this->checkDues();
                }
                $replace = array(
                    $this->oldness,
                    $cdate->format(__("Y-m-d"))
                );

                $ret = preg_replace(
                    $patterns,
                    $replace,
                    _T("Never contributed: Registered %days days ago (since %date)")
                );
            } else {
                $ret = _T("Never contributed");
            }
        // Last active or first expired day
        } elseif ($this->days_remaining === 0) {
            if ($date_diff->invert == 0) {
                $ret = _T("Last day!");
            } else {
                $ret = _T("Late since today!");
            }
        // Active
        } elseif ($date_diff->invert == 0 && $this->days_remaining > 0) {
            $patterns = array('/%days/', '/%date/');
            $replace = array(
                $this->days_remaining,
                $due_date->format(__("Y-m-d"))
            );
            $ret = preg_replace(
                $patterns,
                $replace,
                _T("%days days remaining (ending on %date)")
            );
        // Expired
        } elseif ($date_diff->invert == 1 && $this->days_remaining > 0) {
            $patterns = array('/%days/', '/%date/');
            $replace = array(
                // We need the number of days expired, not the number of days remaining.
                $this->days_remaining + 1,
                $due_date->format(__("Y-m-d"))
            );
            if ($this->active) {
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
     * Is member a sponsor for current period?
     *
     * @return bool
     */
    public function isSponsor(): bool
    {
        global $preferences;

        $date_now = new DateTime();

        //calculate begin date of period
        if ($preferences->pref_beg_membership != '') { //classical membership date + 1 year
            list($j, $m) = explode('/', $preferences->pref_beg_membership);
            $sdate = new DateTime($date_now->format('Y') . '-' . $m . '-' . $j);
        } elseif ($preferences->pref_membership_ext != '') { //classical membership date + N months
            $dext = new DateInterval('P' . $preferences->pref_membership_ext . 'M');
            $sdate = $date_now->sub($dext);
        } else {
            throw new \RuntimeException(
                'Unable to define sponsoring start date; none of pref_beg_membership nor pref_membership_ext are defined!'
            );
        }

        //date_debut_cotis because member can ask for his donation ot be recorded for next year
        $select = $this->zdb->select(Contribution::TABLE, 'c');
        $select
            ->columns(
                array(
                    'count' => new Expression('COUNT(*)')
                )
            )
            ->join(
                array(
                    'ct' => PREFIX_DB . ContributionsTypes::TABLE),
                'c.' . ContributionsTypes::PK . '=ct.' . ContributionsTypes::PK,
                array()
            )
            ->where(
                [
                    'id_adh' => $this->id,
                    'cotis_extension' => 0 //donations only
                ]
            )
            ->where->greaterThanOrEqualTo(
                'date_debut_cotis',
                $sdate->format('Y-m-d')
            );

        $results = $this->zdb->execute($select);
        $result = $results->current();

        return (int)$result->count > 0;
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
     * @param ?string       $name    Member name
     * @param ?string       $surname Member surname
     * @param false|Title   $title   Member title to show or false
     * @param false|integer $id      Member id to display or false
     * @param false|string  $nick    Member nickname to display or false
     *
     * @return string
     */
    public static function getNameWithCase(
        ?string $name,
        ?string $surname,
        Title|bool $title = false,
        int|bool $id = false,
        string|bool $nick = false
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
     * @return array<string>
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
        $this->self_adh = true;
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
            //member is due free, he's up-to-date.
            return true;
        } else {
            //let's check from due date, if present
            if (!isset($this->due_date)) {
                return false;
            } else {
                $due_date = new DateTime($this->due_date);
                $now = new DateTime();
                $now->setTime(0, 0, 0);
                return $due_date >= $now;
            }
        }
    }

    /**
     * Set dependencies
     *
     * @param Preferences         $preferences Preferences instance
     * @param array<string,mixed> $fields      Members fields configuration
     * @param History             $history     History instance
     *
     * @return void
     */
    public function setDependencies(
        Preferences $preferences,
        array $fields,
        History $history
    ): void {
        $this->preferences = $preferences;
        $this->fields = $fields;
        $this->history = $history;
    }

    /**
     * Check posted values validity
     *
     * @param array<string,mixed> $values   All values to check, basically the $_POST array
     *                                      after sending the form
     * @param array<string,bool>  $required Array of required fields
     * @param array<string>       $disabled Array of disabled fields
     *
     * @return true|array<string>
     */
    public function check(array $values, array $required, array $disabled): bool|array
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
            && !empty($this->id)
            && isset($values['parent_id'])
        ) {
            unset($values['parent_id']);
        }

        if (isset($values['duplicate'])) {
            //if we're duplicating, keep a trace (if an error occurs)
            $this->duplicate = true;
        }

        foreach ($fields as $key) {
            //first, let's sanitize values
            $key = strtolower($key);
            $prop = $this->fields[$key]['propname'];

            if (isset($values[$key])) {
                $value = $values[$key];
                if ($value !== true && $value !== false) {
                    //@phpstan-ignore-next-line
                    $value = trim($value ?? '');
                }
            } elseif (empty($this->id)) {
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
                if ($prop != 'password' || isset($values['mdp_adh']) && isset($values['mdp_adh2'])) {
                    $value = $this->$prop;
                } else {
                    $value = null;
                }
            }

            // if the field is enabled, check it
            if (!in_array($key, $disabled)) {
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
                } elseif (empty($this->id)) {
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
            $prop = $this->fields[$key]['propname'];

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
            $this->parent = null;
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
     * @param string              $field  Field name
     * @param mixed               $value  Value we want to set
     * @param array<string,mixed> $values All values, for some references
     *
     * @return void
     */
    public function validate(string $field, $value, array $values): void
    {
        global $preferences;

        $prop = $this->fields[$field]['propname'];

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
                    $d = DateTime::createFromFormat(__("Y-m-d"), $value);
                    if ($d === false) {
                        //try with non localized date
                        $d = DateTime::createFromFormat("Y-m-d", $value);
                        if ($d === false) {
                            throw new \Exception('Incorrect format');
                        }
                    }

                    if ($field === 'ddn_adh') {
                        $now = new DateTime();
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
                                (string)($years * -1),
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
                    $this->errors[] = sprintf(
                        //TRANS %1$s is the expected dat format, %2$s the field label
                        _T('- Wrong date format (%1$s) for %2$s!'),
                        __("Y-m-d"),
                        $this->getFieldLabel($field)
                    );
                }
                break;
            case 'titre_adh':
                if ($value !== '') {
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

                try {
                    $select = $this->zdb->select(self::TABLE);
                    $select->columns(
                        array(self::PK)
                    )->where(array('email_adh' => $value));
                    if (!empty($this->id)) {
                        $select->where->notEqualTo(
                            self::PK,
                            $this->id
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
                break;
            case 'login_adh':
                /** FIXME: add a preference for login length */
                if (strlen($value) < 2) {
                    $this->errors[] = str_replace(
                        '%i',
                        '2',
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
                            if (!empty($this->id)) {
                                $select->where->notEqualTo(
                                    self::PK,
                                    $this->id
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
                    $this->self_adh !== true
                    && (!isset($values['mdp_adh2'])
                    || $values['mdp_adh2'] != $value)
                ) {
                    $this->errors[] = _T("- The passwords don't match!");
                } elseif (
                    $this->self_adh === true
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
                $pid = ($value instanceof Adherent) ? (int)$value->id : (int)$value;
                if ($pid === $this->id) {
                    $this->errors[] = _T("A member cannot be its own parent!");
                    $this->$prop = null;
                } else {
                    $this->$prop = $pid;
                    $this->loadParent();
                }
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
                $this->parent = $login->id;
            }
        }

        try {
            $values = array();
            $fields = self::getDbFields($this->zdb);

            foreach ($fields as $field) {
                if (
                    $field !== 'date_modif_adh'
                    || empty($this->id)
                ) {
                    $prop = $this->fields[$field]['propname'];
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
                        if (!isset($this->parent)) {
                            $values['parent_id'] = new Expression('NULL');
                        } elseif ($this->parent instanceof Adherent) {
                            $values['parent_id'] = $this->parent->id;
                        } else {
                            $values['parent_id'] = $this->parent;
                        }
                    } else {
                        $values[$field] = $this->$prop;
                    }
                }
            }

            //an empty value will cause date to be set to 1901-01-01, a null
            //will result in 0000-00-00. We want a database NULL value here.
            if (!$this->birthdate) {
                $values['ddn_adh'] = new Expression('NULL');
            }
            if (!$this->due_date) {
                $values['date_echeance'] = new Expression('NULL');
            }

            if ($this->title instanceof Title) {
                $values['titre_adh'] = $this->title->id;
            } else {
                $values['titre_adh'] = new Expression('NULL');
            }

            if (!$this->parent) {
                $values['parent_id'] = new Expression('NULL');
            }

            if (!$this->number) {
                $values['num_adh'] = new Expression('NULL');
            }

            //fields that cannot be null
            $notnull = [
                'surname'  => 'prenom_adh',
                'nickname' => 'pseudo_adh',
                'address'  => 'adresse_adh',
                'zipcode'  => 'cp_adh',
                'town'     => 'ville_adh',
                'region'   => 'region_adh'
            ];
            foreach ($notnull as $prop => $field) {
                if (!isset($this->$prop) || $this->$prop === null) {
                    $values[$field] = '';
                }
            }

            if (empty($this->id)) {
                //we're inserting a new member
                unset($values[self::PK]);
                //set modification date
                $this->modification_date = date('Y-m-d');
                $values['date_modif_adh'] = $this->modification_date;

                $insert = $this->zdb->insert(self::TABLE);
                $insert->values($values);
                $add = $this->zdb->execute($insert);
                if ($add->count() > 0) {
                    $this->id = $this->zdb->getLastGeneratedValue($this);
                    $this->picture = new Picture($this->id);
                    // logging
                    if ($this->self_adh) {
                        $hist->add(
                            _T("Self_subscription as a member: ") .
                            $this->getNameWithCase($this->name, $this->surname),
                            $this->sname
                        );
                    } else {
                        $hist->add(
                            _T("Member card added"),
                            $this->sname
                        );
                    }

                    $event = $this->getAddEventName();
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
                    $due_date = Contribution::getDueDate($this->zdb, $this->id);
                    if ($due_date) {
                        $values['date_echeance'] = $due_date;
                    }
                }

                if (!$this->password) {
                    unset($values['mdp_adh']);
                }

                $update = $this->zdb->update(self::TABLE);
                $update->set($values);
                $update->where([self::PK => $this->id]);

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
                $event = $this->getEditEventName();
            }

            //dynamic fields
            $this->dynamicsStore();
            $this->storeSocials($this->id);

            //send event at the end of process, once all has been stored
            if ($event !== null && $this->areEventsEnabled()) {
                $emitter->dispatch(new GaletteEvent($event, $this));
            }
            return true;
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
            )->where([self::PK => $this->id]);

            $this->zdb->execute($update);
            $this->modification_date = $modif_date;
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
            switch ($name) {
                case 'sadmin':
                    return (($this->isAdmin()) ? _T("Yes") : _T("No"));
                case 'sdue_free':
                    return (($this->isDueFree()) ? _T("Yes") : _T("No"));
                case 'sappears_in_list':
                    return (($this->appearsInMembersList()) ? _T("Yes") : _T("No"));
                case 'sstaff':
                    return (($this->isStaff()) ? _T("Yes") : _T("No"));
                case 'sactive':
                    return (($this->isActive()) ? _T("Active") : _T("Inactive"));
                case 'stitle':
                    if (isset($this->title) && $this->title instanceof Title) {
                        return $this->title->tshort;
                    } else {
                        return null;
                    }
                case 'sstatus':
                    $status = new Status($this->zdb);
                    return $status->getLabel($this->status);
                case 'sfullname':
                    return $this->getNameWithCase(
                        $this->name,
                        $this->surname,
                        ($this->title ?? false)
                    );
                case 'saddress':
                    return $this->address;
                case 'sname':
                    return $this->getNameWithCase($this->name, $this->surname);
                case 'rbirthdate':
                    return $this->birthdate ?? null;
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
            $values = Social::getListForMember($this->id, $name);
            return $values[0] ?? null;
        }

        switch ($name) {
            case 'id':
            case 'id_statut':
                if (isset($this->$name) && $this->$name !== null) {
                    return (int)$this->$name;
                } else {
                    return null;
                }
            case 'address':
                return $this->$name ?? '';
            case 'birthdate':
            case 'creation_date':
            case 'modification_date':
            case 'due_date':
                if (isset($this->$name) && $this->$name != '') {
                    try {
                        $d = new DateTime($this->$name);
                        return $d->format(__("Y-m-d"));
                    } catch (Throwable $e) {
                        //oops, we've got a bad date :/
                        Analog::log(
                            'Bad date (' . $this->$name . ') | ' .
                            $e->getMessage(),
                            Analog::INFO
                        );
                        return $this->$name;
                    }
                }
                return null;
            case 'parent_id':
                return ($this->parent instanceof Adherent) ? $this->parent->id : (int)$this->parent;
            default:
                if (!property_exists($this, $name)) {
                    Analog::log(
                        "Unknown property '$name'",
                        Analog::WARNING
                    );
                    return null;
                } else {
                    if (isset($this->$name)) {
                        return $this->$name;
                    }
                    return null;
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
    public function __isset(string $name): bool
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
                return property_exists($this, $name);
        }
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
        $email = $this->email;
        if (empty($email) && $this->hasParent()) {
            $this->loadParent();
            $email = $this->parent->email;
        }

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
        $address = $this->address;
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
        $address = $this->address;
        $zip = $this->zipcode;
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
        $address = $this->address;
        $town = $this->town;
        if (empty($address) && $this->hasParent()) {
            $this->loadParent();
            $town = $this->parent->town;
        }

        return $town ?? '';
    }

    /**
     * Get member region.
     * If member does not have an address, but is attached to another member, we'll take information from its parent.
     *
     * @return string
     */
    public function getRegion(): string
    {
        $address = $this->address;
        $region = $this->region;
        if (empty($address) && $this->hasParent()) {
            $this->loadParent();
            $region = $this->parent->region;
        }

        return $region ?? '';
    }

    /**
     * Get member country.
     * If member does not have an address, but is attached to another member, we'll take information from its parent.
     *
     * @return string
     */
    public function getCountry(): string
    {
        $address = $this->address;
        $country = $this->country;
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
        if (!isset($this->birthdate) && $this->birthdate == null) {
            return '';
        }

        $d = DateTime::createFromFormat('Y-m-d', $this->birthdate);
        if ($d === false) {
            Analog::log(
                'Invalid birthdate: ' . $this->birthdate,
                Analog::ERROR
            );
            return '';
        }

        return str_replace(
            '%age',
            (string)$d->diff(new DateTime())->y,
            _T(' (%age years old)')
        );
    }

    /**
     * Get parent inherited fields
     *
     * @return array<string>
     */
    public function getParentFields(): array
    {
        return $this->parent_fields;
    }

    /**
     * Handle files (photo and dynamics files)
     *
     * @param array<string,mixed>  $files    Files sent
     * @param ?array<string,mixed> $cropping Cropping properties
     *
     * @return array<string>|true
     */
    public function handleFiles(array $files, array $cropping = null): array|bool
    {
        $this->errors = [];
        // picture upload
        if (isset($files['photo'])) {
            if ($files['photo']['error'] === UPLOAD_ERR_OK) {
                if ($files['photo']['tmp_name'] != '') {
                    if (is_uploaded_file($files['photo']['tmp_name'])) {
                        if ($this->preferences->pref_force_picture_ratio == 1 && isset($cropping)) {
                            $res = $this->picture->store($files['photo'], false, $cropping);
                        } else {
                            $res = $this->picture->store($files['photo']);
                        }
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
        $this->duplicate = true;
        $infos = $this->others_infos_admin;
        $this->others_infos_admin = str_replace(
            ['%name', '%id'],
            [$this->sname, $this->id],
            _T('Duplicated from %name (%id)')
        );
        if (!empty($infos)) {
            $this->others_infos_admin .= "\n" . $infos;
        }
        //drop id_adh
        $this->id = null;
        //drop email, must be unique
        $this->email = null;
        //drop creation date
        $this->creation_date = date("Y-m-d");
        //drop login
        $this->login = null;
        //reset picture
        $this->picture = new Picture();
        //remove birthdate
        $this->birthdate = null;
        //remove surname
        $this->surname = null;
        //not admin
        $this->admin = false;
        //not due free
        $this->due_free = false;
    }

    /**
     * Get current errors
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get user groups
     *
     * @return array<int, Group>
     */
    public function getGroups(): array
    {
        if (!$this->isDepEnabled('groups')) {
            $this->loadGroups();
        }
        return $this->groups;
    }

    /**
     * Get user managed groups
     *
     * @return array<int, Group>
     */
    public function getManagedGroups(): array
    {
        if (!$this->isDepEnabled('groups')) {
            $this->loadGroups();
        }
        return $this->managed_groups;
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

        if (isset($this->id) && $login->id == $this->id || $login->isAdmin() || $login->isStaff()) {
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
        if (isset($this->id) && $login->id == $this->id || $login->isAdmin() || $login->isStaff()) {
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
        return $this->duplicate;
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
        $this->parent = $id;
        $this->loadParent();
        return $this;
    }

    /**
     * Get current due status
     *
     * @return int
     */
    public function getDueStatus(): int
    {
        return $this->due_status;
    }

    /**
     * Get prefix for events
     *
     * @return string
     */
    protected function getEventsPrefix(): string
    {
        return 'member';
    }
}
