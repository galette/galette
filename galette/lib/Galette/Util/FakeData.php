<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Generates fake data as example
 *
 * PHP version 5
 *
 * Copyright © 2017-2018 The Galette Team
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
 * @category  Util
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017-2018 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9
 */

namespace Galette\Util;

use Analog\Analog;
use Galette\Core\Db;
use Galette\Core\I18n;
use Galette\Core\Preferences;
use Galette\Core\History;
use Galette\Core\Login;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Repository\Titles;
use Galette\Entity\Status;
use Galette\Entity\ContributionsTypes;
use Galette\Entity\Group;
use Galette\Entity\Transaction;
use Galette\Entity\PaymentType;

/**
 * Generate random data
 *
 * @category  Util
 * @name      FakeData
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @see       https://github.com/fzaninotto/Faker
 * @since     Available since 0.9dev - 2017-02-20
 */
class FakeData
{
    public const DEFAULT_NB_MEMBERS       = 20;
    public const DEFAULT_NB_CONTRIB       = 5;
    public const DEFAULT_NB_GROUPS        = 5;
    public const DEFAULT_NB_TRANSACTIONS  = 2;
    public const DEFAULT_PHOTOS           = false;

    protected $preferences;
    protected $member_fields;
    protected $history;
    protected $login;

    protected $zdb;
    protected $i18n;
    protected $faker;
    private $report = [
        'success'   => [],
        'errors'    => [],
        'warnings'  => []
    ];

    protected $groups       = [];
    protected $mids         = [];
    protected $transactions = [];
    protected $titles       = [];
    protected $status;
    protected $contrib_types;

    /**
     * @var integer
     * Number of members to generate
     */
    protected $nbmembers = self::DEFAULT_NB_MEMBERS;

    /**
     * @var boolean
     * With members photos
     */
    protected $with_photos = self::DEFAULT_PHOTOS;

    /**
     * @var integer
     * Number of groups to generate
     */
    protected $nbgroups = self::DEFAULT_NB_GROUPS;

    /**
     * @var integer
     * Max number of contributions to generate
     * for each member
     */
    protected $maxcontribs = self::DEFAULT_NB_CONTRIB;

    /**
     * @var integer
     * Number of transactions to generate
     */
    protected $nbtransactions = self::DEFAULT_NB_TRANSACTIONS;

    /**
     * @var integer
     * Seed to use for data generation (to get same data accross runs)
     */
    protected $seed;

    /**
     * Default constructor
     *
     * @param Db      $zdb      Db instance
     * @param I18n    $i18n     Current language
     * @param boolean $generate Process data generation; defaults to false
     *
     * @return void
     */
    public function __construct(Db $zdb, I18n $i18n, $generate = false)
    {
        $this->zdb = $zdb;
        $this->i18n = $i18n;
        if ($generate) {
            $this->generate();
        }
    }

    /**
     * Set seed
     *
     * @param integer $seed Seed
     *
     * @return FakeData
     */
    public function setSeed($seed)
    {
        $this->seed = $seed;
        return $this;
    }

    /**
     * Set number of members to generate
     *
     * @param integer $nb Number of members
     *
     * @return FakeData
     */
    public function setNbMembers($nb)
    {
        $this->nbmembers = (int)$nb;
        return $this;
    }

    /**
     * Set maximum number of contribution per member to generate
     *
     * @param integer $nb Number of contributions
     *
     * @return FakeData
     */
    public function setMaxContribs($nb)
    {
        $this->maxcontribs = (int)$nb;
        return $this;
    }

    /**
     * Set number of groups to generate
     *
     * @param integer $nb Number of groups
     *
     * @return FakeData
     */
    public function setNbGroups($nb)
    {
        $this->nbgroups = (int)$nb;
        return $this;
    }

    /**
     * Set number of transactions to generate
     *
     * @param integer $nb Number of transactions
     *
     * @return FakeData
     */
    public function setNbTransactions($nb)
    {
        $this->nbtransactions = (int)$nb;
        return $this;
    }

    /**
     * Set with members photos or not
     *
     * @param boolean $with With photos
     *
     * @return FakeData
     */
    public function setWithPhotos($with)
    {
        $this->with_photos = $with;
        return $this;
    }

    /**
     * Get (and create if needed) Faker instance
     *
     * @return \Faker\Factory
     */
    public function getFaker()
    {
        if ($this->faker === null) {
            $this->faker = \Faker\Factory::create($this->i18n->getID());
        }
        return $this->faker;
    }

