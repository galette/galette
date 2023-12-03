<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Reminders repository tests
 *
 * PHP version 5
 *
 * Copyright © 2020-2023 The Galette Team
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
 * @category  Repository
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-09-14
 */

namespace Galette\Repository\test\units;

use Galette\GaletteTestCase;

/**
 * Reminders repository tests
 *
 * @category  Repository
 * @name      Reminders
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-09-14
 */
class Reminders extends GaletteTestCase
{
    protected int $seed = 95842355;
    private array $ids = [];

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->initStatus();
        $this->initContributionsTypes();

        $this->contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);

        $this->adh = new \Galette\Entity\Adherent($this->zdb);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
        $this->cleanContributions();

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Reminder::TABLE);
        $this->zdb->execute($delete);
    }

    /**
     * Clean created contributions
     *
     * @return void
     */
    private function cleanContributions(): void
    {
        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where(['info_cotis' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);
    }

    /**
     * Test getList
     *
     * @return void
     */
    public function testGetList()
    {
        //impendings
        $ireminders = new \Galette\Repository\Reminders([\Galette\Entity\Reminder::IMPENDING]);
        $this->assertSame([], $ireminders->getList($this->zdb));

        //lates
        $lreminders = new \Galette\Repository\Reminders([\Galette\Entity\Reminder::LATE]);
        $this->assertSame([], $lreminders->getList($this->zdb));

        //all
        $reminders = new \Galette\Repository\Reminders();
        $this->assertSame([], $reminders->getList($this->zdb));

        //create member
        $this->getMemberTwo();
        $id = $this->adh->id;

        //create a contribution, just before being a close to be expired contribution
        $now = new \DateTime();
        $due_date = clone $now;
        $due_date->add(new \DateInterval('P30D'));
        $due_date->add(new \DateInterval('P1D'));
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P1Y'));

        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $due_date->format('Y-m-d'),
            'date_enreg'            => $begin_date->format('Y-m-d'),
            'date_debut_cotis'      => $begin_date->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->assertTrue($adh->load($id));

        //member is up to date, but not yet close to be expired, no reminder to send
        $this->assertTrue($this->adh->isUp2Date());
        $this->assertCount(0, $reminders->getList($this->zdb));
        $this->assertCount(0, $lreminders->getList($this->zdb));
        $this->assertCount(0, $ireminders->getList($this->zdb));

        //create a close to be expired contribution
        $due_date = clone $now;
        $due_date->add(new \DateInterval('P30D'));
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $due_date->format('Y-m-d'),
            'date_enreg'            => $begin_date->format('Y-m-d'),
            'date_debut_cotis'      => $begin_date->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->assertTrue($adh->load($id));

        //member is up-to-date, and close to be expired, one impending reminder to send
        $this->assertTrue($this->adh->isUp2Date());
        $this->assertCount(1, $reminders->getList($this->zdb));
        $this->assertCount(0, $lreminders->getList($this->zdb));
        $this->assertCount(1, $ireminders->getList($this->zdb));


        //create a close to be expired contribution, 7 days before expiration
        $due_date = clone $now;
        $due_date->add(new \DateInterval('P7D'));
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $due_date->format('Y-m-d'),
            'date_enreg'            => $begin_date->format('Y-m-d'),
            'date_debut_cotis'      => $begin_date->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->assertTrue($adh->load($id));

        //member is up to date, and close to be expired, one impending reminder to send
        $this->assertTrue($this->adh->isUp2Date());
        $this->assertCount(1, $reminders->getList($this->zdb));
        $this->assertCount(0, $lreminders->getList($this->zdb));
        $this->assertCount(1, $ireminders->getList($this->zdb));

        //create a close to be expired contribution, the last day before expiration
        $due_date = clone $now;
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $due_date->format('Y-m-d'),
            'date_enreg'            => $begin_date->format('Y-m-d'),
            'date_debut_cotis'      => $begin_date->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->assertTrue($adh->load($id));

        //member is up-to-date, and close to be expired, one impending reminder to send
        $this->assertTrue($this->adh->isUp2Date());
        $this->assertCount(1, $reminders->getList($this->zdb));
        $this->assertCount(0, $lreminders->getList($this->zdb));
        $this->assertCount(1, $ireminders->getList($this->zdb));

        //add a first close to be expired contribution reminder
        $send = new \DateTime();
        $send->sub(new \DateInterval('P30D'));
        $data = array(
            'reminder_type'     => \Galette\Entity\Reminder::IMPENDING,
            'reminder_dest'     => $id,
            'reminder_date'     => $send->format('Y-m-d'),
            'reminder_success'  => true,
            'reminder_nomail'   => ($this->zdb->isPostgres() ? 'false' : 0)
        );

        $insert = $this->zdb->insert(\Galette\Entity\Reminder::TABLE);
        $insert->values($data);

        $add = $this->zdb->execute($insert);
        $this->assertGreaterThan(0, $add->count());

        //there is still one impending reminder to send
        $this->assertTrue($this->adh->isUp2Date());
        $this->assertCount(1, $reminders->getList($this->zdb));
        $this->assertCount(0, $lreminders->getList($this->zdb));
        $this->assertCount(1, $ireminders->getList($this->zdb));

        //add a second close to be expired contribution reminder, yesterday
        $send = new \DateTime();
        $send->sub(new \DateInterval('P1D'));
        $data = array(
            'reminder_type'     => \Galette\Entity\Reminder::IMPENDING,
            'reminder_dest'     => $id,
            'reminder_date'     => $send->format('Y-m-d'),
            'reminder_success'  => true,
            'reminder_nomail'   => ($this->zdb->isPostgres() ? 'false' : 0)
        );

        $insert = $this->zdb->insert(\Galette\Entity\Reminder::TABLE);
        $insert->values($data);

        $add = $this->zdb->execute($insert);
        $this->assertGreaterThan(0, $add->count());

        //nothing to send!
        $this->assertTrue($this->adh->isUp2Date());
        $this->assertCount(0, $reminders->getList($this->zdb));
        $this->assertCount(0, $lreminders->getList($this->zdb));
        $this->assertCount(0, $ireminders->getList($this->zdb));

        //create an expired contribution, today
        $due_date = clone $now;
        $due_date->sub(new \DateInterval('P1D'));
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $due_date->format('Y-m-d'),
            'date_enreg'            => $begin_date->format('Y-m-d'),
            'date_debut_cotis'      => $begin_date->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->assertTrue($adh->load($id));

        //member late, but for less than 30 days, no reminder to send
        $this->assertFalse($this->adh->isUp2Date());
        $this->assertCount(0, $reminders->getList($this->zdb));
        $this->assertCount(0, $lreminders->getList($this->zdb));
        $this->assertCount(0, $ireminders->getList($this->zdb));

        //create an expired contribution, 29 days ago
        $due_date = clone $now;
        $due_date->sub(new \DateInterval('P29D'));
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $due_date->format('Y-m-d'),
            'date_enreg'            => $begin_date->format('Y-m-d'),
            'date_debut_cotis'      => $begin_date->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->assertTrue($adh->load($id));

        //member is late, but for less than 30 days, no reminder to send
        $this->assertFalse($this->adh->isUp2Date());
        $this->assertCount(0, $reminders->getList($this->zdb));
        $this->assertCount(0, $lreminders->getList($this->zdb));
        $this->assertCount(0, $ireminders->getList($this->zdb));

        //create an expired contribution, late by 30 days
        $due_date = clone $now;
        $due_date->sub(new \DateInterval('P30D'));
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $due_date->format('Y-m-d'),
            'date_enreg'            => $begin_date->format('Y-m-d'),
            'date_debut_cotis'      => $begin_date->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->assertTrue($adh->load($id));

        //member is late, one late reminder to send
        $this->assertFalse($this->adh->isUp2Date());
        $this->assertCount(1, $reminders->getList($this->zdb));
        $this->assertCount(1, $lreminders->getList($this->zdb));
        $this->assertCount(0, $ireminders->getList($this->zdb));

        //create an expired contribution, late by 40 days
        $due_date = clone $now;
        $due_date->sub(new \DateInterval('P40D'));
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $due_date->format('Y-m-d'),
            'date_enreg'            => $begin_date->format('Y-m-d'),
            'date_debut_cotis'      => $begin_date->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->assertTrue($adh->load($id));

        //member is late, one late reminder to send
        $this->assertFalse($this->adh->isUp2Date());
        $this->assertCount(1, $reminders->getList($this->zdb));
        $this->assertCount(1, $lreminders->getList($this->zdb));
        $this->assertCount(0, $ireminders->getList($this->zdb));

        //add a sent late reminder, as it should have been
        $send = clone $now;
        $send->sub(new \DateInterval('P5D'));
        $data = array(
            'reminder_type'     => \Galette\Entity\Reminder::LATE,
            'reminder_dest'     => $id,
            'reminder_date'     => $send->format('Y-m-d'),
            'reminder_success'  => true,
            'reminder_nomail'   => ($this->zdb->isPostgres() ? 'false' : 0)
        );

        $insert = $this->zdb->insert(\Galette\Entity\Reminder::TABLE);
        $insert->values($data);

        $add = $this->zdb->execute($insert);
        $this->assertGreaterThan(0, $add->count());

        //nothing to send!
        $this->assertFalse($this->adh->isUp2Date());
        $this->assertCount(0, $reminders->getList($this->zdb));
        $this->assertCount(0, $lreminders->getList($this->zdb));
        $this->assertCount(0, $ireminders->getList($this->zdb));

        //create an expired contribution, 60 days ago
        $due_date = clone $now;
        $due_date->sub(new \DateInterval('P60D'));
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $due_date->format('Y-m-d'),
            'date_enreg'            => $begin_date->format('Y-m-d'),
            'date_debut_cotis'      => $begin_date->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->assertTrue($adh->load($id));

        //member has been late for two months, one late reminder to send
        $this->assertFalse($this->adh->isUp2Date());
        $this->assertCount(1, $reminders->getList($this->zdb));
        $this->assertCount(1, $lreminders->getList($this->zdb));
        $this->assertCount(0, $ireminders->getList($this->zdb));
    }

    /**
     * Test getList with reminders from previous period already present
     *
     * @return void
     */
    public function testGetListNextYear()
    {
        //impendings
        $ireminders = new \Galette\Repository\Reminders([\Galette\Entity\Reminder::IMPENDING]);
        $this->assertSame([], $ireminders->getList($this->zdb));

        //lates
        $lreminders = new \Galette\Repository\Reminders([\Galette\Entity\Reminder::LATE]);
        $this->assertSame([], $lreminders->getList($this->zdb));

        //all
        $reminders = new \Galette\Repository\Reminders();
        $this->assertSame([], $reminders->getList($this->zdb));

        //create member
        $this->getMemberTwo();
        $id = $this->adh->id;

        //create a contribution, just before being a close to be expired contribution
        $now = new \DateTime();

        //add a first close to be expired contribution reminder - last year
        $send = new \DateTime();
        $send->sub(new \DateInterval('P90D'))->sub(new \DateInterval('P1Y'));
        $data = array(
            'reminder_type'     => \Galette\Entity\Reminder::IMPENDING,
            'reminder_dest'     => $id,
            'reminder_date'     => $send->format('Y-m-d'),
            'reminder_success'  => true,
            'reminder_nomail'   => ($this->zdb->isPostgres() ? 'false' : 0)
        );

        $insert = $this->zdb->insert(\Galette\Entity\Reminder::TABLE);
        $insert->values($data);

        $add = $this->zdb->execute($insert);
        $this->assertGreaterThan(0, $add->count());

        //add a second close to be expired contribution reminder - last year
        $send = new \DateTime();
        $send->sub(new \DateInterval('P67D'))->sub(new \DateInterval('P1Y'));
        $data = array(
            'reminder_type'     => \Galette\Entity\Reminder::IMPENDING,
            'reminder_dest'     => $id,
            'reminder_date'     => $send->format('Y-m-d'),
            'reminder_success'  => true,
            'reminder_nomail'   => ($this->zdb->isPostgres() ? 'false' : 0)
        );

        $insert = $this->zdb->insert(\Galette\Entity\Reminder::TABLE);
        $insert->values($data);

        $add = $this->zdb->execute($insert);
        $this->assertGreaterThan(0, $add->count());

        //add a first late contribution reminder - last year
        $send = clone $now;
        $send->sub(new \DateInterval('P30D'))->sub(new \DateInterval('P1Y'));
        $data = array(
            'reminder_type'     => \Galette\Entity\Reminder::LATE,
            'reminder_dest'     => $id,
            'reminder_date'     => $send->format('Y-m-d'),
            'reminder_success'  => true,
            'reminder_nomail'   => ($this->zdb->isPostgres() ? 'false' : 0)
        );

        $insert = $this->zdb->insert(\Galette\Entity\Reminder::TABLE);
        $insert->values($data);

        $add = $this->zdb->execute($insert);
        $this->assertGreaterThan(0, $add->count());

        //add a second late contribution reminder - last year
        $send = clone $now;
        $send->sub(new \DateInterval('P1Y'));
        $data = array(
            'reminder_type'     => \Galette\Entity\Reminder::LATE,
            'reminder_dest'     => $id,
            'reminder_date'     => $send->format('Y-m-d'),
            'reminder_success'  => true,
            'reminder_nomail'   => ($this->zdb->isPostgres() ? 'false' : 0)
        );

        $insert = $this->zdb->insert(\Galette\Entity\Reminder::TABLE);
        $insert->values($data);

        $add = $this->zdb->execute($insert);
        $this->assertGreaterThan(0, $add->count());

        //create a close to be expired contribution
        $due_date = clone $now;
        $due_date->add(new \DateInterval('P30D'));
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $due_date->format('Y-m-d'),
            'date_enreg'            => $begin_date->format('Y-m-d'),
            'date_debut_cotis'      => $begin_date->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->assertTrue($adh->load($id));

        //member is up-to-date, and close to be expired, one impending reminder to send
        $this->assertTrue($this->adh->isUp2Date());
        $this->assertCount(1, $reminders->getList($this->zdb));
        $this->assertCount(0, $lreminders->getList($this->zdb));
        $this->assertCount(1, $ireminders->getList($this->zdb));

        //create a close to be expired contribution, 7 days before expiration
        $due_date = clone $now;
        $due_date->add(new \DateInterval('P7D'));
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $due_date->format('Y-m-d'),
            'date_enreg'            => $begin_date->format('Y-m-d'),
            'date_debut_cotis'      => $begin_date->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->assertTrue($adh->load($id));

        //member is up-to-date, and close to be expired, one impending reminder to send
        $this->assertTrue($this->adh->isUp2Date());
        $this->assertCount(1, $reminders->getList($this->zdb));
        $this->assertCount(0, $lreminders->getList($this->zdb));
        $this->assertCount(1, $ireminders->getList($this->zdb));

        //create an expired contribution, late by 30 days
        $due_date = clone $now;
        $due_date->sub(new \DateInterval('P30D'));
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $due_date->format('Y-m-d'),
            'date_enreg'            => $begin_date->format('Y-m-d'),
            'date_debut_cotis'      => $begin_date->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->assertTrue($adh->load($id));

        //member is late, one late reminder to send
        $this->assertFalse($this->adh->isUp2Date());
        $this->assertCount(1, $reminders->getList($this->zdb));
        $this->assertCount(1, $lreminders->getList($this->zdb));
        $this->assertCount(0, $ireminders->getList($this->zdb));

        //create an expired contribution, 60 days ago
        $due_date = clone $now;
        $due_date->sub(new \DateInterval('P60D'));
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $due_date->format('Y-m-d'),
            'date_enreg'            => $begin_date->format('Y-m-d'),
            'date_debut_cotis'      => $begin_date->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->assertTrue($adh->load($id));

        //member has been late for two months, one late reminder to send
        $this->assertFalse($this->adh->isUp2Date());
        $this->assertCount(1, $reminders->getList($this->zdb));
        $this->assertCount(1, $lreminders->getList($this->zdb));
        $this->assertCount(0, $ireminders->getList($this->zdb));
    }
}
