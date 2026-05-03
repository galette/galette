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
use Safe\DateTime;

/**
 * Password tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Password extends GaletteTestCase
{
    private ?\Galette\Core\Password $pass = null;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->pass = new \Galette\Core\Password($this->zdb, false);
    }

    /**
     * Test unique password generator
     */
    public function testRandom(): void
    {
        $results = [];

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
     */
    private function localCreateMember(): int
    {
        try {
            $this->deleteMember();
        } catch (\Exception) {
            //empty catch
        }

        $status = $this->container->get(\Galette\Entity\Status::class);
        if (count($status->getList()) === 0) {
            $res = $status->installInit();
            $this->assertTrue($res);
        }
        $insert = $this->zdb->insert(\Galette\Entity\Adherent::TABLE);
        $insert->values(
            [
                'nom_adh'   => 'Test password user',
                'login_adh' => 'test_password_user',
                'adresse_adh' => 'The address',
                \Galette\Entity\Status::PK => \Galette\Entity\Status::DEFAULT_STATUS,
                'sexe_adh' => \Galette\Entity\Adherent::MAN,
                'date_crea_adh' => date('Y-m-d'),
                'date_modif_adh' => date('Y-m-d'),
            ]
        );
        $this->zdb->execute($insert);

        if ($this->zdb->isPostgres()) {
            // @phpstan-ignore arguments.count (laminas does not respect its own interfaces)
            return (int)$this->zdb->driver->getLastGeneratedValue(
                $this->zdb->getSequenceName(\Galette\Entity\Adherent::TABLE, \Galette\Entity\Adherent::PK, true)
            );
        } else {
            return (int)$this->zdb->driver->getLastGeneratedValue();
        }
    }

    /**
     * Delete member
     */
    private function deleteMember(): void
    {
        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['login_adh' => 'test_password_user']);
        $this->zdb->execute($delete);
    }

    /**
     * Test new Password generation
     */
    public function testGenerateNewPassword(): void
    {
        $id_adh = $this->localCreateMember();
        $pass = $this->pass;
        $res = $pass->generateNewPassword($id_adh);
        $this->assertTrue($res);
        $new_pass = $pass->getNewPassword();
        $this->assertSame($pass::DEFAULT_SIZE, strlen($new_pass));
        $hash = $pass->getHash();
        $this->assertSame(60, strlen($hash));

        $is_valid = $pass->isHashValid($hash);
        $this->assertNotFalse($is_valid);

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
     */
    public function testCleanExpired(): void
    {
        $id_adh = $this->localCreateMember();

        $date = new DateTime();
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

        new \Galette\Core\Password($this->zdb, true);

        $results = $this->zdb->execute($select);
        $this->assertSame(0, $results->count());

        $this->deleteMember();
    }

    /**
     * Generate new password that throws an exception
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGenerateNewPasswordWException(): void
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

        $pass = new \Galette\Core\Password($zdb, false);
        $res = $pass->generateNewPassword(12);
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Error executing query!');
        $this->assertFalse($res);
    }

    /**
     * Test cleanExpired that throws an exception
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testCleanExpiredWException(): void
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

        $pass = new \Galette\Core\Password($zdb, false);
        $this->assertFalse($pass->cleanExpired());
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Error executing query!');
    }

    /**
     * Test hash validity that throws an exception
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testIsHashValidWException(): void
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

        $pass = new \Galette\Core\Password($zdb, false);
        $res = $pass->isHashValid('thehash');
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Error executing query!');
        $this->assertFalse($res);
    }

    /**
     * Test hash removal that throws an exception
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testRemoveHashWException(): void
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

        $pass = new \Galette\Core\Password($zdb, false);
        $res = $pass->removeHash('thehash');
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Error executing query!');
        $this->assertFalse($res);
    }
}
