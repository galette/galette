<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Webhooks;

use Galette\Core\Authentication;
use Galette\Entity\Webhook;
use Galette\Tests\GaletteTestCase;
use Galette\Webhooks\DeliveryQueue;
use Galette\Webhooks\WebhookEvent;

use function Safe\json_decode;

/**
 * Webhooks listener (deliveries queuing) tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class WebhookListener extends GaletteTestCase
{
    protected int $seed = 20261004221500;

    /**
     * Create a webhook
     *
     * @param string              $name   Name
     * @param array<WebhookEvent> $events Events
     * @param int                 $level  Access level
     * @param bool                $active Active or not
     */
    private function createWebhook(string $name, array $events, int $level, bool $active = true): Webhook
    {
        $webhook = (new Webhook())
            ->setName($name)
            ->setUrl('https://example.com/' . $name)
            ->setEvents($events)
            ->setAccessLevel($level)
            ->setActive($active);
        $this->assertTrue($webhook->save());
        return $webhook;
    }

    /**
     * Get queued deliveries
     *
     * @return array<int, array<string, mixed>>
     */
    private function getDeliveries(): array
    {
        $select = $this->zdb->select(DeliveryQueue::TABLE);
        $select->order(DeliveryQueue::PK);
        $deliveries = [];
        foreach ($this->zdb->execute($select) as $row) {
            $deliveries[] = $row->getArrayCopy();
        }
        return $deliveries;
    }

    /**
     * Test nothing is queued without webhooks
     */
    public function testNoWebhook(): void
    {
        $this->getMemberOne();
        $this->assertSame([], $this->getDeliveries());
    }

    /**
     * Test deliveries are queued for subscribed and active webhooks
     */
    public function testEnqueue(): void
    {
        $staff = $this->createWebhook('staff', [WebhookEvent::MemberCreated], Authentication::ACCESS_STAFF);
        $manager = $this->createWebhook('manager', WebhookEvent::subscribable(), Authentication::ACCESS_MANAGER);
        $this->createWebhook(
            name: 'inactive',
            events: WebhookEvent::subscribable(),
            level: Authentication::ACCESS_ADMIN,
            active: false
        );

        $member = $this->getMemberOne();

        $deliveries = $this->getDeliveries();
        $this->assertCount(2, $deliveries);
        [$first, $second] = $deliveries;

        $this->assertSame([$staff->getId(), $manager->getId()], [(int)$first['id_webhook'], (int)$second['id_webhook']]);
        //same event, same message
        $this->assertSame($first['message_id'], $second['message_id']);
        $this->assertMatchesRegularExpression('/^msg_[0-9a-f]{32}$/', $first['message_id']);
        foreach ($deliveries as $delivery) {
            $this->assertSame('member.created', $delivery['event_type']);
            $this->assertSame(DeliveryQueue::STATUS_PENDING, (int)$delivery['status']);
            $this->assertSame(0, (int)$delivery['attempts']);
            $this->assertNotNull($delivery['next_attempt_at']);
        }

        //each payload is filtered at its webhook access level
        $staff_payload = json_decode($first['payload'], associative: true);
        $manager_payload = json_decode($second['payload'], associative: true);
        $this->assertSame('member.created', $staff_payload['type']);
        $this->assertSame($member->id, $staff_payload['data']['id']);
        $this->assertArrayHasKey('others_infos_admin', $staff_payload['data']);
        $this->assertArrayNotHasKey('others_infos_admin', $manager_payload['data']);

        //only the webhook subscribed to groups events gets them
        global $zdb;
        $zdb = $this->zdb;
        $group = new \Galette\Entity\Group();
        $group->setName('Webhooks group');
        $this->assertTrue($group->store());

        $deliveries = $this->getDeliveries();
        $this->assertCount(3, $deliveries);
        $this->assertSame($manager->getId(), (int)$deliveries[2]['id_webhook']);
        $this->assertSame('group.created', $deliveries[2]['event_type']);
        $this->assertSame(
            array_map(fn(array $delivery): int => (int)$delivery['id_delivery'], $deliveries),
            array_slice($this->container->get(\Galette\Events\WebhookListener::class)->getEnqueued(), -3)
        );
    }

    /**
     * Test deliveries belong to the transaction the event is sent from
     */
    public function testRollback(): void
    {
        $this->createWebhook('rollback', [WebhookEvent::GroupCreated], Authentication::ACCESS_STAFF);

        global $zdb;
        $zdb = $this->zdb;
        //tests already run in a transaction
        $this->assertTrue($this->zdb->inTransaction());
        $group = new \Galette\Entity\Group();
        $group->setName('Rolled back group');
        $this->assertTrue($group->store());
        $this->assertCount(1, $this->getDeliveries());

        $this->zdb->rollback();
        $this->assertSame([], $this->getDeliveries());
    }

    /**
     * Test a failure is logged, and does not prevent the change
     */
    public function testFailureIsLogged(): void
    {
        $webhook = $this->createWebhook('failing', [WebhookEvent::GroupCreated], Authentication::ACCESS_STAFF);

        $listener = new \Galette\Events\WebhookListener(
            new \Galette\Repository\Webhooks($this->zdb),
            new DeliveryQueue($this->zdb),
            fn() => throw new \RuntimeException('Builder is broken')
        );
        $group = new \Galette\Entity\Group();
        $listener->enqueue(WebhookEvent::GroupCreated, $group);

        $this->assertSame([], $listener->getEnqueued());
        $this->assertSame([], $this->getDeliveries());
        $this->expectLogEntry(
            \Analog\Analog::ERROR,
            'Unable to queue webhooks for group.created event | Builder is broken'
        );
        $this->assertTrue($webhook->isActive());
    }
}
