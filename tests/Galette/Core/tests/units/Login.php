<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

namespace Galette\Core\test\units;

use Galette\GaletteTestCase;

/**
 * Login tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Login extends GaletteTestCase
{
    protected int $seed = 320112365;
    private string $login_adh = 'dumas.roger';
    private string $mdp_adh = 'sd8)AvtE|*';

    /**
     * Cleanup after tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->zdb = new \Galette\Core\Db();
        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        parent::tearDown();
    }

    /**
     * Test defaults
     *
     * @return void
     */
    public function testDefaults(): void
    {
        $this->assertFalse($this->login->isLogged());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isSuperAdmin());
        $this->assertFalse($this->login->isActive());
        $this->assertFalse($this->login->isCron());
        $this->assertFalse($this->login->isUp2Date());
        $this->assertFalse($this->login->isImpersonated());
        $this->assertNull($this->login->lang);
    }

    /**
     * Test not logged-in users Impersonating
     *
     * @return void
     */
    public function testNotLoggedCantImpersonate(): void
    {
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isLogged'))
            ->getMock();
        $login->method('isLogged')->willReturn(false);

        $this->expectExceptionMessage('Only superadmin can impersonate!');
        $login->impersonate(1);
    }

    /**
     * Test staff users Impersonating
     *
     * @return void
     */
    public function testStaffCantImpersonate(): void
    {
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);

        $this->expectExceptionMessage('Only superadmin can impersonate!');
        $login->impersonate(1);
    }

    /**
     * Test admin users Impersonating
     *
     * @return void
     */
    public function testAdminCantImpersonate(): void
    {
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(true);
        $login->method('isSuperAdmin')->willReturn(false);

        $this->expectExceptionMessage('Only superadmin can impersonate!');
        $login->impersonate(1);
    }

    /**
     * Test Impersonating that throws an exception
     *
     * @return void
     */
    public function testImpersonateExistsWException(): void
    {
        $zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(array('execute'))
            ->getMock();

        $zdb->method('execute')
            ->willReturnCallback(
                function ($o): void {
                    throw new \LogicException('Error executing query!', 123);
                }
            );

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($zdb, $this->i18n))
            ->onlyMethods(array('isSuperAdmin'))
            ->getMock();

        $login->method('isSuperAdmin')->willReturn(true);

        $this->assertFalse($login->impersonate(1));
        $this->expectLogEntry(\Analog::WARNING, 'An error occurred: Error executing query!');
        $this->expectLogEntry(\Analog::ERROR, 'Galette\Core\Login->impersonate()');
    }

    /**
     * Test superadmin users Impersonating
     *
     * @return void
     */
    public function testSuperadminCanImpersonate(): void
    {
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isSuperAdmin'))
            ->getMock();

        $login->method('isSuperAdmin')->willReturn(true);

        //We're faking, Impersonating won't work but will not throw any exception
        $this->assertFalse($login->impersonate(1));
        $this->expectLogEntry(\Analog::WARNING, 'No entry found for id `1`');
    }

    /**
     * Test return requesting a non-existing property
     *
     * @return void
     */
    public function testInexistingGetter(): void
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Property doesnotexists is not set!');
        $this->assertFalse($this->login->doesnotexists);
    }

    /**
     * Test login exists
     *
     * @return void
     */
    public function testLoginExists(): void
    {
        $this->assertFalse($this->login->loginExists('exists'));
        $this->assertFalse($this->login->loginExists('doesnotexists'));
    }

    /**
     * Test login exists that throws an exception
     *
     * @return void
     */
    public function testLoginExistsWException(): void
    {
        $zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(array('execute'))
            ->getMock();

        $zdb->method('execute')
            ->willReturnCallback(
                function ($o): void {
                    if ($o instanceof \Laminas\Db\Sql\Select) {
                        throw new \LogicException('Error executing query!', 123);
                    }
                }
            );

        $login = new \Galette\Core\Login($zdb, $this->i18n);
        $this->assertTrue($login->loginExists('doesnotexists'));
        $this->expectLogEntry(\Analog::WARNING, 'Cannot check if login exists | Error executing query!');
    }

    /**
     * Test login as super admin
     *
     * @return void
     */
    public function testLogAdmin(): void
    {
        $this->login->logAdmin('superadmin', $this->preferences);
        $this->assertTrue($this->login->isLogged());
        $this->assertFalse($this->login->isStaff());
        $this->assertTrue($this->login->isAdmin());
        $this->assertTrue($this->login->isSuperAdmin());
        $this->assertTrue($this->login->isActive());
        $this->assertFalse($this->login->isCron());
        $this->assertFalse($this->login->isUp2Date());
        $this->assertFalse($this->login->isImpersonated());
        $this->assertSame($this->preferences->pref_lang, $this->login->lang);

        //test logout
        $this->login->logOut();
        $this->testDefaults();
    }

    /**
     * Creates or load test user
     *
     * @return void
     */
    private function createUser(): void
    {
        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE, 'a');
        $select->where(array('a.fingerprint' => 'FAKER' . $this->seed));
        $results = $this->zdb->execute($select);

        global $zdb, $login, $hist, $i18n; // globals :(
        $zdb = $this->zdb;
        $login = $this->login;
        $hist = $this->history;
        $i18n = $this->i18n;

        if ($results->count() === 0) {
            $status = new \Galette\Entity\Status($this->zdb);
            if (count($status->getList()) === 0) {
                $res = $status->installInit();
                $this->assertTrue($res);
            }

            $data = [
                'nom_adh' => 'Barre',
                'prenom_adh' => 'Olivier',
                'ville_adh' => 'Le GoffVille',
                'cp_adh' => '05 029',
                'adresse_adh' => '9, impasse Frédérique Boulanger',
                'email_adh' => 'bernadette37@hernandez.fr',
                'login_adh' => 'dumas.roger',
                'mdp_adh' => 'sd8)AvtE|*',
                'mdp_adh2' => 'sd8)AvtE|*',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 1,
                'prof_adh' => 'Pédologue',
                'titre_adh' => null,
                'ddn_adh' => '1948-10-23',
                'lieu_naissance' => 'Lagarde',
                'pseudo_adh' => 'elisabeth50',
                'pays_adh' => 'Géorgie',
                'tel_adh' => '05 05 20 88 04',
                'activite_adh' => true,
                'id_statut' => 6,
                'date_crea_adh' => '2019-09-02',
                'pref_lang' => 'nb_NO',
                'fingerprint' => 'FAKER' . $this->seed,
            ];

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
        } else {
            $this->adh = new \Galette\Entity\Adherent($this->zdb, $results->current());
        }
    }

    /**
     * Look for a login that does exist
     *
     * @return void
     */
    public function testLoginExistsDb(): void
    {
        $this->createUser();
        $this->assertTrue($this->login->loginExists('dumas.roger'));
    }

    /**
     * Test user login
     *
     * @return void
     */
    public function testLogin(): void
    {
        $this->createUser();
        $this->assertFalse($this->login->login('doenotexists', 'empty'));
        $this->assertTrue($this->login->login($this->login_adh, $this->mdp_adh));
        $this->expectLogEntry(\Analog::WARNING, 'No entry found for login `doenotexists`');
    }

    /**
     * Test logged user name
     *
     * @return void
     */
    public function testLoggedInAs(): void
    {
        global $translator;

        $this->createUser();
        $this->assertTrue($this->login->login($this->login_adh, $this->mdp_adh));

        /** Should get message in the right locale but doesn't... */
        $this->i18n->changeLanguage('en_US');
        $tstring = $translator->translate(
            "Logged in as:<br/>%login",
            'galette',
            $this->login->lang
        );
        $this->assertSame(
            str_replace(
                '%login',
                'Barre Olivier (dumas.roger)',
                $tstring
            ),
            $this->login->loggedInAs()
        );
        $this->assertSame('Barre Olivier (dumas.roger)', $this->login->loggedInAs(true));
    }

    /**
     * Test login from cron
     *
     * @return void
     */
    public function testLogCron(): void
    {
        $this->login->logCron('reminder', $this->preferences);
        $this->assertTrue($this->login->isLogged());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isSuperAdmin());
        $this->assertFalse($this->login->isActive());
        $this->assertTrue($this->login->isCron());
        $this->assertFalse($this->login->isUp2Date());
        $this->assertFalse($this->login->isImpersonated());
        $this->assertSame('cron', $this->login->login);
        $this->assertSame($this->preferences->pref_lang, $this->login->lang);

        $this->expectException('Exception');
        $this->expectExceptionMessage('Not authorized!');
        $this->login->logCron('filename', $this->preferences);
    }
}
