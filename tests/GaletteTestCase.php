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

namespace Galette;

use PHPUnit\Framework\TestCase;

/**
 * Galette tests case main class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
abstract class GaletteTestCase extends TestCase
{
    /** @var \Galette\Core\Db */
    protected \Galette\Core\Db $zdb;
    protected array $members_fields;
    protected array $members_fields_cats;
    /** @var \Galette\Core\I18n */
    protected \Galette\Core\I18n $i18n;
    /** @var \Galette\Core\Preferences */
    protected \Galette\Core\Preferences $preferences;
    protected \RKA\Session $session;
    /** @var \Galette\Core\Login */
    protected \Galette\Core\Login $login;
    /** @var \Galette\Core\History */
    protected \Galette\Core\History $history;
    protected $logger_storage = '';

    /** @var \Galette\Entity\Adherent */
    protected \Galette\Entity\Adherent $adh;
    /** @var \Galette\Entity\Contribution */
    protected \Galette\Entity\Contribution $contrib;
    protected array $adh_ids = [];
    protected array $contrib_ids = [];
    /** @var array */
    protected array $flash_data;
    /** @var \Slim\Flash\Messages */
    protected \Slim\Flash\Messages $flash;
    protected \DI\Container $container;
    protected int $seed;
    protected array $expected_mysql_warnings = [];

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        $flash_data = [];
        $this->flash_data = &$flash_data;
        $this->flash = new \Slim\Flash\Messages($flash_data);

        $gapp =  new \Galette\Core\SlimApp();
        $app = $gapp->getApp();
        $plugins = new \Galette\Core\Plugins();
        require GALETTE_BASE_PATH . '/includes/dependencies.php';
        /** @var \DI\Container $container */
        $container = $app->getContainer();
        $_SERVER['HTTP_HOST'] = '';

        $container->set('flash', $this->flash);
        $container->set(\Slim\Flash\Messages::class, $this->flash);

        $app->addRoutingMiddleware();

        $this->container = $container;

        $this->zdb = $container->get('zdb');
        $this->i18n = $container->get('i18n');
        $this->login = $container->get('login');
        $this->preferences = $container->get('preferences');
        $this->history = $container->get('history');
        $this->members_fields = $container->get('members_fields');
        $this->members_fields_cats = $container->get('members_fields_cats');
        $this->session = $container->get('session');

        global $zdb, $login, $hist, $i18n, $container, $galette_log_var; // globals :(
        $zdb = $this->zdb;
        $login = $this->login;
        $hist = $this->history;
        $i18n = $this->i18n;
        $container = $this->container;
        $galette_log_var = $this->logger_storage;

        $authenticate = new \Galette\Middleware\Authenticate($container);
        $showPublicPages = function (\Slim\Psr7\Request $request, \Psr\Http\Server\RequestHandlerInterface $handler) {
            return $handler->handle($request);
        };

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
     *
     * @return void
     */
    public function tearDown(): void
    {
        if (TYPE_DB === 'mysql') {
            $this->assertSame($this->expected_mysql_warnings, $this->zdb->getWarnings());
        }
        $this->cleanHistory();
    }

    /**
     * Loads member from a resultset
     *
     * @param integer $id Id
     *
     * @return void
     */
    protected function loadAdherent($id)
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
     *
     * @return array
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
        $data = [
            'nom_adh' => 'Durand',
            'prenom_adh' => 'René',
            'ville_adh' => 'Martel',
            'cp_adh' => '39 069',
            'adresse_adh' => '66, boulevard De Oliveira',
            'email_adh' => 'meunier.josephine' .  $this->seed . '@ledoux.com',
            'login_adh' => 'arthur.hamon' .  $this->seed,
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
        ];
        return $data;
    }

    /**
     * Get Faker data for second member
     *
     * @return array
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

        $data = [
            'nom_adh' => 'Hoarau',
            'prenom_adh' => 'Lucas',
            'ville_adh' => 'Reynaudnec',
            'cp_adh' => '63077',
            'adresse_adh' => '2, boulevard Legros',
            'email_adh' => 'phoarau' .  $this->seed . '@tele2.fr',
            'login_adh' => 'nathalie51' .  $this->seed,
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
        return $data;
    }

    /**
     * Create member from data
     *
     * @param array $data Data to use to create member
     *
     * @return \Galette\Entity\Adherent
     */
    public function createMember(array $data)
    {
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

        return $this->adh;
    }

    /**
     * Check members expecteds
     *
     * @param \Galette\Entity\Adherent $adh           Member instance, if any
     * @param array                    $new_expecteds Changes on expected values
     *
     * @return void
     */
    protected function checkMemberOneExpected($adh = null, $new_expecteds = [])
    {
        if ($adh === null) {
            $adh = $this->adh;
        }

        $expecteds = [
            'nom_adh' => 'Durand',
            'prenom_adh' => 'René',
            'ville_adh' => 'Martel',
            'adresse_adh' => '66, boulevard De Oliveira',
            'email_adh' => 'meunier.josephine' .  $this->seed . '@ledoux.com',
            'login_adh' => 'arthur.hamon' .  $this->seed,
            'mdp_adh' => 'J^B-()f',
            'bool_admin_adh' => false,
            'bool_exempt_adh' => false,
            'bool_display_info' => true,
            'sexe_adh' => 0,
            'prof_adh' => 'Chef de fabrication',
            'titre_adh' => null,
            'ddn_adh' => 'NOT USED',
            'lieu_naissance' => 'Gonzalez-sur-Meunier',
            'pseudo_adh' => 'ubertrand',
            'cp_adh' => '39 069',
            'pays_adh' => 'Antarctique',
            'tel_adh' => '0439153432',
            'activite_adh' => true,
            'id_statut' => 9,
            'pref_lang' => 'en_US',
            'fingerprint' => 'FAKER95842354',
            'societe_adh' => ''
        ];
        $expecteds = array_merge($expecteds, $new_expecteds);

        foreach ($expecteds as $key => $value) {
            $property = $this->members_fields[$key]['propname'];
            switch ($key) {
                case 'bool_admin_adh':
                    $this->assertSame($value, $adh->isAdmin());
                    break;
                case 'bool_exempt_adh':
                    $this->assertSame($value, $adh->isDueFree());
                    break;
                case 'bool_display_info':
                    $this->assertSame($value, $adh->appearsInMembersList());
                    break;
                case 'activite_adh':
                    $this->assertSame($value, $adh->isActive());
                    break;
                case 'mdp_adh':
                    $pw_checked = password_verify($value, $adh->password);
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

        $d = \DateTime::createFromFormat('Y-m-d', $expecteds['ddn_adh']);

        $this->assertFalse($adh->hasChildren());
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

        $this->assertSame('DURAND René', $adh::getSName($this->zdb, $adh->id));
        $this->assertSame('active-account cotis-never', $adh->getRowClass());
    }

    /**
     * Check members expecteds
     *
     * @param \Galette\Entity\Adherent $adh           Member instance, if any
     * @param array                    $new_expecteds Changes on expected values
     *
     * @return void
     */
    protected function checkMemberTwoExpected($adh = null, $new_expecteds = [])
    {
        if ($adh === null) {
            $adh = $this->adh;
        }

        $expecteds = [
            'nom_adh' => 'Hoarau',
            'prenom_adh' => 'Lucas',
            'ville_adh' => 'Reynaudnec',
            'cp_adh' => '63077',
            'adresse_adh' => '2, boulevard Legros',
            'email_adh' => 'phoarau' .  $this->seed . '@tele2.fr',
            'login_adh' => 'nathalie51' .  $this->seed,
            'mdp_adh' => 'T.u!IbKOi|06',
            'bool_admin_adh' => false,
            'bool_exempt_adh' => false,
            'bool_display_info' => false,
            'sexe_adh' => 1,
            'prof_adh' => 'Extraction',
            'titre_adh' => null,
            'ddn_adh' => 'NOT USED',
            'lieu_naissance' => 'Fischer',
            'pseudo_adh' => 'vallet.camille',
            'pays_adh' => '',
            'tel_adh' => '05 59 53 59 43',
            'activite_adh' => true,
            'id_statut' => 9,
            'pref_lang' => 'ca',
            'fingerprint' => 'FAKER' . $this->seed,
            'societe_adh' => 'Philippe'
        ];
        $expecteds = array_merge($expecteds, $new_expecteds);

        foreach ($expecteds as $key => $value) {
            $property = $this->members_fields[$key]['propname'];
            switch ($key) {
                case 'bool_admin_adh':
                    $this->assertSame($value, $adh->isAdmin());
                    break;
                case 'bool_exempt_adh':
                    $this->assertSame($value, $adh->isDueFree());
                    break;
                case 'bool_display_info':
                    $this->assertSame($value, $adh->appearsInMembersList());
                    break;
                case 'activite_adh':
                    $this->assertSame($value, $adh->isActive());
                    break;
                case 'mdp_adh':
                    $pw_checked = password_verify($value, $adh->password);
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

        $d = \DateTime::createFromFormat('Y-m-d', $expecteds['ddn_adh']);

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

        $this->assertSame('HOARAU Lucas', $adh::getSName($this->zdb, $adh->id));
        $this->assertSame('active-account cotis-never', $adh->getRowClass());
    }

    /**
     * Look in database if test member already exists
     *
     * @return false|\Laminas\Db\ResultSet\ResultSet
     */
    protected function adhOneExists()
    {
        $mdata = $this->dataAdherentOne();
        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE, 'a');
        $select->where(
            array(
                'a.fingerprint' => 'FAKER' . $this->seed,
                'a.login_adh' => $mdata['login_adh']
            )
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
     *
     * @return false|\Laminas\Db\ResultSet\ResultSet
     */
    protected function adhTwoExists()
    {
        $mdata = $this->dataAdherentTwo();
        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE, 'a');
        $select->where(
            array(
                'a.fingerprint' => 'FAKER' . $this->seed,
                'a.login_adh' => $mdata['login_adh']
            )
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
     *
     * @return \Galette\Entity\Adherent
     */
    protected function getMemberOne()
    {
        $rs = $this->adhOneExists();
        if ($rs === false) {
            $this->createMember($this->dataAdherentOne());
        } else {
            $this->loadAdherent($rs->current()->id_adh);
        }
        return $this->adh;
    }

    /**
     * Get member two
     *
     * @return \Galette\Entity\Adherent
     */
    protected function getMemberTwo()
    {
        $rs = $this->adhTwoExists();
        if ($rs === false) {
            $this->createMember($this->dataAdherentTwo());
        } else {
            $this->loadAdherent($rs->current()->id_adh);
        }
        return $this->adh;
    }

    /**
     * Create contribution from data
     *
     * @param array<string,mixed>           $data    Data to use to create contribution
     * @param ?\Galette\Entity\Contribution $contrib Contribution instance, if any
     *
     * @return \Galette\Entity\Contribution
     */
    public function createContrib(array $data, \Galette\Entity\Contribution $contrib = null)
    {
        if ($contrib === null) {
            $this->contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);
            $contrib = $this->contrib;
        }

        $check = $contrib->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $contrib->store();
        $this->assertTrue($store);

        return $contrib;
    }

    /**
     * Create test contribution in database
     *
     * @return void
     */
    protected function createContribution()
    {
        $now = new \DateTime(); // 2020-11-07
        $begin_date = clone $now;
        $begin_date->sub(new \DateInterval('P5M')); // 2020-06-08
        $begin_date->add(new \DateInterval('P3D')); // 2020-06-11

        $due_date = clone $begin_date;
        $due_date->sub(new \DateInterval('P1D'));
        $due_date->add(new \DateInterval('P1Y'));

        $data = [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 1, //annual fee
            'montant_cotis' => 92,
            'type_paiement_cotis' => 3,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $begin_date->format('Y-m-d'),
            'date_debut_cotis' => $begin_date->format('Y-m-d'),
            'date_fin_cotis' => $due_date->format('Y-m-d'),
        ];
        $this->createContrib($data);
        $this->checkContribExpected();
    }

    /**
     * Check contributions expected
     *
     * @param \Galette\Entity\Contribution $contrib       Contribution instance, if any
     * @param array                        $new_expecteds Changes on expected values
     *
     * @return void
     */
    protected function checkContribExpected($contrib = null, $new_expecteds = [])
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
        ];
        $expecteds = array_merge($expecteds, $new_expecteds);

        $this->assertSame($expecteds['date_fin_cotis'], $contrib->raw_end_date->format('Y-m-d'));

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

        //load member from db
        $this->adh = new \Galette\Entity\Adherent($this->zdb, $this->adh->id);
        //member is now up-to-date
        $this->assertSame('active-account cotis-ok', $this->adh->getRowClass());
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
    }

    /**
     * Initialize default status in database
     *
     * @return void
     */
    protected function initStatus(): void
    {
        $status = new \Galette\Entity\Status($this->zdb);
        if (count($status->getList()) === 0) {
            //status are not yet instantiated.
            $res = $status->installInit();
            $this->assertTrue($res);
        }
    }

    /**
     * Initialize default contributions types in database
     *
     * @return void
     */
    protected function initContributionsTypes(): void
    {
        $ct = new \Galette\Entity\ContributionsTypes($this->zdb);
        if (count($ct->getCompleteList()) === 0) {
            //status are not yet instanciated.
            $res = $ct->installInit();
            $this->assertTrue($res);
        }
    }

    /**
     * Initialize default titles in database
     *
     * @return void
     */
    protected function initTitles(): void
    {
        $titles = new \Galette\Repository\Titles($this->zdb);
        if (count($titles->getList($this->zdb)) === 0) {
            $res = $titles->installInit();
            $this->assertTrue($res);
        }
    }

    /**
     * Initialize default PDF models in database
     *
     * @return void
     */
    protected function initModels(): void
    {
        $models = new \Galette\Repository\PdfModels($this->zdb, $this->preferences, $this->login);
        $res = $models->installInit(false);
        $this->assertTrue($res);
    }

    /**
     * Clean history
     *
     * @return void
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
     *
     * @return void
     */
    protected function logSuperAdmin(): void
    {
        $this->login->logAdmin('superadmin', $this->preferences);
        $this->assertTrue($this->login->isLogged());
        $this->assertTrue($this->login->isSuperAdmin());
    }
}
