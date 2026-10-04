<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Entity;

use Galette\Core\Authentication;
use Galette\Tests\GaletteTestCase;
use Galette\Webhooks\Signer;
use Galette\Webhooks\WebhookEvent;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Webhook entity tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Webhook extends GaletteTestCase
{
    /**
     * Get a valid webhook
     */
    private function getWebhook(): \Galette\Entity\Webhook
    {
        $webhook = new \Galette\Entity\Webhook();
        return $webhook
            ->setName('  My <b>receiver</b> ')
            ->setUrl(' https://example.com/hooks/galette ')
            ->setEvents([WebhookEvent::MemberCreated, WebhookEvent::GroupDeleted, WebhookEvent::MemberCreated]);
    }

    /**
     * Test creation, load, update and removal
     */
    public function testCrud(): void
    {
        $webhook = $this->getWebhook();
        $this->assertSame('My receiver', $webhook->getName());
        $this->assertSame('https://example.com/hooks/galette', $webhook->getUrl());
        $this->assertSame([], $webhook->validate());
        $this->assertTrue($webhook->save());

        $id = $webhook->getId();
        $secret = $webhook->getSecret();
        $this->assertStringStartsWith(Signer::SECRET_PREFIX, $secret);
        $this->assertNotNull($webhook->getCreationDate());

        $loaded = new \Galette\Entity\Webhook($id);
        $this->assertSame('My receiver', $loaded->getName());
        $this->assertSame($secret, $loaded->getSecret());
        $this->assertSame([WebhookEvent::MemberCreated, WebhookEvent::GroupDeleted], $loaded->getEvents());
        $this->assertTrue($loaded->subscribesTo(WebhookEvent::GroupDeleted));
        $this->assertFalse($loaded->subscribesTo(WebhookEvent::MemberUpdated));
        $this->assertSame(Authentication::ACCESS_STAFF, $loaded->getAccessLevel());
        $this->assertTrue($loaded->isActive());
        $this->assertFalse($loaded->isInsecure());

        //update keeps secret and creation date
        $loaded
            ->setActive(active: false)
            ->setAccessLevel(Authentication::ACCESS_ADMIN)
            ->setUrl('http://192.168.1.10:5678/webhook');
        $this->assertTrue($loaded->save());

        $reloaded = new \Galette\Entity\Webhook($id);
        $this->assertFalse($reloaded->isActive());
        $this->assertSame(Authentication::ACCESS_ADMIN, $reloaded->getAccessLevel());
        $this->assertTrue($reloaded->isInsecure());
        $this->assertSame($secret, $reloaded->getSecret());
        $this->assertSame($webhook->getCreationDate(), $reloaded->getCreationDate());

        //regenerated secret is stored
        $reloaded->regenerateSecret();
        $this->assertNotSame($secret, $reloaded->getSecret());
        $this->assertTrue($reloaded->save());
        $this->assertSame($reloaded->getSecret(), (new \Galette\Entity\Webhook($id))->getSecret());

        $this->assertTrue($reloaded->delete());
        $this->assertSame([], (new \Galette\Repository\Webhooks($this->zdb))->getList());
    }

    /**
     * Test validation errors
     */
    public function testValidate(): void
    {
        $webhook = new \Galette\Entity\Webhook();
        $this->assertSame(
            [
                'Name is mandatory.',
                'URL must be a valid http or https address, without credentials.',
                'Select at least one event.',
            ],
            $webhook->validate()
        );

        $webhook = $this->getWebhook()
            ->setName(str_repeat('a', \Galette\Entity\Webhook::NAME_MAX_LENGTH + 1))
            ->setAccessLevel(Authentication::ACCESS_SUPERADMIN);
        $this->assertSame(
            ['Name must not exceed 100 characters.', 'Invalid access level.'],
            $webhook->validate()
        );

        //the test event cannot be subscribed to
        $webhook = $this->getWebhook()->setEvents([WebhookEvent::Ping]);
        $this->assertSame([], $webhook->getEvents());
        $this->assertSame(['Select at least one event.'], $webhook->validate());
    }

    /**
     * URLs provider
     *
     * @return array<string, array{string, bool}>
     */
    public static function urlsProvider(): array
    {
        return [
            'https' => ['https://example.com/hook', true],
            'http with port' => ['http://localhost:5678/webhook/abc', true],
            'query string' => ['https://example.com/hook?token=abc', true],
            'empty' => ['', false],
            'no scheme' => ['example.com/hook', false],
            'ftp' => ['ftp://example.com/hook', false],
            'file' => ['file:///etc/passwd', false],
            'javascript' => ['javascript:alert(1)', false],
            'credentials' => ['https://user:pass@example.com/hook', false],
            'user only' => ['https://user@example.com/hook', false],
            'too long' => ['https://example.com/' . str_repeat('a', 2048), false],
        ];
    }

    /**
     * Test URL validation
     */
    #[DataProvider('urlsProvider')]
    public function testIsValidUrl(string $url, bool $expected): void
    {
        $this->assertSame($expected, \Galette\Entity\Webhook::isValidUrl($url));
    }
}
