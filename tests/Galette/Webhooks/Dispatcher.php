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
use Galette\Entity\Webhook;
use Galette\Tests\GaletteTestCase;
use Galette\Webhooks\DeliveryQueue;
use Galette\Webhooks\Payload\Envelope;
use Galette\Webhooks\Signer;
use Galette\Webhooks\WebhookEvent;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\RequestInterface;
use Safe\DateTimeImmutable;

/**
 * Webhooks dispatcher tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Dispatcher extends GaletteTestCase
{
    private MockHandler $mock;
    /** @var ArrayObject<int, array<mixed>> */
    private ArrayObject $requests;
    /** @var ArrayObject<string, int> */
    private ArrayObject $time;
    private ClockInterface $clock;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->mock = new MockHandler();
        $this->requests = new ArrayObject();
        $this->time = new ArrayObject(['now' => (new DateTimeImmutable('2026-10-04 12:00:00'))->getTimestamp()]);
        $time = $this->time;
        $this->clock = new readonly class ($time) implements ClockInterface {
            /**
             * Constructor
             *
             * @param ArrayObject<string, int> $time Shared current timestamp
             */
            public function __construct(private ArrayObject $time)
            {
            }

            /**
             * Current time
             */
            public function now(): \DateTimeImmutable
            {
                return (new DateTimeImmutable())->setTimestamp($this->time['now']);
            }
        };
    }

    /**
     * Get dispatcher, with a mocked HTTP client
     */
    private function getDispatcher(): \Galette\Webhooks\Dispatcher
    {
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->requests));
        return new \Galette\Webhooks\Dispatcher($this->zdb, $this->clock, new Client(['handler' => $stack]));
    }

    /**
     * Move clock forward
     *
     * @param int $seconds Seconds
     */
    private function wait(int $seconds): void
    {
        $this->time['now'] += $seconds;
    }

    /**
     * Create a webhook and queue a delivery for it
     *
     * @param bool $active Active webhook
     *
     * @return array{Webhook, int} Webhook and delivery identifier
     */
    private function queue(bool $active = true): array
    {
        $webhook = (new Webhook())
            ->setName('receiver')
            ->setUrl('https://example.com/hook')
            ->setEvents([WebhookEvent::GroupCreated])
            ->setAccessLevel(Authentication::ACCESS_STAFF)
            ->setActive($active);
        $this->assertTrue($webhook->save());

        $envelope = new Envelope(WebhookEvent::GroupCreated, ['id' => 1, 'name' => 'A group']);
        $id = (new DeliveryQueue($this->zdb))->enqueue($webhook, $envelope, 'msg_test');
        //queued rows are due "now" by the system clock, which is ahead of the test one
        $this->setDelivery($id, ['next_attempt_at' => $this->clock->now()->format('Y-m-d H:i:s')]);
        return [$webhook, $id];
    }

    /**
     * Get delivery row
     *
     * @param int $id Delivery identifier
     *
     * @return ArrayObject<string, mixed>
     */
    private function getDelivery(int $id): ArrayObject
    {
        $select = $this->zdb->select(DeliveryQueue::TABLE);
        $select->where([DeliveryQueue::PK => $id]);
        return $this->zdb->execute($select)->current();
    }

    /**
     * Change delivery values
     *
     * @param int                  $id     Delivery identifier
     * @param array<string, mixed> $values Values
     */
    private function setDelivery(int $id, array $values): void
    {
        $update = $this->zdb->update(DeliveryQueue::TABLE);
        $update->set($values)->where([DeliveryQueue::PK => $id]);
        $this->zdb->execute($update);
    }

    /**
     * Test a successful delivery and its signed request
     */
    public function testDelivered(): void
    {
        [$webhook, $id] = $this->queue();
        $this->mock->append(new Response(204));

        $report = $this->getDispatcher()->process();
        $this->assertSame(1, $report->getDelivered());
        $this->assertSame(1, $report->getTotal());

        $this->assertCount(1, $this->requests);
        $request = $this->requests[0]['request'];
        $this->assertInstanceOf(RequestInterface::class, $request);
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://example.com/hook', (string)$request->getUri());
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertStringStartsWith('Galette/', $request->getHeaderLine('User-Agent'));
        $this->assertSame('msg_test', $request->getHeaderLine('webhook-id'));
        $this->assertSame((string)$this->time['now'], $request->getHeaderLine('webhook-timestamp'));

        $body = (string)$request->getBody();
        $this->assertSame($this->getDelivery($id)->payload, $body);
        $this->assertStringStartsWith('{"type":"group.created","timestamp":', $body);
        $this->assertTrue(
            (new Signer($webhook->getSecret()))->verify(
                message_id: $request->getHeaderLine('webhook-id'),
                timestamp: $request->getHeaderLine('webhook-timestamp'),
                signatures: $request->getHeaderLine('webhook-signature'),
                payload: $body,
                now: $this->time['now']
            )
        );

        $row = $this->getDelivery($id);
        $this->assertSame(DeliveryQueue::STATUS_DELIVERED, (int)$row->status);
        $this->assertSame(1, (int)$row->attempts);
        $this->assertSame(204, (int)$row->response_code);
        $this->assertSame('2026-10-04 12:00:00', $row->delivered_at);
        $this->assertNull($row->next_attempt_at);

        //nothing left to send
        $this->assertSame(0, $this->getDispatcher()->process()->getTotal());
    }

    /**
     * Test failed attempts are retried with a growing delay, then given up
     */
    public function testRetries(): void
    {
        [, $id] = $this->queue();
        $dispatcher = $this->getDispatcher();

        for ($attempt = 1; $attempt < \Galette\Webhooks\Dispatcher::MAX_ATTEMPTS; ++$attempt) {
            $this->mock->append(new Response(500, [], 'Server is on fire'));
            $report = $dispatcher->process();
            $this->assertSame(1, $report->getRetried(), 'attempt ' . $attempt);

            $row = $this->getDelivery($id);
            $this->assertSame(DeliveryQueue::STATUS_PENDING, (int)$row->status);
            $this->assertSame($attempt, (int)$row->attempts);
            $this->assertSame(500, (int)$row->response_code);
            $this->assertSame('HTTP 500: Server is on fire', $row->last_error);
            $delay = \Galette\Webhooks\Dispatcher::BACKOFF[$attempt];
            $this->assertSame(
                $this->clock->now()->modify('+' . $delay . ' seconds')->format('Y-m-d H:i:s'),
                $row->next_attempt_at
            );

            //not due yet
            $this->assertSame(0, $dispatcher->process()->getTotal());
            $this->wait($delay);
        }

        $this->mock->append(new Response(503));
        $this->assertSame(1, $dispatcher->process()->getFailed());
        $row = $this->getDelivery($id);
        $this->assertSame(DeliveryQueue::STATUS_FAILED, (int)$row->status);
        $this->assertSame(\Galette\Webhooks\Dispatcher::MAX_ATTEMPTS, (int)$row->attempts);
        $this->assertNull($row->next_attempt_at);
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            sprintf('Webhook delivery #%1$d (group.created) failed, attempt 6/6: HTTP 503: ', $id)
        );
    }

    /**
     * Test network errors are retried
     */
    public function testNetworkError(): void
    {
        [, $id] = $this->queue();
        $this->mock->append(
            new ConnectException('Connection refused', new Request('POST', 'https://example.com/hook'))
        );

        $this->assertSame(1, $this->getDispatcher()->process()->getRetried());
        $row = $this->getDelivery($id);
        $this->assertSame(DeliveryQueue::STATUS_PENDING, (int)$row->status);
        $this->assertNull($row->response_code);
        $this->assertSame('Connection refused', $row->last_error);
    }

    /**
     * Test a 410 answer disables webhook
     */
    public function testGone(): void
    {
        [$webhook, $id] = $this->queue();
        $this->mock->append(new Response(410));

        $this->assertSame(1, $this->getDispatcher()->process()->getFailed());
        $this->assertSame(DeliveryQueue::STATUS_FAILED, (int)$this->getDelivery($id)->status);
        $this->assertFalse((new Webhook($webhook->getId()))->isActive());
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Webhook "receiver" has been disabled, its receiver answered 410 Gone.'
        );
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            sprintf('Webhook delivery #%1$d (group.created) failed, attempt 1/6: HTTP 410: ', $id)
        );
    }

    /**
     * Test deliveries of inactive webhooks wait
     */
    public function testInactive(): void
    {
        [$webhook, $id] = $this->queue(active: false);
        $this->assertSame(0, $this->getDispatcher()->process()->getTotal());
        $this->assertSame(DeliveryQueue::STATUS_PENDING, (int)$this->getDelivery($id)->status);

        //sent once webhook is enabled again
        $webhook->setActive(active: true)->save();
        $this->mock->append(new Response(200));
        $this->assertSame(1, $this->getDispatcher()->process()->getDelivered());
    }

    /**
     * Test claims of a worker that died are released
     */
    public function testStaleClaim(): void
    {
        [, $id] = $this->queue();
        $this->setDelivery($id, [
            'status' => DeliveryQueue::STATUS_SENDING,
            'claimed_at' => $this->clock->now()->modify('-5 minutes')->format('Y-m-d H:i:s'),
        ]);

        //claim is still honoured
        $this->assertSame(0, $this->getDispatcher()->process()->getTotal());

        $this->wait(6 * 60);
        $this->mock->append(new Response(200));
        $this->assertSame(1, $this->getDispatcher()->process()->getDelivered());
    }

    /**
     * Test old deliveries are purged
     */
    public function testPurge(): void
    {
        [, $old] = $this->queue();
        [, $recent] = $this->queue();
        [, $pending] = $this->queue();
        $this->setDelivery($old, [
            'status' => DeliveryQueue::STATUS_DELIVERED,
            'creation_date' => $this->clock->now()->modify('-31 days')->format('Y-m-d H:i:s'),
        ]);
        $this->setDelivery($recent, [
            'status' => DeliveryQueue::STATUS_FAILED,
            'creation_date' => $this->clock->now()->modify('-29 days')->format('Y-m-d H:i:s'),
        ]);
        //pending deliveries are never purged
        $this->setDelivery($pending, [
            'next_attempt_at' => $this->clock->now()->modify('+1 day')->format('Y-m-d H:i:s'),
            'creation_date' => $this->clock->now()->modify('-31 days')->format('Y-m-d H:i:s'),
        ]);

        $this->assertSame(0, $this->getDispatcher()->process()->getTotal());
        $select = $this->zdb->select(DeliveryQueue::TABLE);
        $select->columns([DeliveryQueue::PK])->order(DeliveryQueue::PK);
        $ids = [];
        foreach ($this->zdb->execute($select) as $row) {
            $ids[] = (int)$row->{DeliveryQueue::PK};
        }
        $this->assertSame([$recent, $pending], $ids);
    }

    /**
     * Test sending a delivery right away
     */
    public function testSendNow(): void
    {
        [, $id] = $this->queue();
        $this->setDelivery($id, [
            'status' => DeliveryQueue::STATUS_FAILED,
            'attempts' => 6,
            'next_attempt_at' => null,
        ]);

        $this->mock->append(new Response(200));
        $result = $this->getDispatcher()->sendNow($id);
        $this->assertNotNull($result);
        $this->assertTrue($result->isSuccess());
        $this->assertSame(200, $result->status_code);
        $this->assertSame(7, (int)$this->getDelivery($id)->attempts);

        //a delivery being sent by another worker is left alone
        $this->setDelivery($id, ['status' => DeliveryQueue::STATUS_SENDING]);
        $this->assertNull($this->getDispatcher()->sendNow($id));
    }

    /**
     * Test deliveries summaries
     */
    public function testSummaries(): void
    {
        [$webhook, $delivered] = $this->queue();
        $queue = new DeliveryQueue($this->zdb);
        $envelope = new Envelope(WebhookEvent::GroupCreated, ['id' => 2]);
        $failed = $queue->enqueue($webhook, $envelope, 'msg_failed');
        $pending = $queue->enqueue($webhook, $envelope, 'msg_pending');
        $this->setDelivery($delivered, [
            'status' => DeliveryQueue::STATUS_DELIVERED,
            'delivered_at' => '2026-10-04 12:00:00',
            'response_code' => 200,
        ]);
        $this->setDelivery($failed, ['status' => DeliveryQueue::STATUS_FAILED, 'response_code' => 500]);

        $summaries = $queue->getSummaries();
        $this->assertSame([$webhook->getId()], array_keys($summaries));
        $summary = $summaries[$webhook->getId()];
        $this->assertSame(1, $summary['pending']);
        $this->assertSame(1, $summary['failed']);
        //last one is the pending delivery
        $this->assertSame(DeliveryQueue::STATUS_PENDING, $summary['last_status']);
        $this->assertNull($summary['last_code']);
        $this->assertNotNull($summary['last_date']);
        $this->assertGreaterThan($failed, $pending);
    }
}
