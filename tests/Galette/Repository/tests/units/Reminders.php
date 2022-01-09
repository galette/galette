<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Reminders repository tests
 *
 * PHP version 5
 *
 * Copyright © 2020 The Galette Team
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
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2020-09-14
 */

namespace Galette\Repository\test\units;

use atoum;
use Galette\GaletteTestCase;

/**
 * Reminders repository tests
 *
 * @category  Repository
 * @name      Remoinders
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-09-14
 */
class Reminders extends GaletteTestCase
{
    protected $seed = 95842355;
    private $ids = [];

    /**
     * Set up tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function beforeTestMethod($method)
    {
        parent::beforeTestMethod($method);
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
     * @param string $method Calling method
     *
     * @return void
     */
    public function afterTestMethod($method)
    {
        parent::afterTestMethod($method);
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
        $this->array($ireminders->getList($this->zdb))->isIdenticalTo([]);

        //lates
        $lreminders = new \Galette\Repository\Reminders([\Galette\Entity\Reminder::LATE]);
        $this->array($lreminders->getList($this->zdb))->isIdenticalTo([]);

        //all
        $reminders = new \Galette\Repository\Reminders();
        $this->array($reminders->getList($this->zdb))->isIdenticalTo([]);

        //create member
        $this->getMemberTwo();
        $id = $this->adh->id;

        //create contribution, just about to be impending
        $now = new \DateTime();
        $date_begin = clone $now;
        $date_begin->sub(new \DateInterval('P1Y'));
        $date_begin->add(new \DateInterval('P1M'));
        $date_begin->add(new \DateInterval('P1D'));
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $date_end->format('Y-m-d'),
            'date_enreg'            => $now->format('Y-m-d'),
            'date_debut_cotis'      => $now->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->boolean($adh->load($id))->isTrue();

        //member is up to date, but not yet impeding, no reminder to send
        $this->boolean($this->adh->isUp2Date())->isTrue();
        $this->array($reminders->getList($this->zdb))->hasSize(0);
        $this->array($lreminders->getList($this->zdb))->hasSize(0);
        $this->array($ireminders->getList($this->zdb))->hasSize(0);


        //create contribution, just impending
        $date_begin = clone $now;
        $date_begin->sub(new \DateInterval('P1Y'));
        $date_begin->add(new \DateInterval('P1M'));
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $date_end->format('Y-m-d'),
            'date_enreg'            => $now->format('Y-m-d'),
            'date_debut_cotis'      => $now->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->boolean($adh->load($id))->isTrue();

        //member is up to date, there is one impending reminder to send
        $this->boolean($this->adh->isUp2Date())->isTrue();
        $this->array($reminders->getList($this->zdb))->hasSize(1);
        $this->array($lreminders->getList($this->zdb))->hasSize(0);
        $this->array($ireminders->getList($this->zdb))->hasSize(1);

        //create contribution, just impending less than 7 days
        $date_begin = clone $now;
        $date_begin->sub(new \DateInterval('P1Y'));
        $date_begin->add(new \DateInterval('P4D'));
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $date_end->format('Y-m-d'),
            'date_enreg'            => $now->format('Y-m-d'),
            'date_debut_cotis'      => $now->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->boolean($adh->load($id))->isTrue();

        //member is up to date, there is one impending reminder to send
        $this->boolean($this->adh->isUp2Date())->isTrue();
        $this->array($reminders->getList($this->zdb))->hasSize(1);
        $this->array($lreminders->getList($this->zdb))->hasSize(0);
        $this->array($ireminders->getList($this->zdb))->hasSize(1);

        //add a sent first impending reminder
        $send = clone $now;
        $send->sub(new \DateInterval('P1M'));
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
        $this->integer($add->count())->isGreaterThan(0);

        //there is still one reminder to send
        $this->boolean($this->adh->isUp2Date())->isTrue();
        $this->array($reminders->getList($this->zdb))->hasSize(1);
        $this->array($lreminders->getList($this->zdb))->hasSize(0);
        $this->array($ireminders->getList($this->zdb))->hasSize(1);

        //add a sent second impending reminder, yesterday
        $send = clone $now;
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
        $this->integer($add->count())->isGreaterThan(0);

        //nothing to send!
        $this->boolean($this->adh->isUp2Date())->isTrue();
        $this->array($reminders->getList($this->zdb))->hasSize(0);
        $this->array($lreminders->getList($this->zdb))->hasSize(0);
        $this->array($ireminders->getList($this->zdb))->hasSize(0);

        //create contribution, expiration day
        $now = new \DateTime();
        $date_begin = clone $now;
        $date_begin->sub(new \DateInterval('P1Y'));
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $date_end->format('Y-m-d'),
            'date_enreg'            => $now->format('Y-m-d'),
            'date_debut_cotis'      => $now->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->boolean($adh->load($id))->isTrue();

        //member is not up to date, no reminder to send
        $this->boolean($this->adh->isUp2Date())->isFalse();
        $this->array($reminders->getList($this->zdb))->hasSize(0);
        $this->array($lreminders->getList($this->zdb))->hasSize(0);
        $this->array($ireminders->getList($this->zdb))->hasSize(0);

        //create contribution, just late
        $now = new \DateTime();
        $date_begin = clone $now;
        $date_begin->sub(new \DateInterval('P1Y'));
        $date_begin->sub(new \DateInterval('P1D'));
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $date_end->format('Y-m-d'),
            'date_enreg'            => $now->format('Y-m-d'),
            'date_debut_cotis'      => $now->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->boolean($adh->load($id))->isTrue();

        //member is not up to date, but less than one month, no reminder to send
        $this->boolean($this->adh->isUp2Date())->isFalse();
        $this->array($reminders->getList($this->zdb))->hasSize(0);
        $this->array($lreminders->getList($this->zdb))->hasSize(0);
        $this->array($ireminders->getList($this->zdb))->hasSize(0);

        //create contribution, late by 1 month minus 1 day
        $now = new \DateTime();
        $date_begin = clone $now;
        $date_begin->sub(new \DateInterval('P1Y'));
        $date_begin->sub(new \DateInterval('P1M'));
        $date_begin->add(new \DateInterval('P1D'));
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $date_end->format('Y-m-d'),
            'date_enreg'            => $now->format('Y-m-d'),
            'date_debut_cotis'      => $now->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->boolean($adh->load($id))->isTrue();

        //member is not up to date, but less than one month, no reminder to send
        $this->boolean($this->adh->isUp2Date())->isFalse();
        $this->array($reminders->getList($this->zdb))->hasSize(0);
        $this->array($lreminders->getList($this->zdb))->hasSize(0);
        $this->array($ireminders->getList($this->zdb))->hasSize(0);

        //create contribution, late by 31 days
        $now = new \DateTime();
        $date_begin = clone $now;
        $date_begin->sub(new \DateInterval('P1Y'));
        $date_begin->sub(new \DateInterval('P31D'));
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $date_end->format('Y-m-d'),
            'date_enreg'            => $now->format('Y-m-d'),
            'date_debut_cotis'      => $now->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->boolean($adh->load($id))->isTrue();

        //member is not up to date for one month, one late reminder to send
        $this->boolean($this->adh->isUp2Date())->isFalse();
        $this->array($reminders->getList($this->zdb))->hasSize(1);
        $this->array($lreminders->getList($this->zdb))->hasSize(1);
        $this->array($ireminders->getList($this->zdb))->hasSize(0);

        //create contribution, late by 40 days
        $now = new \DateTime();
        $date_begin = clone $now;
        $date_begin->sub(new \DateInterval('P1Y'));
        $date_begin->sub(new \DateInterval('P40D'));
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $date_end->format('Y-m-d'),
            'date_enreg'            => $now->format('Y-m-d'),
            'date_debut_cotis'      => $now->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->boolean($adh->load($id))->isTrue();

        //member is not up to date for one month, one late reminder to send
        $this->boolean($this->adh->isUp2Date())->isFalse();
        $this->array($reminders->getList($this->zdb))->hasSize(1);
        $this->array($lreminders->getList($this->zdb))->hasSize(1);
        $this->array($ireminders->getList($this->zdb))->hasSize(0);

        //add a sent late reminder, at it should have been
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
        $this->integer($add->count())->isGreaterThan(0);

        //nothing to send!
        $this->boolean($this->adh->isUp2Date())->isFalse();
        $this->array($reminders->getList($this->zdb))->hasSize(0);
        $this->array($lreminders->getList($this->zdb))->hasSize(0);
        $this->array($ireminders->getList($this->zdb))->hasSize(0);

        //create contribution, late by 2 months
        $now = new \DateTime();
        $date_begin = clone $now;
        $date_begin->sub(new \DateInterval('P1Y'));
        $date_begin->sub(new \DateInterval('P2M'));
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $date_end->format('Y-m-d'),
            'date_enreg'            => $now->format('Y-m-d'),
            'date_debut_cotis'      => $now->format('Y-m-d')
        ]);

        $adh = $this->adh;
        $this->boolean($adh->load($id))->isTrue();

        //member is not up to date for one month, one late reminder to send
        $this->boolean($this->adh->isUp2Date())->isFalse();
        $this->array($reminders->getList($this->zdb))->hasSize(1);
        $this->array($lreminders->getList($this->zdb))->hasSize(1);
        $this->array($ireminders->getList($this->zdb))->hasSize(0);
    }
}