    /**
     * Do data generation
     *
     * @return void
     */
    public function generate()
    {
        $this->getFaker();
        if ($this->seed !== null) {
            $this->faker->seed($this->seed);
        }

        $this->generateGroups($this->nbgroups);
        $this->generateMembers($this->nbmembers);
        $this->generateTransactions($this->nbtransactions);
        $this->generateContributions();
    }

    /**
     * Generate groups
     *
     * @param integer $count Number of groups to generate
     *
     * @return void
     */
    public function generateGroups($count = null)
    {
        $faker = $this->getFaker();

        $done = 0;
        $parent_group = null;

        if ($count === null) {
            $count = $this->nbgroups;
        }

        for ($i = 0; $i < $count; $i++) {
            $group = new Group();
            $group->setName($faker->unique()->lastName());
            if (count($this->groups) > 0 && $faker->boolean($chanceOfGettingTrue = 10)) {
                if ($parent_group === null) {
                    $parent_group = $faker->randomElement($this->groups);
                }
                $group->setParentGroup($parent_group->getId());
            }

            if ($group->store()) {
                $this->groups[] = $group;
                ++$done;
            }
        }

        if ($count != 0 && $done != 0) {
            if ($done === $count) {
                $this->addSuccess(
                    str_replace('%count', $count, _T("%count groups created"))
                );
            } else {
                $this->addWarning(
                    str_replace(
                        ['%count', '%done'],
                        [$count, $done],
                        _T("%count groups requested, and %done created")
                    )
                );
            }
        }
    }

    /**
     * Generate members
     *
     * @param integer $count Number of members to generate
     *
     * @return void
     */
    public function generateMembers($count = null)
    {
        $faker = $this->getFaker();
        $done = 0;
        $photos_done = 0;

        if ($count === null) {
            $count = $this->nbmembers;
        }

        for ($i = 0; $i < $count; $i++) {
            $data = $this->fakeMember();

            $member = new Adherent($this->zdb);
            $member->setDependencies(
                $this->preferences,
                $this->member_fields,
                $this->history
            );
            if ($member->check($data, [], [])) {
                if ($member->store()) {
                    $this->mids[] = $member->id;
                    ++$done;
                    if ($this->with_photos && $faker->boolean($chanceOfGettingTrue = 70)) {
                        if ($this->addPhoto($member)) {
                            ++$photos_done;
                        }
                    }
                }

                //add to a group?
                if (count($this->groups) > 0 && $faker->boolean($chanceOfGettingTrue = 60)) {
                    $groups = $faker->randomElements($this->groups);
                    foreach ($groups as $group) {
                        $manager = $faker->boolean($chanceOfGettingTrue = 10);
                        if ($manager) {
                            $managers = $group->getManagers();
                            $managers[] = $member;
                            $group->setManagers($managers);
                        } else {
                            $members = $group->getMembers();
                            $members[] = $member;
                            $group->setMembers($members);
                        }
                    }
                }
            }
        }

        if ($count != 0 && $done != 0) {
            if ($done === $count) {
                $this->addSuccess(
                    str_replace('%count', $count, _T("%count members created"))
                );
            } else {
                $this->addWarning(
                    str_replace(
                        ['%count', '%done'],
                        [$count, $done],
                        _T("%count members requested, and %done created")
                    )
                );
            }
        }
        if ($this->with_photos === true) {
            if ($photos_done > 0) {
                $this->addSuccess(
                    str_replace('%count', $count, _T("%count photos created"))
                );
            } else {
                $this->addWarning(
                    _T("No photo has been created")
                );
            }
        }
    }

