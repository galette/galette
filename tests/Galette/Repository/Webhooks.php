<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Repository;

use Galette\Entity\Webhook;
use Galette\Tests\GaletteTestCase;
use Galette\Webhooks\WebhookEvent;

/**
 * Webhooks repository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Webhooks extends GaletteTestCase
{
    /**
     * Create a webhook
     *
     * @param string              $name   Name
     * @param array<WebhookEvent> $events Events
     * @param bool                $active Active or not
     */
    private function createWebhook(string $name, array $events, bool $active = true): Webhook
    {
        $webhook = (new Webhook())
            ->setName($name)
            ->setUrl('https://example.com/' . $name)
            ->setEvents($events)
            ->setActive($active);
        $this->assertTrue($webhook->save());
        return $webhook;
    }

    /**
     * Test lists
     */
    public function testLists(): void
    {
        $repository = new \Galette\Repository\Webhooks($this->zdb);
        $this->assertSame([], $repository->getList());
        $this->assertSame([], $repository->getActive());

        $members = $this->createWebhook('members', [WebhookEvent::MemberCreated, WebhookEvent::MemberDeleted]);
        $all = $this->createWebhook('all', WebhookEvent::subscribable());
        $inactive = $this->createWebhook('inactive', WebhookEvent::subscribable(), active: false);

        //ordered by name
        $this->assertSame(
            [$all->getId(), $inactive->getId(), $members->getId()],
            array_keys($repository->getList())
        );
        $this->assertSame([$members->getId(), $all->getId()], array_keys($repository->getActive()));
        $this->assertSame(
            [$members->getId(), $all->getId()],
            array_keys($repository->getActiveFor(WebhookEvent::MemberDeleted))
        );
        $this->assertSame([$all->getId()], array_keys($repository->getActiveFor(WebhookEvent::GroupCreated)));
    }
}
