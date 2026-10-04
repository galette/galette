<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Console\Command;

use Galette\Console\Command\ProcessWebhooks as ProcessWebhooksCommand;
use Galette\Entity\Webhook;
use Galette\Tests\GaletteTestCase;
use Galette\Webhooks\DeliveryQueue;
use Galette\Webhooks\Dispatcher;
use Galette\Webhooks\Payload\Envelope;
use Galette\Webhooks\WebhookEvent;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Webhooks processing command tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ProcessWebhooks extends GaletteTestCase
{
    /**
     * Run the command
     *
     * @param array<string, mixed> $input Command input
     */
    private function runCommand(array $input = []): CommandTester
    {
        $commandTester = new CommandTester(new ProcessWebhooksCommand(GALETTE_ROOT));
        $commandTester->execute($input);
        return $commandTester;
    }

    /**
     * Test pending deliveries are sent
     */
    public function testProcess(): void
    {
        $webhook = (new Webhook())
            ->setName('console')
            ->setUrl('https://example.com/hook')
            ->setEvents([WebhookEvent::GroupCreated]);
        $this->assertTrue($webhook->save());
        $queue = new DeliveryQueue($this->zdb);
        foreach ([1, 2] as $id) {
            $queue->enqueue(
                $webhook,
                new Envelope(WebhookEvent::GroupCreated, ['id' => $id]),
                DeliveryQueue::generateMessageId()
            );
        }

        $mock = new MockHandler([new Response(200), new Response(500)]);
        $this->container->set(
            Dispatcher::class,
            new Dispatcher(
                $this->zdb,
                $this->container->get(ClockInterface::class),
                new Client(['handler' => HandlerStack::create($mock)])
            )
        );

        $commandTester = $this->runCommand(['--limit' => 5]);
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString(
            'Webhooks processed: 1 delivered, 1 to be retried, 0 failed.',
            $commandTester->getDisplay()
        );
        $this->assertSame(0, $mock->count());
    }

    /**
     * Test invalid limit
     */
    public function testInvalidLimit(): void
    {
        $commandTester = $this->runCommand(['--limit' => 0]);
        $this->assertSame(Command::INVALID, $commandTester->getStatusCode());
        $this->assertStringContainsString('Limit must be a positive number.', $commandTester->getDisplay());
    }
}
