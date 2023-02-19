<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Password tests
 *
 * PHP version 5
 *
 * Copyright © 2013-2023 The Galette Team
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
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2013-10-22
 */

namespace Galette\Core\test\units;

use atoum;

/**
 * Password tests class
 *
 * @category  Core
 * @name      Password
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2013-01-13
 */
class Password extends atoum
{
    private ?\Galette\Core\Password $pass = null;
    private \Galette\Core\Db $zdb;

    /**
     * Set up tests
     *
     * @param string $method Method name
     *
     * @return void
     */
    public function beforeTestMethod($method)
    {
        $this->zdb = new \Galette\Core\Db();
        $this->pass = new \Galette\Core\Password($this->zdb, false);
    }

    /**
     * Tear down tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function afterTestMethod($method)
    {
        if (TYPE_DB === 'mysql') {
            $this->array($this->zdb->getWarnings())->isIdenticalTo([]);
        }
    }

    /**
     * Test unique password generator
     *
     * @return void
     */
    public function testRandom()
    {
        $results = array();

        for ($i = 0; $i < 200; $i++) {
            $random = $this->pass->makeRandomPassword(15);
            $this->string($random)->hasLength(15);

            $exists = in_array($random, $results);
            $this->boolean($exists)->isFalse();

            $results[] = $random;
            $this->array($results)->hasSize($i + 1);
        }

        $random = $this->pass->makeRandomPassword();
        $this->string($random)->hasLength(\Galette\Core\Password::DEFAULT_SIZE);
    }

    /**
     * Create member and get its id
     *
     * @return int
     */
    private function createMember()
    {
        try {
            $this->deleteMember();
        } catch (\Exception $e) {
            //empty catch
        }

        $status = new \Galette\Entity\Status($this->zdb);
        if (count($status->getList()) === 0) {
            $res = $status->installInit();
            $this->boolean($res)->isTrue();
        }
        $insert = $this->zdb->insert(\Galette\Entity\Adherent::TABLE);
        $insert->values(
            [
                'nom_adh'   => 'Test password user',
                'login_adh' => 'test_password_user',
                'adresse_adh' => 'The address'
            ]
        );
        $this->zdb->execute($insert);

        if ($this->zdb->isPostgres()) {
            return $this->zdb->driver->getLastGeneratedValue(
                PREFIX_DB . 'adherents_id_seq'
            );
        } else {
            return $this->zdb->driver->getLastGeneratedValue();
        }
    }

    /**
     * Delete member
     *
     * @return void
     */
    private function deleteMember()
    {
        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['login_adh' => 'test_password_user']);
        $this->zdb->execute($delete);
    }

    /**
     * Test new Password generation
     *
     * @return void
     */
    public function testGenerateNewPassword()
    {
        $id_adh = $this->createMember();
        $pass = $this->pass;
        $res = $pass->generateNewPassword($id_adh);
        $this->boolean($res)->isTrue();
        $new_pass = $pass->getNewPassword();
        $this->string($new_pass)
            ->hasLength($pass::DEFAULT_SIZE);
        $hash = $pass->getHash();
        $this->string($hash)->hasLength(60);

        $is_valid = $pass->isHashValid($hash);
        $this->variable($is_valid)->isNotNull();

        $select = $this->zdb->select(\Galette\Core\Password::TABLE);
        $results = $this->zdb->execute($select);
        $this->integer($results->count())->isIdenticalTo(1);

        $removed = $pass->removeHash($hash);
        $this->boolean($removed)->isTrue();

        $results = $this->zdb->execute($select);
        $this->integer($results->count())->isIdenticalTo(0);

        $this->deleteMember();
    }

    /**
     * Test cleanExpired
     *
     * @return void
     */
    public function testCleanExpired()
    {
        $id_adh = $this->createMember();

        $date = new \DateTime();
        $date->sub(new \DateInterval('PT48H'));

        $insert = $this->zdb->insert(\Galette\Core\Password::TABLE);
        $insert->values(
            [
                \Galette\Core\Password::PK  => $id_adh,
                'date_crea_tmp_passwd'      => $date->format('Y-m-d'),
                'tmp_passwd'                => 'azerty'
            ]
        );
        $this->zdb->execute($insert);

        $select = $this->zdb->select(\Galette\Core\Password::TABLE);
        $results = $this->zdb->execute($select);
        $this->integer($results->count())->isIdenticalTo(1);

        $pass = new \Galette\Core\Password($this->zdb, true);

        $results = $this->zdb->execute($select);
        $this->integer($results->count())->isIdenticalTo(0);

        $this->deleteMember();
    }

    /**
     * Generate new password that throws an exception
     *
     * @return void
     */
    public function testGenerateNewPasswordWException()
    {
        $this->zdb = new \mock\Galette\Core\Db();
        $this->calling($this->zdb)->execute = function ($o) {
            throw new \LogicException('Error executing query!', 123);
        };

        $pass = new \Galette\Core\Password($this->zdb, false);
        $res = $pass->generateNewPassword(12);
        $this->boolean($res)->isFalse();
    }

    /**
     * Generate new password when insert returns false
     *
     * @return void
     */
    public function testGenerateNewPasswordWFalseInsert()
    {
        $this->zdb = new \mock\Galette\Core\Db();
        $this->calling($this->zdb)->execute = function ($o) {
            throw new \Exception('Ba. Da. Boum.');
        };

        $pass = new \Galette\Core\Password($this->zdb, false);
        $res = $pass->generateNewPassword(12);
        $this->boolean($res)->isFalse();
    }

    /**
     * Test cleanExpired that throws an exception
     *
     * @return void
     */
    public function testCleanExpiredWException()
    {
        $this->zdb = new \mock\Galette\Core\Db();
        $this->calling($this->zdb)->execute = function ($o) {
            throw new \LogicException('Error executing query!', 123);
        };

        $pass = new \Galette\Core\Password($this->zdb, true);
    }

    /**
     * Test hash validity that throws an exception
     *
     * @return void
     */
    public function testIsHashValidWException()
    {
        $this->zdb = new \mock\Galette\Core\Db();
        $this->calling($this->zdb)->execute = function ($o) {
            throw new \LogicException('Error executing query!', 123);
        };

        $pass = new \Galette\Core\Password($this->zdb, false);
        $res = $pass->isHashValid('thehash');
        $this->boolean($res)->isFalse();
    }

    /**
     * Test hash removal that throws an exception
     *
     * @return void
     */
    public function testRemoveHashWException()
    {
        $this->zdb = new \mock\Galette\Core\Db();
        $this->calling($this->zdb)->execute = function ($o) {
            throw new \LogicException('Error executing query!', 123);
        };

        $pass = new \Galette\Core\Password($this->zdb, false);
        $res = $pass->removeHash('thehash');
        $this->boolean($res)->isFalse();
    }
}
