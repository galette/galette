<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

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
     * Test defaults
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
        $this->assertNull($this->login->lang); // @phpstan-ignore method.impossibleType (__get explicitly return null)
    }

    /**
     * Test not logged-in users Impersonating
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testNotLoggedCantImpersonate(): void
    {
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, $this->i18n])
            ->onlyMethods(['isLogged'])
            ->getMock();
        $login->method('isLogged')->willReturn(false);

        $this->expectExceptionMessage('Only superadmin can impersonate!');
        $login->impersonate(1);
    }

    /**
     * Test staff users Impersonating
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testStaffCantImpersonate(): void
    {
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, $this->i18n])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
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
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testAdminCantImpersonate(): void
    {
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, $this->i18n])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
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
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testImpersonateExistsWException(): void
    {
        $zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(['execute'])
            ->getMock();

        $zdb->method('execute')
            ->willReturnCallback(
                function (): void {
                    throw new \LogicException('Error executing query!', 123);
                }
            );

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$zdb, $this->i18n])
            ->onlyMethods(['isSuperAdmin'])
            ->getMock();

        $login->method('isSuperAdmin')->willReturn(true);

        $this->assertFalse($login->impersonate(1));
        $this->expectLogEntry(\Analog\Analog::WARNING, 'An error occurred: Error executing query!');
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Galette\Core\Login->impersonate()');
    }

    /**
     * Test superadmin users Impersonating
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testSuperadminCanImpersonate(): void
    {
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, $this->i18n])
            ->onlyMethods(['isSuperAdmin'])
            ->getMock();

        $login->method('isSuperAdmin')->willReturn(true);

        //We're faking, Impersonating won't work but will not throw any exception
        $this->assertFalse($login->impersonate(1));
        $this->expectLogEntry(\Analog\Analog::WARNING, 'No entry found for id `1`');
    }

    /**
     * Test return requesting a non-existing property
     */
    public function testInexistingGetter(): void
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Property doesnotexists is not set!');
        $this->assertFalse($this->login->doesnotexists); // @phpstan-ignore property.notFound (class handle that)
    }

    /**
     * Test login exists
     */
    public function testLoginExists(): void
    {
        $this->assertFalse($this->login->loginExists('exists'));
        $this->assertFalse($this->login->loginExists('doesnotexists'));
    }

    /**
     * Test login exists that throws an exception
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testLoginExistsWException(): void
    {
        $zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(['execute'])
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
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Cannot check if login exists | Error executing query!');
    }

    /**
     * Test login as super admin
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
     */
    private function createUser(): void
    {
        $this->logSuperAdmin();

        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE, 'a');
        $select->where(['a.fingerprint' => 'FAKER' . $this->seed]);
        $results = $this->zdb->execute($select);

        if ($results->count() === 0) {
            $status = $this->container->get(\Galette\Entity\Status::class);
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

        $this->login->logOut();
    }

    /**
     * Look for a login that does exist
     */
    public function testLoginExistsDb(): void
    {
        $this->createUser();
        $this->assertTrue($this->login->loginExists('dumas.roger'));
    }

    /**
     * Test user login
     */
    public function testLogin(): void
    {
        $this->createUser();
        $this->assertFalse($this->login->login('doenotexists', 'empty'));
        $this->assertTrue($this->login->login($this->login_adh, $this->mdp_adh));
        $this->expectLogEntry(\Analog\Analog::WARNING, 'No entry found for login `doenotexists`');
    }

    /**
     * A member holding an enabled second factor is not logged in on credentials
     * alone. isLogged() is the gate every middleware and template goes through,
     * so anything calling logIn() then isLogged() fails closed on its own.
     */
    public function testEnrolledMemberOwesASecondFactor(): void
    {
        global $preferences;

        $this->createUser();
        $this->login->logOut();
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;

        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE);
        $select->columns([\Galette\Entity\Adherent::PK])->where([\Galette\Core\Login::PK => $this->login_adh]);
        $id_adh = (int)$this->zdb->execute($select)->current()->id_adh;

        $secret = new \Galette\Core\TwoFactorSecret($this->zdb);
        $secret->create($id_adh, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');

        //not confirmed yet: nothing is owed, enrolment must not lock anyone out
        $this->assertTrue($this->login->logIn($this->login_adh, $this->mdp_adh));
        $this->assertTrue($this->login->isLogged());
        $this->assertFalse($this->login->isTwoFactorPending());
        $this->login->logOut();

        $secret->enable();

        //now credentials alone are not enough
        $this->assertTrue($this->login->logIn($this->login_adh, $this->mdp_adh));
        $this->assertFalse($this->login->isLogged());
        $this->assertTrue($this->login->isTwoFactorPending());
        //identity is known though, the challenge needs it
        $this->assertSame($this->login_adh, $this->login->login);

        $this->login->validateTwoFactor();
        $this->assertTrue($this->login->isLogged());
        $this->assertFalse($this->login->isTwoFactorPending());

        //and logging out clears the state
        $this->login->logOut();
        $this->assertFalse($this->login->isLogged());
        $this->assertFalse($this->login->isTwoFactorPending());
    }

    /**
     * A session owing a second factor must hold none of its privileges:
     * several routes carry no middleware and are gated on those predicates
     * alone -- public pages, and the public documents
     */
    public function testPendingSessionHoldsNoPrivilege(): void
    {
        $login = new class ($this->zdb, $this->i18n) extends \Galette\Core\Login {
            /**
             * Pretend a fully privileged account has just given its password
             */
            public function grantEverything(): void
            {
                $this->logged = true;
                $this->admin = true;
                $this->superadmin = true;
                $this->staff = true;
                $this->uptodate = true;
                $this->managed_groups = [1];
            }
        };

        $login->grantEverything();
        $this->assertSame(\Galette\Core\Authentication::ACCESS_SUPERADMIN, $login->getAccessLevel());

        $login->requireTwoFactor();

        $this->assertFalse($login->isLogged());
        $this->assertFalse($login->isAdmin());
        $this->assertFalse($login->isSuperAdmin());
        $this->assertFalse($login->isStaff());
        $this->assertFalse($login->isUp2Date());
        $this->assertFalse($login->isGroupManager());
        $this->assertFalse($login->isGroupManager(1));
        //which is what public pages and public documents rely on
        $this->assertSame(\Galette\Core\Authentication::ACCESS_PUBLIC, $login->getAccessLevel());

        //the account is still known, so the second factor can be looked up
        //where it is kept
        $this->assertTrue($login->isSuperAdminAccount());

        $login->validateTwoFactor();

        $this->assertTrue($login->isLogged());
        $this->assertTrue($login->isAdmin());
        $this->assertTrue($login->isSuperAdmin());
        $this->assertTrue($login->isStaff());
        $this->assertTrue($login->isUp2Date());
        $this->assertTrue($login->isGroupManager());
        $this->assertSame(\Galette\Core\Authentication::ACCESS_SUPERADMIN, $login->getAccessLevel());
    }

    /**
     * A mandatory policy is held back by a flag, and reads as optional: the
     * member enrolled while it applied is still asked for a code. Reading it as
     * disabled would drop that protection without saying so.
     */
    public function testMandatoryPolicyStillChallengesWithoutTheFlag(): void
    {
        global $preferences;

        $this->createUser();
        $this->login->logOut();
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_REQUIRED_ALL;

        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE);
        $select->columns([\Galette\Entity\Adherent::PK])->where([\Galette\Core\Login::PK => $this->login_adh]);
        $id_adh = (int)$this->zdb->execute($select)->current()->id_adh;

        $secret = new \Galette\Core\TwoFactorSecret($this->zdb);
        $secret->create($id_adh, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
        $secret->enable();

        try {
            \Galette\Core\TwoFactorAuth::forceRequiredAvailable(available: false);

            $this->assertTrue($this->login->logIn($this->login_adh, $this->mdp_adh));
            $this->assertFalse($this->login->isLogged());
            $this->assertTrue($this->login->isTwoFactorPending());
            $this->login->logOut();

            //same answer when the policy has to be read from the table rather
            //than from the global, which is the one read that bypasses
            //Preferences entirely
            $update = $this->zdb->update(\Galette\Core\Preferences::TABLE);
            $update->set(['val_pref' => (string)\Galette\Core\TwoFactorAuth::MODE_REQUIRED_ALL])
                ->where(['nom_pref' => 'pref_2fa_mode']);
            $this->zdb->execute($update);

            $kept = $preferences;
            $preferences = null;
            try {
                $this->assertTrue($this->login->logIn($this->login_adh, $this->mdp_adh));
                $this->assertTrue($this->login->isTwoFactorPending());
            } finally {
                $preferences = $kept;
                $update = $this->zdb->update(\Galette\Core\Preferences::TABLE);
                $update->set(['val_pref' => (string)\Galette\Core\TwoFactorAuth::MODE_DISABLED])
                    ->where(['nom_pref' => 'pref_2fa_mode']);
                $this->zdb->execute($update);
            }
        } finally {
            \Galette\Core\TwoFactorAuth::forceRequiredAvailable(available: true);
            $this->login->logOut();
            $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_DISABLED;
        }
    }

    /**
     * Turning the feature off globally must let enrolled members back in: it is
     * the escape hatch when something goes wrong instance wide
     */
    public function testDisabledPolicySkipsTheSecondFactor(): void
    {
        global $preferences;

        $this->createUser();
        $this->login->logOut();

        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE);
        $select->columns([\Galette\Entity\Adherent::PK])->where([\Galette\Core\Login::PK => $this->login_adh]);
        $id_adh = (int)$this->zdb->execute($select)->current()->id_adh;

        $secret = new \Galette\Core\TwoFactorSecret($this->zdb);
        $secret->create($id_adh, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
        $secret->enable();

        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_DISABLED;

        $this->assertTrue($this->login->logIn($this->login_adh, $this->mdp_adh));
        $this->assertTrue($this->login->isLogged());
        $this->assertFalse($this->login->isTwoFactorPending());
    }

    /**
     * The pending state must survive the session round trip, and a session
     * written before the property existed must still unserialize
     */
    public function testPendingStateSurvivesSerialization(): void
    {
        global $preferences;

        $this->createUser();
        $this->login->logOut();
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;

        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE);
        $select->columns([\Galette\Entity\Adherent::PK])->where([\Galette\Core\Login::PK => $this->login_adh]);
        $id_adh = (int)$this->zdb->execute($select)->current()->id_adh;

        $secret = new \Galette\Core\TwoFactorSecret($this->zdb);
        $secret->create($id_adh, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
        $secret->enable();

        $this->login->logIn($this->login_adh, $this->mdp_adh);
        $this->assertTrue($this->login->isTwoFactorPending());

        $revived = unserialize(serialize($this->login));
        $this->assertInstanceOf(\Galette\Core\Login::class, $revived);
        $this->assertFalse($revived->isLogged());
        $this->assertTrue($revived->isTwoFactorPending());

        //a session written before this property existed carries no value for
        //it. PHP applies declared defaults to properties absent from a payload,
        //so what has to hold is that a default is declared: without one the
        //property would come back uninitialized and every read would throw.
        $property = new \ReflectionProperty(\Galette\Core\Authentication::class, 'tfa_pending');
        $this->assertTrue($property->hasDefaultValue(), 'tfa_pending must declare a default');
        $this->assertFalse($property->getDefaultValue());
    }

    /**
     * Passwords hashed with md5 are no longer accepted. Such hashes may still
     * exist on instances upgraded from Galette older than 0.7.4, since nothing
     * ever re-hashed them.
     */
    public function testLegacyMd5PasswordIsRejected(): void
    {
        $this->createUser();
        $this->login->logOut();

        $update = $this->zdb->update(\Galette\Entity\Adherent::TABLE);
        $update->set(['mdp_adh' => md5($this->mdp_adh)])
            ->where([\Galette\Core\Login::PK => $this->login_adh]);
        $this->zdb->execute($update);

        $this->assertFalse($this->login->logIn($this->login_adh, $this->mdp_adh));
        $this->assertFalse($this->login->isLogged());
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Passwords mismatch for login `' . $this->login_adh . '`'
        );
    }

    /**
     * Test logged user name
     */
    public function testLoggedInAs(): void
    {
        global $translator;

        $this->createUser();
        $this->assertTrue($this->login->login($this->login_adh, $this->mdp_adh));

        /* Should get message in the right locale but doesn't... */
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
        $this->assertSame('Barre Olivier (dumas.roger)', $this->login->loggedInAs(only_name: true));
    }

    /**
     * Test login from cron
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
