<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Webhooks;

use ArrayObject;
use Galette\Core\Authentication;
use Galette\Entity\Adherent;
use Galette\Tests\GaletteTestCase;
use Galette\Webhooks\Payload\PayloadBuilder;
use Galette\Webhooks\WebhookEvent;
use InvalidArgumentException;

use function Safe\json_encode;

/**
 * Webhooks payloads tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Payload extends GaletteTestCase
{
    protected int $seed = 20261004203000;

    /**
     * Get builder
     */
    private function getBuilder(): PayloadBuilder
    {
        return $this->container->get(PayloadBuilder::class);
    }

    /**
     * Test envelope
     */
    public function testEnvelope(): void
    {
        $envelope = $this->getBuilder()->ping();
        $json = json_encode($envelope);
        $this->assertMatchesRegularExpression(
            '/^\{"type":"webhook\.ping","timestamp":"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}",'
            . '"data":\{"message":"This is a test event sent from Galette\."\}\}$/',
            $json
        );
    }

    /**
     * Test member data is filtered by access level
     */
    public function testMember(): void
    {
        $member = $this->getMemberOne();
        $builder = $this->getBuilder();

        $data = $builder->build(WebhookEvent::MemberCreated, $member, Authentication::ACCESS_STAFF)->data;
        $this->assertSame($member->id, $data['id']);
        $this->assertSame('Durand', $data['name']);
        $this->assertSame('René', $data['surname']);
        $this->assertSame('Martel', $data['town']);
        $this->assertSame('2020-06-10', $data['creation_date']);
        $this->assertTrue($data['active']);
        //administrator flag is for administrators only
        $this->assertArrayNotHasKey('admin', $data);
        $this->assertSame(['id' => 9, 'label' => 'Non-member'], $data['status']);
        $this->assertNull($data['title']);
        $this->assertArrayHasKey('others_infos_admin', $data);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('mdp_adh', $data);

        $data = $builder->build(WebhookEvent::MemberCreated, $member, Authentication::ACCESS_ADMIN)->data;
        $this->assertFalse($data['admin']);
        $this->assertArrayNotHasKey('password', $data);

        //group managers do not see staff only fields
        $data = $builder->build(WebhookEvent::MemberUpdated, $member, Authentication::ACCESS_MANAGER)->data;
        $this->assertSame($member->id, $data['id']);
        $this->assertSame('Durand', $data['name']);
        $this->assertArrayNotHasKey('others_infos_admin', $data);
        $this->assertArrayNotHasKey('status', $data);
        $this->assertArrayNotHasKey('password', $data);
    }

    /**
     * Test removed member, only known from its database row
     */
    public function testRemovedMember(): void
    {
        $row = new ArrayObject(
            [
                Adherent::PK => '42',
                'nom_adh' => 'Durand',
                'prenom_adh' => 'René',
                'email_adh' => 'rene@example.com',
                'mdp_adh' => 'hash',
            ],
            ArrayObject::ARRAY_AS_PROPS
        );
        $data = $this->getBuilder()->build(WebhookEvent::MemberDeleted, $row, Authentication::ACCESS_STAFF)->data;
        $this->assertSame(
            ['id' => 42, 'name' => 'Durand', 'surname' => 'René', 'email' => 'rene@example.com'],
            $data
        );
    }

    /**
     * Test contribution data
     */
    public function testContribution(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();
        $this->createContribution();
        $builder = $this->getBuilder();

        $data = $builder->build(WebhookEvent::ContributionCreated, $this->contrib, Authentication::ACCESS_STAFF)->data;
        $this->assertSame($this->contrib->id, $data['id']);
        $this->assertSame('membership', $data['kind']);
        $this->assertSame(['id' => 1, 'label' => 'annual fee'], $data['type']);
        $this->assertEqualsWithDelta(92.0, $data['amount'], 0.001);
        $this->assertSame(3, $data['payment_type']['id']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $data['begin_date']);
        $this->assertNull($data['transaction_id']);
        $this->assertSame('FAKER' . $this->seed, $data['info']);
        $this->assertSame(
            [
                'id' => $this->adh->id,
                'name' => 'Durand',
                'surname' => 'René',
                'company_name' => null,
                'email' => 'meunier.josephine' . $this->seed . '@ledoux.com',
            ],
            $data['member']
        );

        //comments are kept for staff
        $data = $builder->build(WebhookEvent::ContributionCreated, $this->contrib, Authentication::ACCESS_MANAGER)->data;
        $this->assertArrayNotHasKey('info', $data);
    }

    /**
     * Test transaction data
     */
    public function testTransaction(): void
    {
        $this->logSuperAdmin();
        $member = $this->getMemberOne();
        $transaction = new \Galette\Entity\Transaction($this->zdb, $this->login);
        $this->assertTrue($transaction->check(
            [
                'id_adh' => $member->id,
                'trans_date' => '2026-10-01',
                'trans_amount' => 150,
                'trans_desc' => 'Desc ' . $this->seed,
                'type_paiement_trans' => \Galette\Entity\PaymentType::CHECK,
            ],
            [],
            []
        ));
        $this->assertTrue($transaction->store($this->history));

        $data = $this->getBuilder()->build(
            WebhookEvent::TransactionCreated,
            $transaction,
            Authentication::ACCESS_STAFF
        )->data;
        $this->assertSame($transaction->id, $data['id']);
        $this->assertSame('2026-10-01', $data['date']);
        $this->assertEqualsWithDelta(150.0, $data['amount'], 0.001);
        $this->assertSame(\Galette\Entity\PaymentType::CHECK, $data['payment_type']['id']);
        $this->assertSame('Desc ' . $this->seed, $data['description']);
        $this->assertSame($member->id, $data['member']['id']);
    }

    /**
     * Test group data
     */
    public function testGroup(): void
    {
        global $zdb;
        $zdb = $this->zdb;

        $parent = new \Galette\Entity\Group();
        $parent->setName('Payload parent');
        $this->assertTrue($parent->store());
        $group = new \Galette\Entity\Group();
        $group->setName('Payload child');
        $group->setParentGroup($parent->getId());
        $this->assertTrue($group->store());

        $data = $this->getBuilder()->build(WebhookEvent::GroupCreated, $group, Authentication::ACCESS_MANAGER)->data;
        $this->assertSame($group->getId(), $data['id']);
        $this->assertSame('Payload child', $data['name']);
        $this->assertSame($parent->getId(), $data['parent_id']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}/', $data['creation_date']);
    }

    /**
     * Test unsupported objects are refused
     */
    public function testUnsupported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->getBuilder()->build(WebhookEvent::MemberCreated, new \stdClass(), Authentication::ACCESS_STAFF);
    }
}
