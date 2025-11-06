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

namespace GaletteTests\Entity;

use Galette\GaletteTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Contribution tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Contribution extends GaletteTestCase
{
    protected int $seed = 95842354;

    /**
     * Cleanup after each test method
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $this->zdb = new \Galette\Core\Db();

        $this->cleanContributions();

        $delete = $this->zdb->delete(\Galette\Entity\ContributionsTypes::TABLE);
        $delete->where(['libelle_type_cotis' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $this->cleanMembers();
    }

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);

        $this->adh = new \Galette\Entity\Adherent($this->zdb);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
    }

    /**
     * pref_beg_membership provider
     *
     * @return array
     */
    public static function begProvider(): array
    {
        return [
            ['interval' => 'P10M'],
            ['interval' => 'P1M'],
        ];
    }

    /**
     * Test empty contribution
     *
     * @return void
     */
    public function testMembershipExtensionJustEmpty(): void
    {
        $contrib = $this->contrib;
        $this->assertNull($contrib->id);
        $this->assertNull($contrib->date);
        $this->assertNull($contrib->begin_date);
        $this->assertNull($contrib->end_date);
        $this->assertNull($contrib->raw_date);
        $this->assertNull($contrib->raw_begin_date);
        $this->assertNull($contrib->raw_end_date);
        $this->assertEmpty($contrib->duration);
        $this->assertSame((int)$this->preferences->pref_default_paymenttype, $contrib->payment_type);
        $this->assertSame('Check', $contrib->getPaymentType());
        $this->assertNull($contrib->model);
        $this->assertNull($contrib->member);
        $this->assertNull($contrib->type);
        $this->assertNull($contrib->amount);
        $this->assertNull($contrib->orig_amount);
        $this->assertNull($contrib->info);
        $this->assertNull($contrib->transaction);
        $this->assertCount(11, $contrib->fields);
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\Contribution::PK]));
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\Adherent::PK]));
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\ContributionsTypes::PK]));
        $this->assertTrue(isset($contrib->fields['montant_cotis']));
        $this->assertTrue(isset($contrib->fields['type_paiement_cotis']));
        $this->assertTrue(isset($contrib->fields['info_cotis']));
        $this->assertTrue(isset($contrib->fields['date_debut_cotis']));

        $this->assertSame('cotis-give', $contrib->getRowClass());
        $this->assertNull($contrib::getDueDate($this->zdb, 1));
        $this->assertFalse($contrib->isTransactionPart());
        $this->assertFalse($contrib->isTransactionPartOf(1));
        $this->assertSame('Check', $contrib->getPaymentType());
        $this->assertNull($contrib->unknown_property);
        $this->expectLogEntry(
            \Analog::WARNING,
            "Unknown property 'unknown_property'"
        );
    }

    /**
     * Test empty contribution with begin of membership set in preferences
     *
     * @param string $interval Interval to subtract from now to set begin of membership
     *
     * @return void
     */
    #[DataProvider("begProvider")]
    public function testBeginMembershipJustEmpty(string $interval): void
    {
        //preg_beg_membership date, some months ago
        $beg_membership = new \DateTime();
        $beg_membership->sub(new \DateInterval($interval));

        global $preferences;
        $preferences->pref_beg_membership = $beg_membership->format('01/m');
        $preferences->pref_membership_ext = '';

        $this->assertTrue($preferences->store());

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login
        );

        //Reset preferences
        $preferences->pref_beg_membership = $this->preferences->getDefaults()['pref_beg_membership'];
        $preferences->pref_membership_ext = $this->preferences->getDefaults()['pref_membership_ext'];
        $this->assertTrue($preferences->store());

        $this->assertNull($contrib->id);
        $this->assertNull($contrib->date);
        $this->assertNull($contrib->begin_date);
        $this->assertNull($contrib->end_date);
        $this->assertNull($contrib->raw_date);
        $this->assertNull($contrib->raw_begin_date);
        $this->assertNull($contrib->raw_end_date);
        $this->assertEmpty($contrib->duration);
        $this->assertSame((int)$this->preferences->pref_default_paymenttype, $contrib->payment_type);
        $this->assertSame('Check', $contrib->getPaymentType());
        $this->assertNull($contrib->model);
        $this->assertNull($contrib->member);
        $this->assertNull($contrib->type);
        $this->assertNull($contrib->amount);
        $this->assertNull($contrib->orig_amount);
        $this->assertNull($contrib->info);
        $this->assertNull($contrib->transaction);
        $this->assertCount(11, $contrib->fields);
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\Contribution::PK]));
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\Adherent::PK]));
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\ContributionsTypes::PK]));
        $this->assertTrue(isset($contrib->fields['montant_cotis']));
        $this->assertTrue(isset($contrib->fields['type_paiement_cotis']));
        $this->assertTrue(isset($contrib->fields['info_cotis']));
        $this->assertTrue(isset($contrib->fields['date_debut_cotis']));

        $this->assertSame('cotis-give', $contrib->getRowClass());
        $this->assertNull($contrib::getDueDate($this->zdb, 1));
        $this->assertFalse($contrib->isTransactionPart());
        $this->assertFalse($contrib->isTransactionPartOf(1));
        $this->assertSame('Check', $contrib->getPaymentType());
        $this->assertNull($contrib->unknown_property);
        $this->expectLogEntry(
            \Analog::WARNING,
            "Unknown property 'unknown_property'"
        );
    }

    /**
     * Test empty donation
     *
     * @return void
     */
    public function testEmptyDonation(): void
    {
        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => 4] //donation in kind
        );
        $this->assertNull($contrib->id);
        $this->assertEquals(date('Y-m-d'), $contrib->date);
        $this->assertEquals(date('Y-m-d'), $contrib->begin_date);
        $this->assertNull($contrib->end_date);
        $this->assertInstanceOf(\DateTime::class, $contrib->raw_date);
        $this->assertEquals(date('Y-m-d'), $contrib->raw_date->format('Y-m-d'));
        $this->assertInstanceOf(\DateTime::class, $contrib->raw_begin_date);
        $this->assertEquals(date('Y-m-d'), $contrib->raw_begin_date->format('Y-m-d'));
        $this->assertNull($contrib->raw_end_date);
        $this->assertEmpty($contrib->duration);
        $this->assertSame((int)$this->preferences->pref_default_paymenttype, $contrib->payment_type);
        $this->assertSame('Check', $contrib->getPaymentType());
        $this->assertSame(\Galette\Entity\PdfModel::RECEIPT_MODEL, $contrib->model);
        $this->assertNull($contrib->member);
        $this->assertInstanceOf(\Galette\Entity\ContributionsTypes::class, $contrib->type);
        $this->assertSame(4, $contrib->type->id);
        $this->assertNull($contrib->amount);
        $this->assertNull($contrib->orig_amount);
        $this->assertNull($contrib->info);
        $this->assertNull($contrib->transaction);
        $this->assertCount(11, $contrib->fields);
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\Contribution::PK]));
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\Adherent::PK]));
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\ContributionsTypes::PK]));
        $this->assertTrue(isset($contrib->fields['montant_cotis']));
        $this->assertTrue(isset($contrib->fields['type_paiement_cotis']));
        $this->assertTrue(isset($contrib->fields['info_cotis']));
        $this->assertTrue(isset($contrib->fields['date_debut_cotis']));

        $this->assertSame('cotis-give', $contrib->getRowClass());
        $this->assertNull($contrib::getDueDate($this->zdb, 1));
        $this->assertFalse($contrib->isTransactionPart());
        $this->assertFalse($contrib->isTransactionPartOf(1));
        $this->assertSame('Check', $contrib->getPaymentType());
        $this->assertNull($contrib->unknown_property);
        $this->expectLogEntry(
            \Analog::WARNING,
            "Unknown property 'unknown_property'"
        );
    }

    /**
     * Test empty donation with begin of membership set in preferences
     *
     * @param string $interval Interval to subtract from now to set begin of membership
     *
     * @return void
     */
    #[DataProvider("begProvider")]
    public function testBeginMembershipEmptyDonation(string $interval): void
    {
        $now = new \DateTime();

        //preg_beg_membership date, some months ago
        $beg_membership = new \DateTime();
        $beg_membership->sub(new \DateInterval($interval));

        global $preferences;
        $preferences->pref_beg_membership = $beg_membership->format('01/m');
        $preferences->pref_membership_ext = '';

        $this->assertTrue($preferences->store());

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => 4] //donation in kind
        );

        //Reset preferences
        $preferences->pref_beg_membership = $this->preferences->getDefaults()['pref_beg_membership'];
        $preferences->pref_membership_ext = $this->preferences->getDefaults()['pref_membership_ext'];
        $this->assertTrue($preferences->store());

        $this->assertNull($contrib->id);
        $this->assertEquals($now->format('Y-m-d'), $contrib->date);
        $this->assertEquals($now->format('Y-m-d'), $contrib->begin_date);
        $this->assertNull($contrib->end_date);
        $this->assertInstanceOf(\DateTime::class, $contrib->raw_date);
        $this->assertEquals($now->format('Y-m-d'), $contrib->raw_date->format('Y-m-d'));
        $this->assertInstanceOf(\DateTime::class, $contrib->raw_begin_date);
        $this->assertEquals($now->format('Y-m-d'), $contrib->raw_begin_date->format('Y-m-d'));
        $this->assertNull($contrib->raw_end_date);
        $this->assertEmpty($contrib->duration);
        $this->assertSame((int)$this->preferences->pref_default_paymenttype, $contrib->payment_type);
        $this->assertSame('Check', $contrib->getPaymentType());
        $this->assertSame(\Galette\Entity\PdfModel::RECEIPT_MODEL, $contrib->model);
        $this->assertNull($contrib->member);
        $this->assertInstanceOf(\Galette\Entity\ContributionsTypes::class, $contrib->type);
        $this->assertSame(4, $contrib->type->id);
        $this->assertNull($contrib->amount);
        $this->assertNull($contrib->orig_amount);
        $this->assertNull($contrib->info);
        $this->assertNull($contrib->transaction);
        $this->assertCount(11, $contrib->fields);
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\Contribution::PK]));
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\Adherent::PK]));
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\ContributionsTypes::PK]));
        $this->assertTrue(isset($contrib->fields['montant_cotis']));
        $this->assertTrue(isset($contrib->fields['type_paiement_cotis']));
        $this->assertTrue(isset($contrib->fields['info_cotis']));
        $this->assertTrue(isset($contrib->fields['date_debut_cotis']));

        $this->assertSame('cotis-give', $contrib->getRowClass());
        $this->assertNull($contrib::getDueDate($this->zdb, 1));
        $this->assertFalse($contrib->isTransactionPart());
        $this->assertFalse($contrib->isTransactionPartOf(1));
        $this->assertSame('Check', $contrib->getPaymentType());
        $this->assertNull($contrib->unknown_property);
        $this->expectLogEntry(
            \Analog::WARNING,
            "Unknown property 'unknown_property'"
        );
    }

    /**
     * Test empty fee
     *
     * @return void
     */
    public function testEmptyFee(): void
    {
        $now = new \DateTime();

        //expected begin date
        $expected_begin = new \DateTime($now->format('Y-m-d'));

        $expected_end = clone $expected_begin;
        $expected_end->add(new \DateInterval('P1Y'));
        $expected_end->sub(new \DateInterval('P1D'));

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => 1] //annual fee
        );
        $this->assertNull($contrib->id);
        $this->assertEquals($now->format('Y-m-d'), $contrib->date);
        $this->assertEquals($expected_begin->format('Y-m-d'), $contrib->begin_date);
        $this->assertSame($expected_end->format('Y-m-d'), $contrib->end_date);
        $this->assertInstanceOf(\DateTime::class, $contrib->raw_date);
        $this->assertEquals($now->format('Y-m-d'), $contrib->raw_date->format('Y-m-d'));
        $this->assertInstanceOf(\DateTime::class, $contrib->raw_begin_date);
        $this->assertEquals($expected_begin->format('Y-m-d'), $contrib->raw_begin_date->format('Y-m-d'));
        $this->assertInstanceOf(\DateTime::class, $contrib->raw_end_date);
        $this->assertEquals($expected_end->format('Y-m-d'), $contrib->raw_end_date->format('Y-m-d'));
        $this->assertSame(12, $contrib->duration);
        $this->assertSame($this->preferences->pref_default_paymenttype, $contrib->payment_type);
        $this->assertSame('Check', $contrib->getPaymentType());
        $this->assertSame(\Galette\Entity\PdfModel::INVOICE_MODEL, $contrib->model);
        $this->assertNull($contrib->member);
        $this->assertInstanceOf(\Galette\Entity\ContributionsTypes::class, $contrib->type);
        $this->assertSame(1, $contrib->type->id);
        $this->assertNull($contrib->amount);
        $this->assertNull($contrib->orig_amount);
        $this->assertNull($contrib->info);
        $this->assertNull($contrib->transaction);
        $this->assertCount(11, $contrib->fields);
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\Contribution::PK]));
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\Adherent::PK]));
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\ContributionsTypes::PK]));
        $this->assertTrue(isset($contrib->fields['montant_cotis']));
        $this->assertTrue(isset($contrib->fields['type_paiement_cotis']));
        $this->assertTrue(isset($contrib->fields['info_cotis']));
        $this->assertTrue(isset($contrib->fields['date_debut_cotis']));

        $this->assertSame('cotis-normal', $contrib->getRowClass());
        $this->assertNull($contrib::getDueDate($this->zdb, 1));
        $this->assertFalse($contrib->isTransactionPart());
        $this->assertFalse($contrib->isTransactionPartOf(1));
        $this->assertSame('Check', $contrib->getPaymentType());
        $this->assertNull($contrib->unknown_property);
        $this->expectLogEntry(
            \Analog::WARNING,
            "Unknown property 'unknown_property'"
        );
    }

    /**
     * Test empty fee with a "monthly" contribution type
     *
     * @return void
     */
    public function testEmptyMonthlyFee(): void
    {
        $now = new \DateTime();

        //expected begin date
        $expected_begin = new \DateTime($now->format('Y-m-d'));

        //create monthly fee type - 2 months extension
        $contribtype = new \Galette\Entity\ContributionsTypes($this->zdb);
        $this->assertTrue($contribtype->add('FAKER' . $this->seed, 10.00, 2));

        $expected_end = clone $expected_begin;
        $expected_end->add(new \DateInterval('P2M'));
        $expected_end->sub(new \DateInterval('P1D'));

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => $contribtype->id] //annual fee
        );
        $this->assertNull($contrib->id);
        $this->assertEquals($now->format('Y-m-d'), $contrib->date);
        $this->assertEquals($expected_begin->format('Y-m-d'), $contrib->begin_date);
        $this->assertSame($expected_end->format('Y-m-d'), $contrib->end_date);
        $this->assertInstanceOf(\DateTime::class, $contrib->raw_date);
        $this->assertEquals($now->format('Y-m-d'), $contrib->raw_date->format('Y-m-d'));
        $this->assertInstanceOf(\DateTime::class, $contrib->raw_begin_date);
        $this->assertEquals($expected_begin->format('Y-m-d'), $contrib->raw_begin_date->format('Y-m-d'));
        $this->assertInstanceOf(\DateTime::class, $contrib->raw_end_date);
        $this->assertEquals($expected_end->format('Y-m-d'), $contrib->raw_end_date->format('Y-m-d'));
        $this->assertSame(2, $contrib->duration);
        $this->assertSame($this->preferences->pref_default_paymenttype, $contrib->payment_type);
        $this->assertSame('Check', $contrib->getPaymentType());
        $this->assertSame(\Galette\Entity\PdfModel::INVOICE_MODEL, $contrib->model);
        $this->assertNull($contrib->member);
        $this->assertInstanceOf(\Galette\Entity\ContributionsTypes::class, $contrib->type);
        $this->assertSame($contribtype->id, $contrib->type->id);
        $this->assertNull($contrib->amount);
        $this->assertNull($contrib->orig_amount);
        $this->assertNull($contrib->info);
        $this->assertNull($contrib->transaction);
        $this->assertCount(11, $contrib->fields);
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\Contribution::PK]));
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\Adherent::PK]));
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\ContributionsTypes::PK]));
        $this->assertTrue(isset($contrib->fields['montant_cotis']));
        $this->assertTrue(isset($contrib->fields['type_paiement_cotis']));
        $this->assertTrue(isset($contrib->fields['info_cotis']));
        $this->assertTrue(isset($contrib->fields['date_debut_cotis']));

        $this->assertSame('cotis-normal', $contrib->getRowClass());
        $this->assertNull($contrib::getDueDate($this->zdb, 1));
        $this->assertFalse($contrib->isTransactionPart());
        $this->assertFalse($contrib->isTransactionPartOf(1));
        $this->assertSame('Check', $contrib->getPaymentType());
        $this->assertNull($contrib->unknown_property);
        $this->expectLogEntry(
            \Analog::WARNING,
            "Unknown property 'unknown_property'"
        );
    }

    /**
     * Test empty fee with begin of membership set in preferences
     *
     * @param string $interval Interval to subtract from now to set begin of membership
     *
     * @return void
     */
    #[DataProvider("begProvider")]
    public function testBeginMembershipEmptyFee(string $interval): void
    {
        $now = new \DateTime();

        //preg_beg_membership date, some months ago
        $beg_membership = new \DateTime();
        $beg_membership->sub(new \DateInterval($interval));

        //expected begin date
        $expected_begin = new \DateTime($beg_membership->format('Y-m-01'));

        $expected_end = clone $expected_begin;
        $expected_end->add(new \DateInterval('P1Y'));
        $expected_end->sub(new \DateInterval('P1D'));

        global $preferences;
        $preferences->pref_beg_membership = $beg_membership->format('01/m');
        $preferences->pref_membership_ext = '';

        $this->assertTrue($preferences->store());

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => 1] //annual fee
        );

        //Reset preferences
        $preferences->pref_beg_membership = $this->preferences->getDefaults()['pref_beg_membership'];
        $preferences->pref_membership_ext = $this->preferences->getDefaults()['pref_membership_ext'];
        $this->assertTrue($preferences->store());

        $this->assertNull($contrib->id);

        $this->assertEquals($now->format('Y-m-d'), $contrib->date);
        $this->assertInstanceOf(\DateTime::class, $contrib->raw_date);
        $this->assertEquals($now->format('Y-m-d'), $contrib->raw_date->format('Y-m-d'));

        $this->assertEquals($expected_begin->format('Y-m-d'), $contrib->begin_date);
        $this->assertInstanceOf(\DateTime::class, $contrib->raw_begin_date);
        $this->assertEquals($expected_begin->format('Y-m-d'), $contrib->raw_begin_date->format('Y-m-d'));

        $this->assertSame($expected_end->format('Y-m-d'), $contrib->end_date);
        $this->assertInstanceOf(\DateTime::class, $contrib->raw_end_date);
        $this->assertEquals($expected_end->format('Y-m-d'), $contrib->raw_end_date->format('Y-m-d'));

        $this->assertSame(12, $contrib->duration);
        $this->assertSame($this->preferences->pref_default_paymenttype, $contrib->payment_type);
        $this->assertSame('Check', $contrib->getPaymentType());
        $this->assertSame(\Galette\Entity\PdfModel::INVOICE_MODEL, $contrib->model);
        $this->assertNull($contrib->member);
        $this->assertInstanceOf(\Galette\Entity\ContributionsTypes::class, $contrib->type);
        $this->assertSame(1, $contrib->type->id);
        $this->assertNull($contrib->amount);
        $this->assertNull($contrib->orig_amount);
        $this->assertNull($contrib->info);
        $this->assertNull($contrib->transaction);
        $this->assertCount(11, $contrib->fields);
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\Contribution::PK]));
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\Adherent::PK]));
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\ContributionsTypes::PK]));
        $this->assertTrue(isset($contrib->fields['montant_cotis']));
        $this->assertTrue(isset($contrib->fields['type_paiement_cotis']));
        $this->assertTrue(isset($contrib->fields['info_cotis']));
        $this->assertTrue(isset($contrib->fields['date_debut_cotis']));

        $this->assertSame('cotis-normal', $contrib->getRowClass());
        $this->assertNull($contrib::getDueDate($this->zdb, 1));
        $this->assertFalse($contrib->isTransactionPart());
        $this->assertFalse($contrib->isTransactionPartOf(1));
        $this->assertSame('Check', $contrib->getPaymentType());
        $this->assertNull($contrib->unknown_property);
        $this->expectLogEntry(
            \Analog::WARNING,
            "Unknown property 'unknown_property'"
        );
    }

    /**
     * Test empty fee with begin of membership set in preferences and a "monthly" contribution type
     *
     * @param string $interval Interval to subtract from now to set begin of membership
     *
     * @return void
     */
    #[DataProvider("begProvider")]
    public function testBeginMembershipEmptyMonthlyFee(string $interval): void
    {
        $now = new \DateTime();

        //preg_beg_membership date, some months ago
        $beg_membership = new \DateTime();
        $beg_membership->sub(new \DateInterval($interval));

        //expected begin date
        $expected_begin = new \DateTime($beg_membership->format('Y-m-01'));

        //create monthly fee type - 2 months extension
        //extension should be ignored since we use a beg membership date in settings
        $contribtype = new \Galette\Entity\ContributionsTypes($this->zdb);
        $this->assertTrue($contribtype->add('FAKER' . $this->seed, 10.00, 2));

        $expected_end = clone $expected_begin;
        $expected_end->add(new \DateInterval('P1Y'));
        $expected_end->sub(new \DateInterval('P1D'));

        global $preferences;
        $preferences->pref_beg_membership = $beg_membership->format('01/m');
        $preferences->pref_membership_ext = '';

        $this->assertTrue($preferences->store());

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => 1] //annual fee
        );

        //Reset preferences
        $preferences->pref_beg_membership = $this->preferences->getDefaults()['pref_beg_membership'];
        $preferences->pref_membership_ext = $this->preferences->getDefaults()['pref_membership_ext'];
        $this->assertTrue($preferences->store());

        $this->assertNull($contrib->id);

        $this->assertEquals($now->format('Y-m-d'), $contrib->date);
        $this->assertInstanceOf(\DateTime::class, $contrib->raw_date);
        $this->assertEquals($now->format('Y-m-d'), $contrib->raw_date->format('Y-m-d'));

        $this->assertEquals($expected_begin->format('Y-m-d'), $contrib->begin_date);
        $this->assertInstanceOf(\DateTime::class, $contrib->raw_begin_date);
        $this->assertEquals($expected_begin->format('Y-m-d'), $contrib->raw_begin_date->format('Y-m-d'));

        $this->assertSame($expected_end->format('Y-m-d'), $contrib->end_date);
        $this->assertInstanceOf(\DateTime::class, $contrib->raw_end_date);
        $this->assertEquals($expected_end->format('Y-m-d'), $contrib->raw_end_date->format('Y-m-d'));

        $this->assertSame(12, $contrib->duration);
        $this->assertSame($this->preferences->pref_default_paymenttype, $contrib->payment_type);
        $this->assertSame('Check', $contrib->getPaymentType());
        $this->assertSame(\Galette\Entity\PdfModel::INVOICE_MODEL, $contrib->model);
        $this->assertNull($contrib->member);
        $this->assertInstanceOf(\Galette\Entity\ContributionsTypes::class, $contrib->type);
        $this->assertSame(1, $contrib->type->id);
        $this->assertNull($contrib->amount);
        $this->assertNull($contrib->orig_amount);
        $this->assertNull($contrib->info);
        $this->assertNull($contrib->transaction);
        $this->assertCount(11, $contrib->fields);
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\Contribution::PK]));
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\Adherent::PK]));
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\ContributionsTypes::PK]));
        $this->assertTrue(isset($contrib->fields['montant_cotis']));
        $this->assertTrue(isset($contrib->fields['type_paiement_cotis']));
        $this->assertTrue(isset($contrib->fields['info_cotis']));
        $this->assertTrue(isset($contrib->fields['date_debut_cotis']));

        $this->assertSame('cotis-normal', $contrib->getRowClass());
        $this->assertNull($contrib::getDueDate($this->zdb, 1));
        $this->assertFalse($contrib->isTransactionPart());
        $this->assertFalse($contrib->isTransactionPartOf(1));
        $this->assertSame('Check', $contrib->getPaymentType());
        $this->assertNull($contrib->unknown_property);
        $this->expectLogEntry(
            \Analog::WARNING,
            "Unknown property 'unknown_property'"
        );
    }

    /**
     * Test getter and setter special cases
     *
     * @return void
     */
    public function testGetterSetter(): void
    {
        $contrib = $this->contrib;

        //set a bad date
        $contrib->begin_date = 'not a date';
        $this->assertNull($contrib->raw_begin_date);
        $this->assertNull($contrib->begin_date);

        $contrib->begin_date = '2017-06-17';
        $this->assertInstanceOf('DateTime', $contrib->raw_begin_date);
        $this->assertSame('2017-06-17', $contrib->begin_date);

        $contrib->amount = 'not an amount';
        $this->expectLogEntry(
            \Analog::WARNING,
            'Trying to set an amount with a non numeric value, or with a zero value'
        );
        $this->assertNull($contrib->amount);
        $contrib->amount = 0;
        $this->assertNull($contrib->amount);
        $contrib->amount = 42;
        $this->assertSame(42.0, $contrib->amount);
        $contrib->amount = '42';
        $this->expectLogEntry(
            \Analog::WARNING,
            'Trying to set an amount with a non numeric value, or with a zero value'
        );
        $this->assertSame(42.0, $contrib->amount);

        $contrib->type = 156;
        $this->expectLogEntry(
            \Analog::ERROR,
            'Unknown ID 156'
        );
        $this->assertInstanceOf(\Galette\Entity\ContributionsTypes::class, $contrib->type);
        $this->assertFalse($contrib->type->id);
        $contrib->type = 1;
        $this->assertInstanceOf(\Galette\Entity\ContributionsTypes::class, $contrib->type);
        $this->assertEquals(1, $contrib->type->id);

        $contrib->transaction = 'not a transaction id';
        $this->expectLogEntry(
            \Analog::WARNING,
            'Trying to set a transaction from an id that is not an integer.'
        );
        $this->assertNull($contrib->transaction);
        $contrib->transaction = 46;
        $this->expectLogEntry(
            \Analog::ERROR,
            'Non-logged-in users cannot load transaction id `46`'
        );
        $this->assertInstanceOf(\Galette\Entity\Transaction::class, $contrib->transaction);
        $this->assertNull($contrib->transaction->id);

        $contrib->member = 'not a member';
        $this->assertNull($contrib->member);
        $contrib->member = 118218;
        $this->assertSame(118218, $contrib->member);

        $contrib->not_a_property = 'abcde';
        $this->expectLogEntry(
            \Analog::WARNING,
            '[Galette\Entity\Contribution]: Trying to set an unknown property (not_a_property)'
        );
        $this->assertFalse(property_exists($contrib, 'not_a_property'));

        $contrib->payment_type = \Galette\Entity\PaymentType::CASH;
        $this->assertSame('Cash', $contrib->getPaymentType());

        $contrib->payment_type = \Galette\Entity\PaymentType::CHECK;
        $this->assertSame('Check', $contrib->getPaymentType());

        $contrib->payment_type = \Galette\Entity\PaymentType::OTHER;
        $this->assertSame('Other', $contrib->getPaymentType());

        $contrib->payment_type = \Galette\Entity\PaymentType::CREDITCARD;
        $this->assertSame('Credit card', $contrib->getPaymentType());

        $contrib->payment_type = \Galette\Entity\PaymentType::TRANSFER;
        $this->assertSame('Transfer', $contrib->getPaymentType());

        $contrib->payment_type = \Galette\Entity\PaymentType::PAYPAL;
        $this->assertSame('Paypal', $contrib->getPaymentType());
    }

    /**
     * Test contribution creation
     *
     * @return void
     */
    public function testCreation(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();
        $this->login->logout();
    }

    /**
     * Test contributions can have an amount equals to zero
     *
     * @return void
     */
    public function testZeroAmountContribution(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            [
                'type' => 1 //annual fee
            ]
        );

        //create contribution for member
        $data = [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 1, //annual fee
            'montant_cotis' => 0,
            'type_paiement_cotis' => 3,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $contrib->date,
            'date_debut_cotis' => $contrib->begin_date,
            'date_fin_cotis' => $contrib->end_date,
        ];

        $check = $contrib->check($data, $contrib->getRequired(), []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $contrib->store();
        $this->assertTrue($store);
        $this->assertSame(0.0, $contrib->amount);

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            $contrib->id
        );
        $this->assertSame(0.0, $contrib->amount);
    }

    /**
     * Test donation update
     *
     * @return void
     */
    public function testDonationUpdate(): void
    {
        $this->getMemberOne();
        //create contribution for member
        $begin_date = new \DateTime(); // 2020-11-07
        $begin_date->sub(new \DateInterval('P5M')); // 2020-06-07
        $begin_date->add(new \DateInterval('P3D')); // 2020-06-10

        $due_date = new \DateTime(); //fake due date; not kept for donations.

        $data = [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 4, //donation
            'montant_cotis' => 12,
            'type_paiement_cotis' => 3,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $begin_date->format('Y-m-d'),
            'date_debut_cotis' => $begin_date->format('Y-m-d'),
            'date_fin_cotis' => $due_date->format('Y-m-d'),
        ];
        $this->logSuperAdmin();
        $contrib = $this->createContrib($data);
        $this->login->logout();
        $this->assertSame(
            [
                'id_type_cotis'     => 1,
                'id_adh'            => 1,
                'date_enreg'        => 1,
                'date_debut_cotis'  => 1,
                'date_fin_cotis'    => 0,
                'montant_cotis'     => 0
            ],
            $this->contrib->getRequired()
        );

        $this->logSuperAdmin();
        $this->assertTrue($contrib->load($contrib->id));
        $this->assertNull($contrib->end_date);

        $data = [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 4, //donation
            'montant_cotis' => 1280,
            'type_paiement_cotis' => 4,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $begin_date->format('Y-m-d'),
            'date_debut_cotis' => $begin_date->format('Y-m-d'),
            'date_fin_cotis' => $due_date->format('Y-m-d'),
        ];
        $this->createContrib($data);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, $this->contrib->id);
        $this->assertSame(1280.00, $contrib->amount);
        $this->assertNull($contrib->end_date);

        //empty amount
        $data = [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 4, //donation
            'montant_cotis' => 0,
            'type_paiement_cotis' => 4,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $begin_date->format('Y-m-d'),
            'date_debut_cotis' => $begin_date->format('Y-m-d'),
            'date_fin_cotis' => $due_date->format('Y-m-d'),
        ];
        $this->createContrib($data);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, $this->contrib->id);
        $this->assertSame(0.00, $contrib->amount);
        $this->assertNull($contrib->end_date);
    }

    /**
     * Test contribution update
     *
     * @return void
     */
    public function testContributionUpdate(): void
    {
        $this->logSuperAdmin();

        $this->getMemberOne();
        //create contribution for member
        $begin_date = new \DateTime(); // 2020-11-07
        $begin_date->sub(new \DateInterval('P5M')); // 2020-06-07
        $begin_date->add(new \DateInterval('P3D')); // 2020-06-10

        $due_date = clone $begin_date;
        $due_date->add(new \DateInterval('P1Y'));
        $due_date->sub(new \DateInterval('P1D'));

        //instantiate contribution as annual fee
        $this->contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            [
                'type' => 1 //annual fee
            ]
        );
        $this->assertSame(
            [
                'id_type_cotis'     => 1,
                'id_adh'            => 1,
                'date_enreg'        => 1,
                'date_debut_cotis'  => 1,
                'date_fin_cotis'    => 1, //should be 1
                'montant_cotis'     => 1 // should be 1
            ],
            $this->contrib->getRequired()
        );

        $data = [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 1, //annual fee
            'montant_cotis' => 0,
            'type_paiement_cotis' => 3,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $begin_date->format('Y-m-d'),
            'date_debut_cotis' => $begin_date->format('Y-m-d'),
            'date_fin_cotis' => $due_date->format('Y-m-d'),
        ];

        $this->createContrib($data, $this->contrib);

        $this->assertSame(0.0, $this->contrib->amount);
        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, $this->contrib->id);
        $this->assertSame(0.0, $contrib->amount);

        //change amount
        $data['montant_cotis'] = 42;
        $check = $contrib->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $contrib->store();
        $this->assertTrue($store);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, $this->contrib->id);
        $this->assertSame(42.0, $contrib->amount);

        //change amount back to 0
        $data['montant_cotis'] = 0;
        $check = $contrib->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $contrib->store();
        $this->assertTrue($store);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, $this->contrib->id);
        $this->assertSame(0.0, $contrib->amount);
    }

    /**
     * Test end date retrieving
     * This is based on some Preferences parameters
     *
     * @return void
     */
    public function testRetrieveEndDate(): void
    {
        global $preferences;

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => 1] //annual fee
        );

        // First, check for 12 months renewal
        $due_date = new \DateTime();
        $due_date->add(new \DateInterval('P1Y'));
        $due_date->sub(new \DateInterval('P1D'));
        $this->assertSame($due_date->format('Y-m-d'), $contrib->end_date);

        // Second, test with beginning of membership date
        $preferences->pref_beg_membership = '29/05';
        $due_date = new \DateTime();
        $due_date->setDate((int)date('Y'), 5, 28);
        if ($due_date <= new \DateTime()) {
            $due_date->add(new \DateInterval('P1Y'));
        }

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => 1] // annual fee
        );
        $this->assertSame($due_date->format('Y-m-d'), $contrib->end_date);

        // Third, test with beginning of membership date and 2 last months offered
        $begin_date = new \DateTime();
        $begin_date->add(new \DateInterval('P1M'));
        $preferences->pref_beg_membership = $begin_date->format('01/m');
        $preferences->pref_membership_offermonths = 2;
        $due_date = new \DateTime($begin_date->format('Y-m-01'));
        $due_date->add(new \DateInterval('P1Y'));
        $due_date->sub(new \DateInterval('P1D'));

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => 1] // annual fee
        );
        $this->assertSame($due_date->format('Y-m-d'), $contrib->end_date);

        //then, test with a contribution type with a 2 months extension (will be ignored since we setup beg membershipe date)
        //create monthly fee type - 2 months extension
        $contribtype = new \Galette\Entity\ContributionsTypes($this->zdb);
        $this->assertTrue($contribtype->add('FAKER' . $this->seed, 10.00, 2));

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => $contribtype->id] // "monthly" fee
        );
        $this->assertSame($due_date->format('Y-m-d'), $contrib->end_date);

        //reset
        $preferences->pref_beg_membership = $this->preferences->getDefaults()['pref_beg_membership'];
        $preferences->pref_membership_ext = $this->preferences->getDefaults()['pref_membership_ext'];
        $preferences->pref_membership_offermonths = $this->preferences->getDefaults()['pref_membership_offermonths'];

        //unset pref_beg_membership and pref_membership_ext
        $preferences->pref_beg_membership = '';
        $preferences->pref_membership_ext = 0;

        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Unable to define end date; none of pref_beg_membership nor pref_membership_ext are defined!');
        new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => 1] //annual fee
        );
    }

    /**
     * Test monthly contribution
     *
     * @return void
     */
    public function testMonthlyContribution(): void
    {
        //create monthly fee type - 2 months extension
        $contribtype = new \Galette\Entity\ContributionsTypes($this->zdb);
        $this->assertTrue($contribtype->add('FAKER' . $this->seed, 10.00, 2));

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => $contribtype->id] //monthly fee
        );

        $due_date = new \DateTime();
        $due_date->add(new \DateInterval('P2M'));
        $due_date->sub(new \DateInterval('P1D'));
        $this->assertSame($due_date->format('Y-m-d'), $contrib->end_date);
    }

    /**
     * Test checkOverlap method
     *
     * @return void
     */
    public function testCheckOverlap(): void
    {
        $this->logSuperAdmin();
        $adh = new \Galette\Entity\Adherent($this->zdb);
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $check = $adh->check(
            [
                'nom_adh'                   => 'Overlapped',
                'date_crea_adh'             => date(_T("Y-m-d")),
                \Galette\Entity\Status::PK  => \Galette\Entity\Status::DEFAULT_STATUS,
                'fingerprint'               => 'FAKER' . $this->seed
            ],
            [],
            []
        );
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $adh->store();
        $this->assertTrue($store);

        //create first contribution for member
        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);

        $now = new \DateTime();
        $due_date = clone $now;
        $due_date->add(new \DateInterval('P1Y'));
        $due_date->sub(new \DateInterval('P1D'));
        $data = [
            \Galette\Entity\Adherent::PK            => $adh->id,
            \Galette\Entity\ContributionsTypes::PK  => 1, //annual fee
            'montant_cotis'                         => 20,
            'type_paiement_cotis'                   => \Galette\Entity\PaymentType::CHECK,
            'date_enreg'                            => $now->format(_T("Y-m-d")),
            'date_debut_cotis'                      => $now->format(_T("Y-m-d")),
            'date_fin_cotis'                        => $due_date->format(_T("Y-m-d")),
            'info_cotis'                            => 'FAKER' . $this->seed
        ];

        $check = $contrib->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($contrib->checkOverlap());

        $store = $contrib->store();
        $this->assertTrue($store);

        //load member from db
        $adh = new \Galette\Entity\Adherent($this->zdb, $adh->id);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P3M'));
        $due_date = clone $begin_date;
        $due_date->add(new \DateInterval('P1Y'));
        $due_date->sub(new \DateInterval('P1D'));
        $data = [
            \Galette\Entity\Adherent::PK            => $adh->id,
            \Galette\Entity\ContributionsTypes::PK  => 1, //annual fee
            'montant_cotis'                         => 20,
            'type_paiement_cotis'                   => \Galette\Entity\PaymentType::CHECK,
            'date_enreg'                            => $now->format(_T("Y-m-d")),
            'date_debut_cotis'                      => $begin_date->format(_T("Y-m-d")),
            'date_fin_cotis'                        => $due_date->format(_T("Y-m-d")),
            'info_cotis'                            => 'FAKER' . $this->seed
        ];

        $check = $contrib->check($data, [], []);
        $this->assertSame(
            [
                '- Membership period overlaps period starting at ' . $now->format('Y-m-d')
            ],
            $check
        );
        $this->expectLogEntry(
            \Analog::ERROR,
            '- Membership period overlaps period starting at ' . $now->format('Y-m-d')
        );

        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Existing errors prevents storing contribution');
        $contrib->store();
    }

    /**
     * Test fields labels
     *
     * @return void
     */
    public function testGetFieldLabel(): void
    {
        $this->assertSame(
            'Amount',
            $this->contrib->getFieldLabel('montant_cotis')
        );

        $this->assertSame(
            'Date of contribution',
            $this->contrib->getFieldLabel('date_debut_cotis')
        );

        $this->contrib->type = 1;
        $this->assertSame(
            'Start date of membership',
            $this->contrib->getFieldLabel('date_debut_cotis')
        );

        $this->assertSame(
            'Comments',
            $this->contrib->getFieldLabel('info_cotis')
        );
    }

    /**
     * Test contribution loading
     *
     * @return void
     */
    public function testLoad(): void
    {
        global $login;
        $this->login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, $this->i18n])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin'])
            ->getMock();
        $this->login->method('isLogged')->willReturn(true);
        $this->login->method('isStaff')->willReturn(true);
        $this->login->method('isAdmin')->willReturn(true);
        $login = $this->login;

        $this->getMemberOne();

        //create contribution for member
        $this->createContribution();

        $id = $this->contrib->id;
        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);

        $this->assertTrue($contrib->load((int)$id));
        $this->checkContribExpected($contrib);

        $this->assertFalse($contrib->load(1355522012));
        $this->expectLogEntry(
            \Analog::ERROR,
            'No contribution #1355522012 (user )'
        );
    }

    /**
     * Test contribution removal
     *
     * @return void
     */
    public function testRemove(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();
        $this->createContribution();

        $this->assertTrue($this->contrib->remove());
        $this->expectNoLogEntry();
        $this->assertFalse($this->contrib->remove());
        $this->expectLogEntry(
            \Analog::WARNING,
            'Contribution has not been removed!'
        );
    }

    /**
     * Test can* methods
     *
     * @return void
     */
    public function testCan(): void
    {
        global $preferences;
        $this->assertFalse($preferences->pref_bool_groupsmanagers_see_contributions);

        $this->logSuperAdmin();
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();
        $contrib = $this->contrib;
        $this->login->logOut();

        $this->assertFalse($contrib->canShow($this->login));

        //Superadmin can fully change contributions
        $this->logSuperAdmin();

        $this->assertTrue($contrib->canShow($this->login));

        //logout
        $this->login->logOut();
        $this->assertFalse($this->login->isLogged());

        //Member can fully change its own contributions
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isLogged());
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $this->assertTrue($contrib->canShow($this->login));

        //logout
        $this->login->logOut();
        $this->assertFalse($this->login->isLogged());

        //Another member has no access
        $member2 = $this->getMemberTwo();
        $mdata = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isLogged());
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $this->assertFalse($contrib->canShow($this->login));

        //parents can chow change children contributions
        $this->getMemberOne();
        $member = $this->adh;
        $mdata = $this->dataAdherentOne();
        global $login;
        $login = $this->login;
        $this->logSuperAdmin();

        $child_data = [
            'nom_adh'       => 'Doe',
            'prenom_adh'    => 'Johny',
            'parent_id'     => $member->id,
            'attach'        => true,
            'login_adh'     => 'child.johny.doe',
            'fingerprint' => 'FAKER' . $this->seed
        ];
        $child = $this->createMember($child_data);
        $cid = $child->id;

        //contribution for child
        $begin_date = new \DateTime(); // 2020-11-07
        $begin_date->sub(new \DateInterval('P5M')); // 2020-06-07
        $begin_date->add(new \DateInterval('P3D')); // 2020-06-10

        $due_date = clone $begin_date;
        $due_date->add(new \DateInterval('P1Y'));
        $due_date->sub(new \DateInterval('P1D'));

        $data = [
            'id_adh' => $cid,
            'id_type_cotis' => 1,
            'montant_cotis' => 25,
            'type_paiement_cotis' => 3,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $begin_date->format('Y-m-d'),
            'date_debut_cotis' => $begin_date->format('Y-m-d'),
            'date_fin_cotis' => $due_date->format('Y-m-d'),
        ];
        $ccontrib = $this->createContrib($data);

        $this->login->logOut();

        //load child from db
        $child = new \Galette\Entity\Adherent($this->zdb);
        $child->enableDep('parent');
        $this->assertTrue($child->load($cid));

        $this->assertSame($child_data['nom_adh'], $child->name);
        $this->assertInstanceOf(\Galette\Entity\Adherent::class, $child->parent);
        $this->assertSame($member->id, $child->parent->id);
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));

        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isLogged());
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $this->assertTrue($ccontrib->canShow($this->login));

        //logout
        $this->login->logOut();
        $this->assertFalse($this->login->isLogged());

        //tests for group managers
        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member2]));
        $this->assertTrue($g1->setMembers([$member]));

        $mdata = $this->dataAdherentTwo();
        $this->assertTrue($this->login->logIn($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($contrib->canShow($this->login));

        $preferences->pref_bool_groupsmanagers_see_contributions = true;
        $can_show = $contrib->canShow($this->login);
        $preferences->pref_bool_groupsmanagers_see_contributions = $this->preferences->getDefaults()['pref_bool_groupsmanagers_see_contributions'];
        $this->assertTrue($can_show);
    }

    /**
     * Test next year contribution
     *
     * @return void
     */
    public function testNextYear(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();

        //create contribution for member
        $begin_date = new \DateTime(); // 2023-12-30
        $ny_begin_date = clone $begin_date; // 2023-12-30
        $end_date = clone $begin_date;
        $begin_date->sub(new \DateInterval('P1Y')); // 2022-12-30
        $end_date->sub(new \DateInterval('P1D')); // 2023-12-29

        $data = [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 1, //contribution
            'montant_cotis' => 100,
            'type_paiement_cotis' => 3,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $begin_date->format('Y-m-d'),
            'date_debut_cotis' => $begin_date->format('Y-m-d'),
            'duree_mois_cotis' => 12
        ];
        $this->createContrib($data);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, $this->contrib->id);
        $this->assertSame(100.00, $contrib->amount);
        $this->assertSame($end_date->format('Y-m-d'), $contrib->end_date);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, ['type' => 1, 'adh' => $this->adh->id]);
        $this->assertSame($ny_begin_date->format('Y-m-d'), $contrib->begin_date);
    }

    /**
     * Test next year contribution from a 0.9.x
     *
     * @return void
     */
    public function testNextYearFrom096(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();

        //create contribution for member
        $begin_date = new \DateTime(); // 2023-12-30
        $ny_begin_date = clone $begin_date; // 2023-12-30
        $end_date = clone $begin_date;
        $due_date = clone $begin_date;

        $begin_date->sub(new \DateInterval('P1Y')); // 2022-12-30

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);
        $insert = $this->zdb->insert(\Galette\Entity\Contribution::TABLE);
        $insert->values(
            [
                'id_adh' => $this->adh->id,
                'id_type_cotis' => 1, //contribution
                'montant_cotis' => 100,
                'type_paiement_cotis' => 3,
                'info_cotis' => 'FAKER' . $this->seed,
                'date_enreg' => $begin_date->format('Y-m-d'),
                'date_debut_cotis' => $begin_date->format('Y-m-d'),
                'date_fin_cotis' => $due_date->format('Y-m-d')
            ]
        );
        $add = $this->zdb->execute($insert);
        $this->assertSame(1, $add->count());
        $contribution_id = (int)($add->getGeneratedValue() ?? $this->zdb->getLastGeneratedValue($contrib));

        $update = $this->zdb->update(\Galette\Entity\Adherent::TABLE);
        $update->set(
            ['date_echeance' => $due_date->format('Y-m-d')]
        )->where(
            [\Galette\Entity\Adherent::PK => $this->adh->id]
        );
        $this->zdb->execute($update);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, $contribution_id);
        $this->assertSame(100.00, $contrib->amount);
        $this->assertSame($end_date->format('Y-m-d'), $contrib->end_date);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, ['type' => 1, 'adh' => $this->adh->id, 'payment_type' => 1]);
        $this->assertSame($ny_begin_date->format('Y-m-d'), $contrib->begin_date);

        $check = $contrib->check(['type_paiement_cotis' => 1, 'montant_cotis' => 1, 'info_cotis' => 'FAKER' . $this->seed], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $contrib->store();
        $this->assertTrue($store);
    }

    /**
     * Test contribution end date is set after start date - when relevant
     *
     * @return void
     */
    public function testEndDateBeforeStartDate(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();

        $now = new \DateTime(); // 2020-11-07
        $begin_date = clone $now;

        $due_date = clone $now; //due date is before begin date
        $due_date->sub(new \DateInterval('P1Y')); // 2019-11-07

        $contrib_data = $this->getContribData();
        $contrib_data['date_debut_cotis'] = $begin_date->format('Y-m-d');
        $contrib_data['duree_mois_cotis'] = 6;
        $contrib_data['date_fin_cotis'] = $due_date->format('Y-m-d');

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => 1] //annual fee
        );
        $check = $contrib->check($contrib_data, [], []);
        $this->assertTrue($check);
        $this->assertSame($contrib->begin_date, $contrib_data['date_debut_cotis']);
        //end date is calculated, not the one sent
        $this->assertNotSame($contrib->end_date, $contrib_data['date_fin_cotis']);

        global $preferences;
        $preferences->pref_beg_membership = '01/09';
        $preferences->pref_membership_ext = '';

        $this->assertTrue($preferences->store());

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => 1] //annual fee
        );
        $check = $contrib->check($contrib_data, [], []);

        $preferences->pref_beg_membership = $this->preferences->getDefaults()['pref_beg_membership'];
        $preferences->pref_membership_ext = $this->preferences->getDefaults()['pref_membership_ext'];
        $this->assertTrue($preferences->store());

        $this->assertEquals($contrib->begin_date, $contrib_data['date_debut_cotis']);
        $this->assertNotTrue($check);
        $this->expectLogEntry(\Analog::ERROR, '- The end date must be after the start date!');
        $this->assertSame(['- The end date must be after the start date!'], $check);
    }

    /**
     * Test login checks
     *
     * @return void
     */
    public function testCheckLogin(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();
        $this->login->logout();

        $now = new \DateTime(); // 2020-11-07
        $begin_date = clone $now;

        $due_date = clone $now; //due date is before begin date
        $due_date->sub(new \DateInterval('P1Y')); // 2019-11-07

        $contrib_data = $this->getContribData();
        $contrib_data['date_debut_cotis'] = $begin_date->format('Y-m-d');
        $contrib_data['duree_mois_cotis'] = 6;
        $contrib_data['date_fin_cotis'] = $due_date->format('Y-m-d');

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => 1] //annual fee
        );

        //user not logged-in, check fails.
        $check = $contrib->check($contrib_data, [], []);
        $this->assertNotTrue($check);
        $this->expectLogEntry(
            \Analog::ERROR,
            'Please select a member from a group you manage.'
        );
        $this->assertSame(
            $check,
            [
                '- Please select a member from a group you manage.',
            ]
        );

        //force no check login, check passes
        $check = $contrib->setNoCheckLogin()->check($contrib_data, [], []);
        $this->assertTrue($check);
        $this->expectNoLogEntry();
    }
}
