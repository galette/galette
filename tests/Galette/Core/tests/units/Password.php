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

namespace Galette\Core\test\units;

use PHPUnit\Framework\TestCase;

/**
 * Password tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Password extends TestCase
{
    private ?\Galette\Core\Password $pass = null;
    private \Galette\Core\Db $zdb;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->zdb = new \Galette\Core\Db();
        $this->pass = new \Galette\Core\Password($this->zdb, false);
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        if (TYPE_DB === 'mysql') {
            $this->assertSame([], $this->zdb->getWarnings());
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
            $this->assertSame(15, strlen($random));

            $exists = in_array($random, $results);
            $this->assertFalse($exists);

            $results[] = $random;
            $this->assertCount($i + 1, $results);
        }

        $random = $this->pass->makeRandomPassword();
        $this->assertSame(\Galette\Core\Password::DEFAULT_SIZE, strlen($random));
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
            $this->assertTrue($res);
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
        $this->assertTrue($res);
        $new_pass = $pass->getNewPassword();
        $this->assertSame($pass::DEFAULT_SIZE, strlen($new_pass));
        $hash = $pass->getHash();
        $this->assertSame(60, strlen($hash));

        $is_valid = $pass->isHashValid($hash);
        $this->assertNotNull($is_valid);

        $select = $this->zdb->select(\Galette\Core\Password::TABLE);
        $results = $this->zdb->execute($select);
        $this->assertSame(1, $results->count());

        $removed = $pass->removeHash($hash);
        $this->assertTrue($removed);

        $results = $this->zdb->execute($select);
        $this->assertSame(0, $results->count());

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
        $this->assertSame(1, $results->count());

        $pass = new \Galette\Core\Password($this->zdb, true);

        $results = $this->zdb->execute($select);
        $this->assertSame(0, $results->count());

        $this->deleteMember();
    }

    /**
     * Generate new password that throws an exception
     *
     * @return void
     */
    public function testGenerateNewPasswordWException()
    {
        $this->zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(array('execute'))
            ->getMock();

        $this->zdb->method('execute')
            ->will(
                $this->returnCallback(
                    function ($o) {
                        throw new \LogicException('Error executing query!', 123);
                    }
                )
            );

        $pass = new \Galette\Core\Password($this->zdb, false);
        $res = $pass->generateNewPassword(12);
        $this->assertFalse($res);
    }

    /**
     * Generate new password when insert returns false
     *
     * @return void
     */
    public function testGenerateNewPasswordWFalseInsert()
    {
        $this->zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(array('execute'))
            ->getMock();

        $this->zdb->method('execute')
            ->will(
                $this->returnCallback(
                    function ($o) {
                        throw new \LogicException('Error executing query!', 123);
                    }
                )
            );

        $pass = new \Galette\Core\Password($this->zdb, false);
        $res = $pass->generateNewPassword(12);
        $this->assertFalse($res);
    }

    /**
     * Test cleanExpired that throws an exception
     *
     * @return void
     */
    public function testCleanExpiredWException()
    {
        $this->zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(array('execute'))
            ->getMock();

        $this->zdb->method('execute')
            ->will(
                $this->returnCallback(
                    function ($o) {
                        throw new \LogicException('Error executing query!', 123);
                    }
                )
            );

        $pass = new \Galette\Core\Password($this->zdb, false);
        $this->assertFalse($pass->cleanExpired());
    }

    /**
     * Test hash validity that throws an exception
     *
     * @return void
     */
    public function testIsHashValidWException()
    {
        $this->zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(array('execute'))
            ->getMock();

        $this->zdb->method('execute')
            ->will(
                $this->returnCallback(
                    function ($o) {
                        throw new \LogicException('Error executing query!', 123);
                    }
                )
            );

        $pass = new \Galette\Core\Password($this->zdb, false);
        $res = $pass->isHashValid('thehash');
        $this->assertFalse($res);
    }

    /**
     * Test hash removal that throws an exception
     *
     * @return void
     */
    public function testRemoveHashWException()
    {
        $this->zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(array('execute'))
            ->getMock();

        $this->zdb->method('execute')
            ->will(
                $this->returnCallback(
                    function ($o) {
                        throw new \LogicException('Error executing query!', 123);
                    }
                )
            );

        $pass = new \Galette\Core\Password($this->zdb, false);
        $res = $pass->removeHash('thehash');
        $this->assertFalse($res);
    }
}
