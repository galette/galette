<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Util;

use Galette\Tests\GaletteTestCase;
use Galette\Core\Preferences;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Password tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Password extends GaletteTestCase
{
    /**
     * Passwords data provider
     *
     * @return array<int, array{0: int, 1: string, 2: array<int, string>}>
     */
    public static function passProvider(): array
    {
        return [
            // [strength, password, errors]
            [Preferences::PWD_WEAK, 'weaker', ['A', '1', '@']],
            [Preferences::PWD_WEAK, '123456', ['nl', '@']],
            [Preferences::PWD_WEAK, '²²²²²²', ['nl', '@']],
            [Preferences::PWD_WEAK, 'foobar', ['A', '1', '@']],
            [Preferences::PWD_WEAK, 'ömgwat', ['A', '1', '@']],
            [Preferences::PWD_WEAK, '!.!.!.', ['nl', '1']],
            [Preferences::PWD_WEAK, '!.!.!﴾', ['nl', '1']],
            [Preferences::PWD_WEAK, '7857375923752947', ['nl', '@']],
            [Preferences::PWD_WEAK, 'FSDFJSLKFFSDFDSF', ['a', '1', '@']],
            [Preferences::PWD_WEAK, 'FÜKFJSLKFFSDFDSF', ['a', '1', '@']],
            [Preferences::PWD_WEAK, 'fjsfjdljfsjsjjlsj', ['A', '1', '@']],

            [Preferences::PWD_MEDIUM, 'wee6eak', ['A', '@']],
            [Preferences::PWD_MEDIUM, 'foobar!', ['A', '1']],
            [Preferences::PWD_MEDIUM, 'Foobar', ['1', '@']],
            [Preferences::PWD_MEDIUM, '123456!', ['nl']],
            [Preferences::PWD_MEDIUM, 'fjsfjdljfsjsjjls1', ['A', '@']],
            [Preferences::PWD_MEDIUM, '785737592375294b', ['A', '@']],

            [Preferences::PWD_STRONG, 'Foobar﴾', ['1']],
            [Preferences::PWD_STRONG, 'foo-b0r!', ['A']],

            [Preferences::PWD_VERY_STRONG, 'Foobar!55!', []],
            [Preferences::PWD_VERY_STRONG, 'Foobar$55', []],
            [Preferences::PWD_VERY_STRONG, 'Foobar€55', []],
            [Preferences::PWD_VERY_STRONG, 'Foobar€55', []],
            [Preferences::PWD_VERY_STRONG, 'Foobar$55_4&F', []],
            [Preferences::PWD_VERY_STRONG, 'L33RoyJ3Jenkins!', []],
        ];
    }

    /**
     * Test password validation
     *
     * @param int                $level  Password level
     * @param string             $pass   Password
     * @param array<int, string> $errors Errors
     */
    #[DataProvider('passProvider')]
    public function testValidatePassword(int $level, string $pass, array $errors): void
    {
        //error messages mapping
        foreach ($errors as &$err) {
            switch ($err) {
                case 'nl':
                    $err = 'Does not contains letters';
                    break;
                case 'a':
                    $err = 'Does not contains lowercase letters';
                    break;
                case 'A':
                    $err = 'Does not contains uppercase letters';
                    break;
                case 1:
                    $err = 'Does not contains numbers';
                    break;
                case '@':
                    $err = 'Does not contains special characters';
                    break;
            }
        }

        if ($level < Preferences::PWD_VERY_STRONG) {
            $this->preferences->pref_password_strength = $level + 1;
            $password = new \Galette\Util\Password($this->preferences);
            $this->assertFalse($password->isValid($pass));
            $this->assertEquals($errors, $password->getErrors());
        }

        $this->preferences->pref_password_strength = $level;
        $password = new \Galette\Util\Password($this->preferences);
        $this->assertTrue($password->isValid($pass), implode(', ', $password->getErrors()));
        $this->assertSame([], $password->getErrors());
        $this->assertEquals($errors, $password->getStrenghtErrors());
    }

    /**
     * Blacklist password provider
     *
     * @return array<int, array{0: string, 1: bool}>
     */
    public static function blacklistProvider(): array
    {
        return [
            ['galette', true],
            ['toto', false],
            ['mypassisgreat', false],
            ['starwars', true],
            ['123456', true],
            ['588795', false]
        ];
    }

    /**
     * Test password blacklist
     *
     * @param string $pass     Password to test
     * @param bool   $expected Expected return
     */
    #[DataProvider('blacklistProvider')]
    public function testBlacklist(string $pass, bool $expected): void
    {
        $this->preferences->pref_password_blacklist = true;
        $password = new \Galette\Util\Password($this->preferences);
        $this->assertSame($expected, $password->isBlacklisted($pass), $pass);

        $this->preferences->pref_password_blacklist = false;
        $password = new \Galette\Util\Password($this->preferences);
        $this->assertFalse($password->isBlacklisted($pass));
    }

    /**
     * Test with personal information
     */
    public function testPersonalInformation(): void
    {
        $infos = [
            'mylogin',
            'myname',
            'mysurname',
            'mynickname'
        ];

        $this->preferences->pref_password_strength = Preferences::PWD_NONE;
        $password = new \Galette\Util\Password($this->preferences);
        $password->addPersonalInformation($infos);
        foreach ($infos as $info) {
            $this->assertTrue($password->isValid($info), implode(', ', $password->getErrors()));
            $this->assertSame([], $password->getErrors());
        }

        $this->preferences->pref_password_strength = Preferences::PWD_WEAK;
        $password = new \Galette\Util\Password($this->preferences);
        $password->addPersonalInformation($infos);
        foreach ($infos as $info) {
            $this->assertFalse($password->isValid($info));
            $this->assertEquals(
                ['Do not use any of your personal information as password!'],
                $password->getErrors()
            );
        }

        $this->assertFalse($password->isValid('MyLoGiN'));
        $this->assertTrue($password->isValid('iMyLoGiN'));

        //create member
        global $zdb, $login, $i18n; // globals :(
        $zdb = $this->zdb;
        $i18n = new \Galette\Core\I18n(
            \Galette\Core\I18n::DEFAULT_LANG
        );
        $login = new \Galette\Core\Login($this->zdb, $i18n);
        $history = new \Galette\Core\History($this->zdb, $login, $this->preferences);
        include GALETTE_ROOT . 'includes/fields_defs/members_fields.php';

        $adh = new \Galette\Entity\Adherent($this->zdb);
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $history
        );

        $adh_data = [
            'nom_adh'       => 'Pignon',
            'prenom_adh'    => 'Jean-Cloud Juste',
            'ddn_adh'       => '1980-05-01',
            'ville_adh'     => 'Paris',
            'pseudo_adh'    => 'petit-cheval-de-manège',
            'login_adh'     => 'log_In',
            'email_adh'     => 'mail@galette.eu',
            //required for check to work
            'date_crea_adh' => date('Y-m-d'),
            'sexe_adh'      => \Galette\Entity\Adherent::NC
        ];
        $check = $adh->check($adh_data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $password = new \Galette\Util\Password($this->preferences);
        $password->setAdherent($adh);

        unset($adh_data['date_crea_adh']);
        unset($adh_data['sexe_adh']);
        //add compounds
        $adh_data['c00'] = 'jean-cloud justepignon';
        $adh_data['c000'] = 'pignonjean-cloud juste';
        $adh_data['c01'] = 'jcjpignon';
        $adh_data['c02'] = 'pignonjcj';
        $adh_data['c03'] = 'pignonj';
        $adh_data['c04'] = 'jpignon';

        $adh_data['c10'] = 'log_inpignon';
        $adh_data['c100'] = 'pignonlog_in';
        $adh_data['c11'] = 'pignonli';
        $adh_data['c12'] = 'lipignon';
        $adh_data['c13'] = 'lpignon';
        $adh_data['c14'] = 'pignonl';

        $adh_data['c20'] = 'petit-cheval-de-manègepignon';
        $adh_data['c200'] = 'pignonpetit-cheval-de-manège';
        $adh_data['c21'] = 'pignonpcdm';
        $adh_data['c22'] = 'pcdmpignon';
        $adh_data['c23'] = 'ppignon';
        $adh_data['c24'] = 'pignonp';

        foreach ($adh_data as $key => $data) {
            $this->assertFalse($password->isValid($data), $key);
        }

        $this->assertFalse($password->isValid('19800501'));
        $this->assertFalse($password->isValid('01051980'));
    }
}
