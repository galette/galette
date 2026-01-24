<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

declare(strict_types=1);

namespace Galette\Tests;

/**
 * Galette tests case main class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
abstract class GaletteTestCase extends BaseGaletteTestCase
{
    protected array $members_fields;
    protected array $members_fields_cats;
    protected \Galette\Core\I18n $i18n;
    protected \Galette\Core\Preferences $preferences;
    protected \RKA\Session $session;
    protected \Galette\Core\Login $login;
    protected \Galette\Core\History $history;

    protected \Galette\Entity\Adherent $adh;
    protected \Galette\Entity\Contribution $contrib;
    protected array $adh_ids = [];
    protected array $contrib_ids = [];
    protected \Slim\Routing\RouteParser $routeparser;
    protected \Slim\Views\Twig $view;
    protected int $seed;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->i18n = $this->container->get(\Galette\Core\I18n::class);
        $this->login = $this->container->get(\Galette\Core\Login::class);
        $this->preferences = $this->container->get(\Galette\Core\Preferences::class);
        $this->history = $this->container->get(\Galette\Core\History::class);
        $this->members_fields = $this->container->get('members_fields');
        $this->members_fields_cats = $this->container->get('members_fields_cats');
        $this->session = $this->container->get(\RKA\Session::class);
        $this->routeparser = $this->container->get(\Slim\Routing\RouteParser::class);
        $this->view = $this->container->get(\Slim\Views\Twig::class);
        if ($this->load_plugins) {
            $this->plugins->loadModules($this->preferences, GALETTE_PLUGINS_PATH);
        }

        global $zdb, $login, $hist, $i18n, $container, $galette_log_var, $routeparser, $app;  // globals :(
        //phpcs:disable SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- globals \o/
        $zdb = $this->zdb;
        $login = $this->login;
        $hist = $this->history;
        $i18n = $this->i18n;
        $container = $this->container;
        $routeparser = $this->routeparser;
        $app = $this->app;
        //phpcs:enable
        //FIXME: use DI when needed instead of global variable -- see also in includes/main.inc.php
        $authenticate = $this->container->get(\Galette\Middleware\Authenticate::class); //phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- not used here, but in route files

        $this->initPaymentTypes();
        $this->initStatus();
        $this->initContributionsTypes();
        $this->initModels();
        $this->initTitles();

        require GALETTE_ROOT . 'includes/routes/main.routes.php';
        require GALETTE_ROOT . 'includes/routes/authentication.routes.php';
        require GALETTE_ROOT . 'includes/routes/management.routes.php';
        require GALETTE_ROOT . 'includes/routes/members.routes.php';
        require GALETTE_ROOT . 'includes/routes/groups.routes.php';
        require GALETTE_ROOT . 'includes/routes/contributions.routes.php';
        require GALETTE_ROOT . 'includes/routes/public_pages.routes.php';
        require GALETTE_ROOT . 'includes/routes/ajax.routes.php';
        require GALETTE_ROOT . 'includes/routes/plugins.routes.php';
    }

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        parent::tearDown();
        $this->cleanHistory();
    }

    /**
     * Loads member from a resultset
     *
     * @param int $id Id
     */
    protected function loadAdherent(int $id): void
    {
        $this->adh = new \Galette\Entity\Adherent($this->zdb, (int)$id);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
    }

    /**
     * Get Faker data for one member
     */
    protected function dataAdherentOne(): array
    {
        $bdate = new \DateTime(date('Y') . '-12-26');
        //member is expected to be 82 years old
        $years = 82;
        $now = new \DateTime();
        if ($now <= $bdate) {
            ++$years;
        }
        $bdate->sub(new \DateInterval('P' . $years . 'Y'));
        return [
            'nom_adh' => 'Durand',
            'prenom_adh' => 'René',
            'ville_adh' => 'Martel',
            'cp_adh' => '39 069',
            'adresse_adh' => '66, boulevard De Oliveira',
            'email_adh' => 'meunier.josephine' . $this->seed . '@ledoux.com',
            'login_adh' => 'arthur.hamon' . $this->seed,
            'mdp_adh' => 'J^B-()f',
            'mdp_adh2' => 'J^B-()f',
            'bool_admin_adh' => false,
            'bool_exempt_adh' => false,
            'bool_display_info' => true,
            'sexe_adh' => 0,
            'prof_adh' => 'Chef de fabrication',
            'titre_adh' => null,
            'ddn_adh' => $bdate->format('Y-m-d'),
            'lieu_naissance' => 'Gonzalez-sur-Meunier',
            'pseudo_adh' => 'ubertrand',
            'pays_adh' => 'Antarctique',
            'tel_adh' => '0439153432',
            'activite_adh' => true,
            'id_statut' => 9,
            'date_crea_adh' => '2020-06-10',
            'pref_lang' => 'en_US',
            'fingerprint' => 'FAKER' . $this->seed,
            'region_adh' => 'Caribbean'
        ];
    }

    /**
     * Get Faker data for second member
     */
    protected function dataAdherentTwo(): array
    {
        $bdate = new \DateTime(date('Y') . '-09-13');
        //member is expected to be 28 years old
        $years = 28;
        $now = new \DateTime();
        if ($now <= $bdate) {
            ++$years;
        }
        $bdate->sub(new \DateInterval('P' . $years . 'Y'));

        return [
            'nom_adh' => 'Hoarau',
            'prenom_adh' => 'Lucas',
            'ville_adh' => 'Reynaudnec',
            'cp_adh' => '63077',
            'adresse_adh' => '2, boulevard Legros',
            'email_adh' => 'phoarau' . $this->seed . '@tele2.fr',
            'login_adh' => 'nathalie51' . $this->seed,
            'mdp_adh' => 'T.u!IbKOi|06',
            'mdp_adh2' => 'T.u!IbKOi|06',
            'bool_admin_adh' => false,
            'bool_exempt_adh' => false,
            'bool_display_info' => false,
            'sexe_adh' => 1,
            'prof_adh' => 'Extraction',
            'titre_adh' => null,
            'ddn_adh' => $bdate->format('Y-m-d'),
            'lieu_naissance' => 'Fischer',
            'pseudo_adh' => 'vallet.camille',
            'pays_adh' => null,
            'tel_adh' => '05 59 53 59 43',
            'activite_adh' => true,
            'id_statut' => 9,
            'date_crea_adh' => '2019-05-20',
            'pref_lang' => 'ca',
            'fingerprint' => 'FAKER' . $this->seed,
            'societe_adh' => 'Philippe',
            'is_company' => true,
        ];
    }

    /**
     * Create member from data
     *
     * @param array<string, mixed> $data Data to use to create member
     */
    public function createMember(array $data): \Galette\Entity\Adherent
    {
        $waslogged = true;
        if (!$this->login->isLogged()) {
            $waslogged = false;
            $this->logSuperAdmin();
        }

        $this->adh = new \Galette\Entity\Adherent($this->zdb);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $check = $this->adh->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $this->adh->store();
        $this->assertTrue($store);

        if (!$waslogged) {
            $this->login->logOut();
        }
        return $this->adh;
    }

    /**
     * Check members expected
     *
     * @param ?\Galette\Entity\Adherent $adh           Member instance, if any
     * @param array<string, mixed>      $new_expecteds Changes on expected values
     */
    protected function checkMemberOneExpected(?\Galette\Entity\Adherent $adh = null, array $new_expecteds = []): void
    {
        if ($adh === null) {
            $adh = $this->adh;
        }

        $expecteds = $this->dataAdherentOne();
        unset($expecteds['mdp_adh2']);
        $expecteds = array_merge($expecteds, $new_expecteds);

        foreach ($expecteds as $key => $value) {
            $property = $this->members_fields[$key]['propname'];
            $this->assertTrue(isset($adh->$property));
            switch ($key) {
                case 'bool_admin_adh':
                    $this->assertSame($value, $adh->$property);
                    $this->expectLogEntry(\Analog\Analog::WARNING, 'Calling property "admin" directly is discouraged.');
                    $this->assertSame($value, $adh->isAdmin());
                    break;
                case 'bool_exempt_adh':
                    $this->assertSame($value, $adh->$property);
                    $this->expectLogEntry(\Analog\Analog::WARNING, 'Calling property "due_free" directly is discouraged.');
                    $this->assertSame($value, $adh->isDueFree());
                    break;
                case 'bool_display_info':
                    $this->assertSame($value, $adh->$property);
                    $this->expectLogEntry(\Analog\Analog::WARNING, 'Calling property "appears_in_list" directly is discouraged.');
                    $this->assertSame($value, $adh->appearsInMembersList());
                    break;
                case 'activite_adh':
                    $this->assertSame($value, $adh->$property);
                    $this->expectLogEntry(\Analog\Analog::WARNING, 'Calling property "active" directly is discouraged.');
                    $this->assertSame($value, $adh->isActive());
                    break;
                case 'mdp_adh':
                    $pw_checked = password_verify((string)$value, (string)$adh->password);
                    $this->assertTrue($pw_checked);
                    break;
                case 'ddn_adh':
                    //rely on age, not on birthdate
                    $this->assertNotNull($adh->$property);
                    $this->assertSame(' (82 years old)', $adh->getAge());
                    break;
                default:
                    $this->assertSame(
                        $value,
                        $adh->$property,
                        "$property expected {$value} got {$adh->$property}"
                    );
                    break;
            }
        }

        $this->assertFalse($adh->hasChildren());
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Children has not been loaded!');
        $this->assertFalse($adh->hasParent());
        $this->assertFalse($adh->hasPicture());

        $this->assertSame('No', $adh->sadmin);
        $this->assertSame('No', $adh->sdue_free);
        $this->assertSame('Yes', $adh->sappears_in_list);
        $this->assertSame('No', $adh->sstaff);
        $this->assertSame('Active', $adh->sactive);
        $this->assertNull($adh->stitle);
        $this->assertSame('Non-member', $adh->sstatus);
        $this->assertSame('DURAND René', $adh->sfullname);
        $this->assertSame('66, boulevard De Oliveira', $adh->saddress);
        $this->assertSame('DURAND René', $adh->sname);

        $this->assertSame($expecteds['adresse_adh'], $adh->getAddress());
        $this->assertSame($expecteds['cp_adh'], $adh->getZipcode());
        $this->assertSame($expecteds['ville_adh'], $adh->getTown());
        $this->assertSame($expecteds['pays_adh'], $adh->getCountry());
        $this->assertSame($expecteds['region_adh'], $adh->getRegion());

        $this->assertSame('DURAND René', $adh::getSName($this->zdb, $adh->id));
        $this->assertSame('active-account cotis-never', $adh->getRowClass());
    }

    /**
     * Check members expected values
     *
     * @param ?\Galette\Entity\Adherent $adh           Member instance, if any
     * @param array<string, mixed>      $new_expecteds Changes on expected values
     */
    protected function checkMemberTwoExpected(?\Galette\Entity\Adherent $adh = null, array $new_expecteds = []): void
    {
        if ($adh === null) {
            $adh = $this->adh;
        }

        $expecteds = $this->dataAdherentTwo();
        unset($expecteds['mdp_adh2']);
        $expecteds = array_merge($expecteds, $new_expecteds);

        foreach ($expecteds as $key => $value) {
            $property = $this->members_fields[$key]['propname'];
            $this->assertTrue(isset($adh->$property));
            switch ($key) {
                case 'bool_admin_adh':
                    $this->assertSame($value, $adh->$property);
                    $this->assertSame($value, $adh->isAdmin());
                    break;
                case 'bool_exempt_adh':
                    $this->assertSame($value, $adh->$property);
                    $this->assertSame($value, $adh->isDueFree());
                    break;
                case 'bool_display_info':
                    $this->assertSame($value, $adh->$property);
                    $this->assertSame($value, $adh->appearsInMembersList());
                    break;
                case 'activite_adh':
                    $this->assertSame($value, $adh->$property);
                    $this->assertSame($value, $adh->isActive());
                    break;
                case 'mdp_adh':
                    $pw_checked = password_verify((string)$value, (string)$adh->password);
                    $this->assertTrue($pw_checked);
                    break;
                case 'ddn_adh':
                    //rely on age, not on birthdate
                    $this->assertNotNull($adh->$property);
                    $this->assertSame(' (28 years old)', $adh->getAge());
                    break;
                default:
                    $this->assertSame(
                        $adh->$property,
                        $value,
                        "$property expected {$value} got {$adh->$property}"
                    );
                    break;
            }
        }

        $this->assertFalse($adh->hasChildren());
        $this->assertFalse($adh->hasParent());
        $this->assertFalse($adh->hasPicture());

        $this->assertSame('No', $adh->sadmin);
        $this->assertSame('No', $adh->sdue_free);
        $this->assertSame('No', $adh->sappears_in_list);
        $this->assertSame('No', $adh->sstaff);
        $this->assertSame('Active', $adh->sactive);
        $this->assertNull($adh->stitle);
        $this->assertSame('Non-member', $adh->sstatus);
        $this->assertSame('HOARAU Lucas', $adh->sfullname);
        $this->assertSame('2, boulevard Legros', $adh->saddress);
        $this->assertSame('HOARAU Lucas', $adh->sname);

        $this->assertSame($expecteds['adresse_adh'], $adh->getAddress());
        $this->assertSame($expecteds['cp_adh'], $adh->getZipcode());
        $this->assertSame($expecteds['ville_adh'], $adh->getTown());
        $this->assertSame($expecteds['pays_adh'], $adh->getCountry());
        $this->assertSame('', $adh->getRegion());

        $this->assertSame('HOARAU Lucas', $adh::getSName($this->zdb, $adh->id));
        $this->assertSame('active-account cotis-never', $adh->getRowClass());
    }

    /**
     * Look in database if test member already exists
     */
    protected function adhOneExists(): false|\Laminas\Db\ResultSet\ResultSet
    {
        $mdata = $this->dataAdherentOne();
        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE, 'a');
        $select->where(
            [
                'a.fingerprint' => 'FAKER' . $this->seed,
                'a.login_adh' => $mdata['login_adh']
            ]
        );

        $results = $this->zdb->execute($select);
        if ($results->count() === 0) {
            return false;
        } else {
            return $results;
        }
    }

    /**
     * Look in database if test member already exists
     */
    protected function adhTwoExists(): false|\Laminas\Db\ResultSet\ResultSet
    {
        $mdata = $this->dataAdherentTwo();
        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE, 'a');
        $select->where(
            [
                'a.fingerprint' => 'FAKER' . $this->seed,
                'a.login_adh' => $mdata['login_adh']
            ]
        );

        $results = $this->zdb->execute($select);
        if ($results->count() === 0) {
            return false;
        } else {
            return $results;
        }
    }

    /**
     * Get member one
     */
    protected function getMemberOne(): \Galette\Entity\Adherent
    {
        $rs = $this->adhOneExists();
        if ($rs === false) {
            $this->createMember($this->dataAdherentOne());
        } else {
            $this->loadAdherent((int)$rs->current()->id_adh);
        }
        return $this->adh;
    }

    /**
     * Get member two
     */
    protected function getMemberTwo(): \Galette\Entity\Adherent
    {
        $rs = $this->adhTwoExists();
        if ($rs === false) {
            $this->createMember($this->dataAdherentTwo());
        } else {
            $this->loadAdherent((int)$rs->current()->id_adh);
        }
        return $this->adh;
    }

    /**
     * Create contribution from data
     *
     * @param array<string,mixed>           $data    Data to use to create contribution
     * @param ?\Galette\Entity\Contribution $contrib Contribution instance, if any
     */
    public function createContrib(array $data, ?\Galette\Entity\Contribution $contrib = null): \Galette\Entity\Contribution
    {
        if ($contrib === null) {
            $this->contrib = new \Galette\Entity\Contribution(
                $this->zdb,
                $this->login,
                ['type' => $data['id_type_cotis']]
            );
            $contrib = $this->contrib;
        }

        $check = $contrib->check($data, $contrib->getRequired(), []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $contrib->store();
        $this->assertTrue($store);

        return $contrib;
    }

    /**
     * Get contribution data
     *
     * @return array<string,mixed>
     */
    protected function getContribData(): array
    {
        $now = new \DateTime(); // 2020-11-07
        $begin_date = clone $now;
        $begin_date->sub(new \DateInterval('P5M')); // 2020-06-08
        $begin_date->add(new \DateInterval('P3D')); // 2020-06-11

        $due_date = clone $begin_date;
        $due_date->sub(new \DateInterval('P1D'));
        $due_date->add(new \DateInterval('P1Y'));

        return [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 1, //annual fee
            'montant_cotis' => 92,
            'type_paiement_cotis' => 3,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $begin_date->format('Y-m-d'),
            'date_debut_cotis' => $begin_date->format('Y-m-d'),
            'date_fin_cotis' => $due_date->format('Y-m-d'),
        ];
    }

    /**
     * Create test contribution in database
     */
    protected function createContribution(): void
    {
        $this->createContrib($this->getContribData());
        $this->checkContribExpected();
    }

    /**
     * Check contributions expected
     *
     * @param ?\Galette\Entity\Contribution $contrib       Contribution instance, if any
     * @param array<string,mixed>           $new_expecteds Changes on expected values
     */
    protected function checkContribExpected(?\Galette\Entity\Contribution $contrib = null, array $new_expecteds = []): void
    {
        if ($contrib === null) {
            $contrib = $this->contrib;
        }

        $begin_date = $contrib->raw_begin_date;

        $due_date = clone $begin_date;
        $due_date->sub(new \DateInterval('P1D'));
        $due_date->add(new \DateInterval('P1Y'));

        $this->assertInstanceOf('DateTime', $contrib->raw_date);
        $this->assertInstanceOf('DateTime', $contrib->raw_begin_date);
        $this->assertInstanceOf('DateTime', $contrib->raw_end_date);

        $expecteds = [
            'id_adh' => "{$this->adh->id}",
            'id_type_cotis' => 1, //annual fee
            'montant_cotis' => '92',
            'type_paiement_cotis' => '3',
            'info_cotis' => 'FAKER' . $this->seed,
            'date_fin_cotis' => $due_date->format('Y-m-d'),
            'row_class' => 'active-account cotis-ok', //member is up-to-date
            'type' => \Galette\Entity\Contribution::TYPE_FEE,
        ];
        $expecteds = array_merge($expecteds, $new_expecteds);

        $this->assertSame($expecteds['date_fin_cotis'], $contrib->raw_end_date->format('Y-m-d'));

        //load member from db
        $this->adh = new \Galette\Entity\Adherent($this->zdb, $this->adh->id);
        $this->assertSame($expecteds['row_class'], $this->adh->getRowClass());
        unset($expecteds['row_class']);
        if ($expecteds['type'] === \Galette\Entity\Contribution::TYPE_FEE) {
            $this->assertSame($this->contrib->end_date, $this->adh->due_date);
            $this->assertTrue($this->adh->isUp2Date());
            $this->assertTrue($contrib->isFee());
            $this->assertSame('Membership', $contrib->getTypeLabel());
            $this->assertSame('membership', $contrib->getRawType());
            $this->assertSame(
                $this->contrib->getRequired(),
                [
                    'id_type_cotis'     => 1,
                    'id_adh'            => 1,
                    'date_enreg'        => 1,
                    'date_debut_cotis'  => 1,
                    'date_fin_cotis'    => 1,
                    'montant_cotis'     => 1
                ]
            );
        } else {
            $this->assertFalse($contrib->isFee());
            $this->assertSame('Donation', $contrib->getTypeLabel());
            $this->assertSame('donation', $contrib->getRawType());
            $this->assertSame(
                $this->contrib->getRequired(),
                [
                    'id_type_cotis'     => 1,
                    'id_adh'            => 1,
                    'date_enreg'        => 1,
                    'date_debut_cotis'  => 1,
                    'date_fin_cotis'    => 0,
                    'montant_cotis'     => 0
                ]
            );
        }
        unset($expecteds['type']);

        foreach ($expecteds as $key => $value) {
            $property = $this->contrib->fields[$key]['propname'];
            switch ($key) {
                case \Galette\Entity\ContributionsTypes::PK:
                    $ct = $this->contrib->type;
                    if ($ct instanceof \Galette\Entity\ContributionsTypes) {
                        $this->assertSame($value, (int)$ct->id);
                    } else {
                        $this->assertSame($value, $ct);
                    }
                    break;
                default:
                    $this->assertEquals($contrib->$property, $value, $property);
                    break;
            }
        }
    }

    /**
     * Initialize default status in database
     */
    protected function initStatus(): void
    {
        $status = $this->container->get(\Galette\Entity\Status::class);
        if (count($status->getList()) === 0) {
            //status are not yet instantiated.
            $res = $status->installInit();
            $this->assertTrue($res);
        }
    }

    /**
     * Initialize default contributions types in database
     */
    protected function initContributionsTypes(): void
    {
        $ct = new \Galette\Entity\ContributionsTypes($this->zdb);
        if (count($ct->getCompleteList()) === 0) {
            //contributions types are not yet instantiated.
            $res = $ct->installInit();
            $this->assertTrue($res);
        }
    }

    /**
     * Initialize default payment types in database
     */
    protected function initPaymentTypes(): void
    {
        $types = new \Galette\Repository\PaymentTypes($this->zdb, $this->preferences, $this->login);
        if (count($types->getList()) === 0) {
            //payment types are not yet instantiated.
            $res = $types->installInit();
            $this->assertTrue($res);
        }
    }

    /**
     * Initialize default titles in database
     */
    protected function initTitles(): void
    {
        $titles = new \Galette\Repository\Titles($this->zdb);
        if (count($titles->getList()) === 0) {
            $res = $titles->installInit();
            $this->assertTrue($res);
        }
    }

    /**
     * Initialize default PDF models in database
     */
    protected function initModels(): void
    {
        $models = new \Galette\Repository\PdfModels($this->zdb, $this->preferences, $this->login);
        $res = $models->installInit(false);
        $this->assertTrue($res);
    }

    /**
     * Clean history
     */
    protected function cleanHistory(): void
    {
        $this->zdb->db->query(
            'TRUNCATE TABLE ' . PREFIX_DB . \Galette\Core\History::TABLE,
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
    }

    /**
     * Log-in as super administrator
     */
    protected function logSuperAdmin(): void
    {
        $this->login->logAdmin('superadmin', $this->preferences);
        $this->assertTrue($this->login->isLogged());
        $this->assertTrue($this->login->isSuperAdmin());
    }

    /**
     * Clean created contributions
     */
    protected function cleanContributions(): void
    {
        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where(['info_cotis' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Transaction::TABLE);
        $delete->where(['trans_desc' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);
    }

    /**
     * Clean created members and groups
     */
    protected function cleanMembers(): void
    {
        $delete = $this->zdb->delete(\Galette\Entity\Group::GROUPSUSERS_TABLE);
        $this->zdb->execute($delete);
        $delete = $this->zdb->delete(\Galette\Entity\Group::GROUPSMANAGERS_TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Group::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $delete->where('parent_id IS NOT NULL');
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);
    }

    /**
     * Set given member as staff
     *
     * @param \Galette\Entity\Adherent $member Member to edit
     */
    protected function getStaffMember(\Galette\Entity\Adherent $member): \Galette\Entity\Adherent
    {
        $this->logSuperAdmin();

        $staff_member = clone $member;
        $check = $staff_member->check(['id_statut' => '1'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($staff_member->store());

        $this->login->logout();

        return $staff_member;
    }

    /**
     * Reset given staff member to given member status
     *
     * @param \Galette\Entity\Adherent $staff_member Staff member to edit
     * @param \Galette\Entity\Adherent $member       Member to get status from
     */
    protected function resetStaffStatus(\Galette\Entity\Adherent $staff_member, \Galette\Entity\Adherent $member): void
    {
        $this->logSuperAdmin();
        $check = $staff_member->check(['id_statut' => $member->status], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($staff_member->store());
        $this->assertTrue($member->load($member->id));
        $this->login->logout();
    }

    /**
     * Set given member as admin
     *
     * @param \Galette\Entity\Adherent $member Member to edit
     */
    protected function getAdminMember(\Galette\Entity\Adherent $member): \Galette\Entity\Adherent
    {
        $this->logSuperAdmin();

        $adm_member = clone $member;
        $check = $adm_member->check(['bool_admin_adh' => '1'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($adm_member->store());

        $this->login->logout();

        return $adm_member;
    }

    /**
     * Reset given admin member flag
     *
     * @param \Galette\Entity\Adherent $admin_member Admin member to edit
     */
    protected function resetAdminStatus(\Galette\Entity\Adherent $admin_member): void
    {
        $this->logSuperAdmin();
        $check = $admin_member->check(['bool_admin_adh' => '0'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($admin_member->store());
        $this->login->logout();
    }

    /**
     * Get mocked document instance
     */
    protected function getDocumentInstance(): \Galette\Entity\Document
    {
        $document = $this->getMockBuilder(\Galette\Entity\Document::class)
            ->setConstructorArgs([$this->zdb])
            ->onlyMethods(['handleFiles'])
            ->getMock();

        $document->method('handleFiles')
            ->willReturnCallback(
                function (array $files) use ($document) {
                    $reflection = new \ReflectionClass(\Galette\Entity\Document::class);
                    $reflection_property = $reflection->getProperty('filename');
                    $reflection_property->setValue($document, $files['document_file']->getClientFilename());

                    return true;
                }
            );
        return $document;
    }
}