    /**
     * Get faked member data
     *
     * @return array
     */
    public function fakeMember()
    {
        $faker = $this->getFaker();
        if ($this->seed !== null) {
            $this->faker->seed($this->seed);
        }
        $creation_date = $faker->dateTimeBetween($startDate = '-3 years', $endDate = 'now');
        $mdp_adh = $faker->password();

        if ($this->status === null) {
            $status = new Status($this->zdb);
            $this->status = array_keys($status->getList());
        }

        $data = [
            'nom_adh'           => $faker->lastName(),
            'prenom_adh'        => $faker->firstName(),
            'ville_adh'         => $faker->city(),
            'cp_adh'            => $faker->postcode(),
            'adresse_adh'       => $faker->streetAddress(),
            'ville_adh'         => $faker->city(),
            'email_adh'         => $faker->unique()->email(),
            'login_adh'         => $faker->unique()->userName(),
            'mdp_adh'           => $mdp_adh,
            'mdp_adh2'          => $mdp_adh,
            'bool_admin_adh'    => $faker->boolean($chanceOfGettingTrue = 5),
            'bool_exempt_adh'   => $faker->boolean($chanceOfGettingTrue = 5),
            'bool_display_info' => $faker->boolean($chanceOfGettingTrue = 70),
            'sexe_adh'          => $faker->randomElement([Adherent::NC, Adherent::MAN, Adherent::WOMAN]),
            'prof_adh'          => $faker->jobTitle(),
            'titre_adh'         => $faker->randomElement(array_keys($this->titles)),
            'ddn_adh'           => $faker->dateTimeBetween(
                $startDate = '-110 years',
                $endDate = date('Y-m-d')
            )->format(_T("Y-m-d")),
            'lieu_naissance'    => $faker->city(),
            'pseudo_adh'        => $faker->userName(),
            'adresse_adh'       => $faker->streetAddress(),
            'cp_adh'            => $faker->postcode(),
            'ville_adh'         => $faker->city(),
            'pays_adh'          => $faker->optional()->country(),
            'tel_adh'           => $faker->phoneNumber(),
            'url_adh'           => $faker->optional()->url(),
            'activite_adh'      => $faker->boolean($chanceOfGettingTrue = 90),
            'id_statut'         => $faker->optional($weight = 0.3, $default = Status::DEFAULT_STATUS)
                                    ->randomElement($this->status),
            'date_crea_adh'     => $creation_date->format(_T("Y-m-d")),
            'pref_lang'         => $faker->randomElement(array_keys($this->i18n->getArrayList())),
            'fingerprint'       => 'FAKER' . ($this->seed !== null ? $this->seed : '')
        ];

        if ($faker->boolean($chanceOfGettingTrue = 20)) {
            $data['societe_adh'] = $faker->company();
            $data['is_company'] = true;
        }

        return $data;
    }

    /**
     * Add photo to a member
     *
     * @param Adherent $member Member instance
     *
     * @return boolean
     */
    public function addPhoto(Adherent $member)
    {
        $file = GALETTE_TEMPIMAGES_PATH . 'fakephoto.jpg';
        if (!defined('GALETTE_TESTS')) {
            $url = 'https://loremflickr.com/800/600/people';
        } else {
            $url = GALETTE_ROOT . '../tests/fake_image.jpg';
        }

        if (copy($url, $file)) {
            $_FILES = array(
                'photo' => array(
                    'name'      => 'fakephoto.jpg',
                    'type'      => 'image/jpeg',
                    'size'      => filesize($file),
                    'tmp_name'  => $file,
                    'error'     => 0
                )
            );
            $res = $member->picture->store($_FILES['photo'], true);
            if ($res < 0) {
                $this->addError(
                    _T("Photo has not been stored!")
                );
            } else {
                return true;
            }
        } else {
            $this->addError(
                _T("Photo has not been copied!")
            );
        }
        return false;
    }

    /**
     * Generate transactions
     *
     * @param integer $count Number of transactions to generate
     * @param array   $mids  Members ids. Defaults to null (will work with previously generated ids)
     *
     * @return void
     */
    public function generateTransactions($count = null, $mids = null)
    {
        $faker = $this->getFaker();

        $done = 0;

        if ($count === null) {
            $count = $this->nbtransactions;
        }

        if ($mids === null) {
            $mids = $this->mids;
        }

        for ($i = 0; $i < $count; $i++) {
            $data = [
                'trans_date'    => $faker->dateTimeBetween($startDate = '-1 years', $endDate = 'now')->format(_T("Y-m-d")),
                Adherent::PK    => $faker->randomElement($mids),
                'trans_amount'  => $faker->numberBetween($min = 50, $max = 2000),
                'trans_desc'    => $faker->realText($maxNbChars = 150)
            ];

            $transaction = new Transaction($this->zdb, $this->login);
            if ($transaction->check($data, [], [])) {
                if ($transaction->store($this->history)) {
                    $this->transactions[] = $transaction;
                    ++$done;
                }
            }
        }

        if ($count != 0 && $done != 0) {
            if ($done === $count) {
                $this->addSuccess(
                    str_replace('%count', $count, _T("%count transactions created"))
                );
            } else {
                $this->addWarning(
                    str_replace(
                        ['%count', '%done'],
                        [$count, $done],
                        _T("%count transactions requested, and %done created")
                    )
                );
            }
        }
    }

