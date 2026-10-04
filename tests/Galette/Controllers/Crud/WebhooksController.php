<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Core\Authentication;
use Galette\Entity\Webhook;
use Galette\Tests\GaletteRoutingTestCase;
use Galette\Webhooks\WebhookEvent;

/**
 * Webhooks controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class WebhooksController extends GaletteRoutingTestCase
{
    protected int $seed = 20261005093000;

    /**
     * Create a webhook
     */
    private function createWebhook(): Webhook
    {
        $webhook = (new Webhook())
            ->setName('n8n')
            ->setUrl('https://n8n.example.com/webhook/galette')
            ->setEvents([WebhookEvent::MemberCreated]);
        $this->assertTrue($webhook->save());
        return $webhook;
    }

    /**
     * Test access is for administrators only
     */
    public function testAccess(): void
    {
        $request = $this->createRequest('webhooks');

        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //staff members cannot manage webhooks
        $this->logSuperAdmin();
        $member = $this->getMemberOne();
        $this->getStaffMember($member);
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isStaff());
        $test_response = $this->app->handle($request);
        $this->expectAuthMiddlewareRefused($test_response);
        $this->resetStaffStatus($member, $member);
    }

    /**
     * Test list page
     */
    public function testList(): void
    {
        $this->logSuperAdmin();
        $request = $this->createRequest('webhooks');

        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $this->assertStringContainsString('No webhook yet', (string)$test_response->getBody());

        $webhook = $this->createWebhook();
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('https://n8n.example.com/webhook/galette', $body);
        $this->assertStringContainsString('member.created', $body);
        $this->assertStringContainsString(
            $this->routeparser->urlFor('editWebhook', ['id' => (string)$webhook->getId()]),
            $body
        );
        $this->assertStringContainsString('Never', $body);
    }

    /**
     * Test add page and action
     */
    public function testAdd(): void
    {
        $this->logSuperAdmin();

        $test_response = $this->app->handle($this->createRequest('addWebhook'));
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('name="events[]"', $body);
        $this->assertStringContainsString('value="group.deleted"', $body);
        //secret is only shown once stored
        $this->assertStringNotContainsString('Signing secret', $body);

        $request = $this->createRequest('doAddWebhook', [], 'POST')->withParsedBody([
            'name' => 'Automation',
            'url' => 'http://localhost:5678/webhook/galette',
            'events' => ['member.created', 'group.created', 'unknown.event'],
            'access_level' => (string)Authentication::ACCESS_MANAGER,
            'active' => '1',
        ]);
        $test_response = $this->app->handle($request);

        $webhooks = (new \Galette\Repository\Webhooks($this->zdb))->getList();
        $this->assertCount(1, $webhooks);
        $webhook = reset($webhooks);
        $this->assertSame('Automation', $webhook->getName());
        $this->assertSame([WebhookEvent::MemberCreated, WebhookEvent::GroupCreated], $webhook->getEvents());
        $this->assertSame(Authentication::ACCESS_MANAGER, $webhook->getAccessLevel());
        $this->assertTrue($webhook->isActive());

        //redirected to edit page, for the secret to be copied
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('editWebhook', ['id' => (string)$webhook->getId()])]],
            $test_response->getHeaders()
        );
        $this->expectFlashData([
            'success_detected' => ['Webhook Automation has been stored.'],
            'warning_detected' => ['This webhook does not use https: data will be sent unencrypted.'],
        ]);

        $test_response = $this->app->handle(
            $this->createRequest('editWebhook', ['id' => (string)$webhook->getId()])
        );
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Signing secret', $body);
        $this->assertStringContainsString($webhook->getSecret(), $body);
    }

    /**
     * Test invalid values are refused, and kept for the form
     */
    public function testAddInvalid(): void
    {
        $this->logSuperAdmin();

        $request = $this->createRequest('doAddWebhook', [], 'POST')->withParsedBody([
            'name' => 'Kept name',
            'url' => 'ftp://example.com',
            'access_level' => (string)Authentication::ACCESS_STAFF,
        ]);
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('addWebhook')]],
            $test_response->getHeaders()
        );
        $this->expectFlashData([
            'error_detected' => [
                'URL must be a valid http or https address, without credentials.',
                'Select at least one event.',
            ],
        ]);
        $this->assertSame([], (new \Galette\Repository\Webhooks($this->zdb))->getList());

        $test_response = $this->app->handle($this->createRequest('addWebhook'));
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('value="Kept name"', $body);
        $this->assertStringContainsString('value="ftp://example.com"', $body);

        //values are only kept once
        $test_response = $this->app->handle($this->createRequest('addWebhook'));
        $this->assertStringNotContainsString('value="Kept name"', (string)$test_response->getBody());
        $this->expectOK($test_response);
    }

    /**
     * Test edition
     */
    public function testEdit(): void
    {
        $this->logSuperAdmin();
        $webhook = $this->createWebhook();
        $id = (string)$webhook->getId();
        $secret = $webhook->getSecret();

        $request = $this->createRequest('doEditWebhook', ['id' => $id], 'POST')->withParsedBody([
            'name' => 'n8n renamed',
            'url' => 'https://n8n.example.com/webhook/other',
            'events' => ['contribution.created'],
            'access_level' => (string)Authentication::ACCESS_ADMIN,
            //unchecked: not posted
        ]);
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('webhooks')]],
            $test_response->getHeaders()
        );
        $this->expectFlashData(['success_detected' => ['Webhook n8n renamed has been stored.']]);

        $webhook = new Webhook((int)$id);
        $this->assertSame('n8n renamed', $webhook->getName());
        $this->assertSame([WebhookEvent::ContributionCreated], $webhook->getEvents());
        $this->assertSame(Authentication::ACCESS_ADMIN, $webhook->getAccessLevel());
        $this->assertFalse($webhook->isActive());
        $this->assertSame($secret, $webhook->getSecret());

        //new secret
        $test_response = $this->app->handle($this->createRequest('regenerateWebhookSecret', ['id' => $id], 'POST'));
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('editWebhook', ['id' => $id])]],
            $test_response->getHeaders()
        );
        $this->expectFlashData([
            'success_detected' => ['A new secret has been generated, update it on the receiver side.'],
        ]);
        $this->assertNotSame($secret, (new Webhook((int)$id))->getSecret());

        //unknown webhook
        $test_response = $this->app->handle($this->createRequest('editWebhook', ['id' => '999999']));
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('webhooks')]],
            $test_response->getHeaders()
        );
        $this->expectFlashData(['error_detected' => ['This webhook does not exist.']]);
        $this->expectLogEntry(\Analog\Analog::ERROR, 'An error occurred loading Galette\Entity\Webhook #999999');
    }

    /**
     * Test removal
     */
    public function testRemove(): void
    {
        $this->logSuperAdmin();
        $webhook = $this->createWebhook();
        $id = (string)$webhook->getId();

        $test_response = $this->app->handle($this->createRequest('removeWebhook', ['id' => $id]));
        $this->expectOK($test_response);
        $this->assertStringContainsString('Remove webhook n8n', (string)$test_response->getBody());

        $request = $this->createRequest('doRemoveWebhook', ['id' => $id], 'POST')->withParsedBody([
            'confirm' => '1',
            'id' => $id,
        ]);
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('webhooks')]],
            $test_response->getHeaders()
        );
        $this->expectFlashData(['success_detected' => ['Successfully deleted!']]);
        $this->assertSame([], (new \Galette\Repository\Webhooks($this->zdb))->getList());
    }
}
