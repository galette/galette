<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Generates fake data as example
 *
 * PHP version 5
 *
 * Copyright © 2017 The Galette Team
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
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   0.7
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9
 */

namespace Galette\Util;

use Analog\Analog;
use Galette\Core\Db;
use Galette\Core\I18n;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Repository\Titles;
use Galette\Entity\Status;
use Galette\Entity\ContributionsTypes;

/**
 * Generate random data
 *
 * @see https://github.com/fzaninotto/Faker
 */
class FakeData
{
    protected $zdb;
    protected $i18n;
    protected $faker;
    private $report = [
        'success'   => [],
        'errors'    => [],
        'warnings'  => []
    ];

    protected $mids = [];

    /**
     * @var integer
     * Number of members to generate
     */
    protected $nbmembers = 20;

    /**
     * @var integer
     * Max number of contributions to generate
     * for each member
     */
    protected $maxcontribs = 5;

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
     * Set number of members to generate
     *
     * @param integer $nb Number of members
     *
     * @return FakeData
     */
    public function setNbMembers($nb)
    {
        $this->nbmembers = $nb;
        return $this;
    }

    /**
     * Do data generation
     *
     * @return void
     */
    public function generate()
    {
        $this->faker = \Faker\Factory::create($this->i18n->getID());

        $this->generateMembers($this->nbmembers);
        $this->generateContributions();
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
        $faker = $this->faker;
        $langs = $this->i18n->getArrayList();
        $titles = Titles::getArrayList($this->zdb);
        $status = new Status();
        $status = $status->getList();

        $done = 0;

        if ($count === null) {
            $count = $this->nbmembers;
        }

        for ($i = 0; $i < $count; $i++) {
            $creation_date = $faker->dateTimeBetween($startDate = '-3 years', $endDate = 'now');
            $mdp_adh = $faker->password();

            $data= [
                'nom_adh'           => $faker->lastName(),
                'prenom_adh'        => $faker->firstName(),
                'ville_adh'         => $faker->city(),
                'cp_ad'             => $faker->postcode(),
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
                'titre_adh'         => $faker->randomElement(array_keys($titles)),
                'ddn_adh'           => $faker->dateTimeBetween($startDate = '-110 years', $endDate = 'now')->format(_T("Y-m-d")),
                'lieu_naissance'    => $faker->city(),
                'pseudo_adh'        => $faker->userName(),
                'adresse_adh'       => $faker->streetAddress(),
                'cp_adh'            => $faker->postcode(),
                'ville_adh'         => $faker->city(),
                'pays_adh'          => $faker->optional()->country(),
                'tel_adh'           => $faker->phoneNumber(),
                'email_adh'         => $faker->email(),
                'url_adh'           => $faker->optional()->url(),
                'activite_adh'      => $faker->boolean($chanceOfGettingTrue = 90),
                'id_statut'         => $faker->optional($weight = 0.3, $default = Status::DEFAULT_STATUS)
                                        ->randomElement(array_keys($status)),
                'date_crea_adh'     => $creation_date->format(_T("Y-m-d")),
                'pref_lang'         => $faker->randomElement(array_keys($langs)),
                'fingerprint'       => 'FAKER'
            ];

            if ($faker->boolean($chanceOfGettingTrue = 20)) {
                $data['societe_adh'] = $faker->company();
            }

            $member = new Adherent();
            if ($member->check($data, [], [])) {
                if ($member->store()) {
                    $this->mids[] = $member->id;
                    ++$done;
                }
            }
        }

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

    /**
     * Generate members contributions
     *
     * @param array $mids Members ids. Defaults to null (will work with previously generated ids)
     *
     * @return void
     */
    public function generateContributions($mids = null)
    {
        $faker = $this->faker;

        $types = new ContributionsTypes();
        $types = $types->getCompleteList();

        if ($mids === null) {
            $mids = $this->mids;
        }

        $done = 0;

        foreach ($mids as $mid) {
            $nbcontribs = $faker->numberBetween(0, $this->maxcontribs);
            for ($i = 0; $i < $nbcontribs; $i++) {
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
                            Contribution::PAYMENT_OTHER,
                            Contribution::PAYMENT_CASH,
                            Contribution::PAYMENT_CREDITCARD,
                            Contribution::PAYMENT_CHECK,
                            Contribution::PAYMENT_TRANSFER,
                            Contribution::PAYMENT_PAYPAL
                        ]
                    ),
                    'info_cotis'            => $faker->optional($weight = 0.1)->realText($maxNbChars = 500),
                    'date_enreg'            => $faker->dateTimeBetween($startDate = '-1 years', $endDate = 'now')->format(_T("Y-m-d")),
                    'date_debut_cotis'      => $begin_date->format(_T("Y-m-d")),
                    'date_fin_cotis'        => $end_date->format(_T("Y-m-d"))
                ];

                $contrib = new Contribution();
                if ($contrib->check($data, [], []) === true) {
                    if ($contrib->store()) {
                        ++$done;
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
        $this->report['warning'][] = $msg;
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
}