    /**
     * Generate members contributions
     *
     * @param array $mids Members ids. Defaults to null (will work with previously generated ids)
     *
     * @return void
     */
    public function generateContributions($mids = null)
    {
        $faker = $this->getFaker();

        if ($this->maxcontribs == 0) {
            return;
        }

        if ($mids === null) {
            $mids = $this->mids;
        }

        $done = 0;

        foreach ($mids as $mid) {
            $nbcontribs = $faker->numberBetween(0, $this->maxcontribs);
            for ($i = 0; $i < $nbcontribs; $i++) {
                $data = $this->fakeContrib($mid);
                $contrib = new Contribution($this->zdb, $this->login);
                if ($contrib->check($data, [], []) === true) {
                    if ($contrib->store()) {
                        $pk = Contribution::PK;
                        $this->cids[] = $contrib->$pk;
                        ++$done;
                    }

                    if (count($this->transactions) > 0) {
                        if ($faker->boolean($chanceOfGettingTrue = 90)) {
                            $transaction = $faker->randomElement($this->transactions);
                            $contrib::setTransactionPart(
                                $this->zdb,
                                $transaction->id,
                                $contrib->id
                            );
                        }
                    }
                }
            }
        }

        if ($done > 0) {
            $this->addSuccess(
                str_replace('%count', $done, _T("%count contributions created"))
            );
        } else {
            $this->addError(
                _T("No contribution created!")
            );
        }
    }

    /**
     * Get faked contribution data
     *
     * @param integer $mid Member id.
     *
     * @return array
     */
    public function fakeContrib($mid)
    {
        $faker = $this->getFaker();
        if ($this->seed !== null) {
            $this->faker->seed($this->seed);
        }

        if ($this->contrib_types === null) {
            $ct = new ContributionsTypes($this->zdb);
            $this->contrib_types = $ct->getCompleteList();
        }
        $types = $this->contrib_types;

        $begin_date = $faker->dateTimeBetween($startDate = '-3 years', $endDate = 'now');
        $end_date = clone $begin_date;
        $end_date->modify('+1 year');
        if (!$begin_date) {
            $begin_date = new \DateTime();
        }

        $data = [
            Adherent::PK            => $mid,
            ContributionsTypes::PK  => $faker->randomElement(array_keys($types)),
            'montant_cotis'         => $faker->numberBetween($min = 5, $max = 200),
            'type_paiement_cotis'   => $faker->randomElement(
                [
                    PaymentType::OTHER,
                    PaymentType::CASH,
                    PaymentType::CREDITCARD,
                    PaymentType::CHECK,
                    PaymentType::TRANSFER,
                    PaymentType::PAYPAL
                ]
            ),
            'info_cotis'            => ($this->seed !== null ?
                                        'FAKER' . $this->seed : $faker->optional($weight = 0.1)->realText($maxNbChars = 500)),
            'date_enreg'            => $faker->dateTimeBetween($startDate = '-1 years', $endDate = 'now')->format(_T("Y-m-d")),
            'date_debut_cotis'      => $begin_date->format(_T("Y-m-d")),
            'date_fin_cotis'        => $end_date->format(_T("Y-m-d"))
        ];

        if (count($this->transactions) > 0) {
            if ($faker->boolean($chanceOfGettingTrue = 90)) {
                $transaction = $faker->randomElement($this->transactions);
                $missing = $transaction->getMissingAmount();
                if ($data['montant_cotis'] > $missing) {
                    $data['montant_cotis'] = $missing;
                }
            }
        }

        return $data;
    }

    /**
     * Add success message
     *
     * @param string $msg Message
     *
     * @return void
     */
    protected function addSuccess($msg)
    {
        $this->report['success'][] = $msg;
    }

    /**
     * Add error message
     *
     * @param string $msg Message
     *
     * @return void
     */
    protected function addError($msg)
    {
        $this->report['errors'][] = $msg;
    }

    /**
     * Add warning message
     *
     * @param string $msg Message
     *
     * @return void
     */
    protected function addWarning($msg)
    {
        $this->report['warnings'][] = $msg;
    }

    /**
     * Get report
     *
     * @return array
     */
    public function getReport()
    {
        return $this->report;
    }

    /**
     * Set dependencies
     *
     * @param Preferences $preferences Preferences instance
     * @param array       $fields      Members fields configuration
     * @param History     $history     History instance
     * @param Login       $login       Login instance
     *
     * @return FakeData
     */
    public function setDependencies(
        Preferences $preferences,
        array $fields,
        History $history,
        Login $login
    ) {
        $this->preferences = $preferences;
        $this->member_fields = $fields;
        $this->history = $history;
        $this->login = $login;

        return $this;
    }

    /**
     * Get generated members ids
     *
     * @return array
     */
    public function getMembersIds()
    {
        return $this->mids;
    }
}
