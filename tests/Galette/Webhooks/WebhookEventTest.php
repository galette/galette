<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Webhooks;

use Galette\Tests\GaletteTestCase;
use Galette\Webhooks\WebhookEvent;

/**
 * Webhooks events catalog tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class WebhookEventTest extends GaletteTestCase
{
    /**
     * Test mapping from internal events
     */
    public function testFromInternal(): void
    {
        $this->assertSame(WebhookEvent::MemberCreated, WebhookEvent::fromInternal('member.add'));
        $this->assertSame(WebhookEvent::ContributionDeleted, WebhookEvent::fromInternal('contribution.remove'));
        $this->assertSame(WebhookEvent::GroupUpdated, WebhookEvent::fromInternal('group.edit'));
        $this->assertNull(WebhookEvent::fromInternal('member.before_remove'));
        $this->assertNull(WebhookEvent::fromInternal('webhook.ping'));
    }

    /**
     * Test every subscribable event maps to a distinct internal one, and back
     */
    public function testSubscribable(): void
    {
        $subscribable = WebhookEvent::subscribable();
        $this->assertCount(count(WebhookEvent::cases()) - 1, $subscribable);
        $this->assertNotContains(WebhookEvent::Ping, $subscribable);

        $internals = [];
        foreach ($subscribable as $event) {
            $internal = (string)$event->getInternalName();
            $internals[] = $internal;
            $this->assertSame($event, WebhookEvent::fromInternal($internal));
            //public and internal events share their subject
            $this->assertSame($event->getSubject(), explode('.', $internal)[0]);
        }
        $this->assertSame($internals, array_unique($internals));
    }

    /**
     * Test grouping by subject
     */
    public function testGrouped(): void
    {
        $grouped = WebhookEvent::grouped();
        $this->assertSame(['Members', 'Contributions', 'Transactions', 'Groups'], array_keys($grouped));
        $this->assertSame(
            [WebhookEvent::GroupCreated, WebhookEvent::GroupUpdated, WebhookEvent::GroupDeleted],
            $grouped['Groups']
        );
        foreach (WebhookEvent::cases() as $event) {
            $this->assertNotSame('', $event->getLabel());
        }
    }
}
