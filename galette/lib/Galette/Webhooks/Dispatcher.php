<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Webhooks;

use Analog\Analog;
use ArrayObject;
use Galette\Core\Db;
use Galette\Entity\Webhook;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Laminas\Db\Sql\Expression;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * Sends queued webhooks deliveries
 *
 * Several workers may run at once (cron, console, end of request): each row
 * is claimed with an update that only matches a pending row, as the mailing
 * queue does, so that a delivery is never sent twice concurrently.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Dispatcher
{
    public const int MAX_ATTEMPTS = 6;
    /** Delay before next attempt, in seconds, indexed by failed attempts count */
    public const array BACKOFF = [1 => 60, 2 => 300, 3 => 1800, 4 => 7200, 5 => 28800];
    /** How long a claim is honoured, in minutes */
    public const int CLAIM_TIMEOUT = 10;
    /** How long delivered and failed rows are kept, in days */
    public const int RETENTION_DAYS = 30;
    public const int BATCH_SIZE = 50;
    /** Stored response or error excerpt length */
    public const int EXCERPT_LENGTH = 1024;

    public const float TIMEOUT = 5.0;
    public const float CONNECT_TIMEOUT = 2.0;

    private readonly ClientInterface $client;

    /**
     * Constructor
     *
     * @param Db               $zdb    Database instance
     * @param ClockInterface   $clock  Clock
     * @param ?ClientInterface $client HTTP client, mainly for tests
     */
    public function __construct(
        private readonly Db $zdb,
        private readonly ClockInterface $clock,
        ?ClientInterface $client = null
    ) {
        $this->client = $client ?? new Client([
            RequestOptions::TIMEOUT => self::TIMEOUT,
            RequestOptions::CONNECT_TIMEOUT => self::CONNECT_TIMEOUT,
            'curl' => [CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS],
        ]);
    }

    /**
     * Send due deliveries
     *
     * @param int $limit Maximum number of deliveries to send
     */
    public function process(int $limit = self::BATCH_SIZE): DispatchReport
    {
        $this->releaseStaleClaims();

        $select = $this->zdb->select(DeliveryQueue::TABLE, 'd');
        $select
            ->columns([DeliveryQueue::PK])
            ->join(
                ['w' => PREFIX_DB . Webhook::TABLE],
                'd.' . Webhook::PK . ' = w.' . Webhook::PK,
                []
            )
            ->where([
                'd.status' => DeliveryQueue::STATUS_PENDING,
                'w.active' => $this->zdb->isPostgres() ? 'true' : 1,
            ])
            ->order(['d.next_attempt_at', 'd.' . DeliveryQueue::PK])
            ->limit($limit);
        $select->where->lessThanOrEqualTo('d.next_attempt_at', $this->now());

        $report = new DispatchReport();
        foreach ($this->zdb->execute($select) as $row) {
            $result = $this->deliver((int)$row->{DeliveryQueue::PK});
            if ($result !== null) {
                $report->add($result);
            }
        }

        $this->purge();
        return $report;
    }

    /**
     * Send given deliveries, if they are still pending
     *
     * @param array<int> $ids Deliveries identifiers
     */
    public function processIds(array $ids): DispatchReport
    {
        $report = new DispatchReport();
        foreach ($ids as $id) {
            $result = $this->deliver($id);
            if ($result !== null) {
                $report->add($result);
            }
        }
        return $report;
    }

    /**
     * Send a single delivery right away, whatever its status and schedule
     *
     * Used to retry a delivery or to send a test event from the webhook page.
     *
     * @param int $id Delivery identifier
     */
    public function sendNow(int $id): ?DeliveryResult
    {
        $update = $this->zdb->update(DeliveryQueue::TABLE);
        $update
            ->set([
                'status' => DeliveryQueue::STATUS_PENDING,
                'claimed_at' => null,
                'next_attempt_at' => $this->now(),
            ])
            ->where->equalTo(DeliveryQueue::PK, $id)
            ->notEqualTo('status', DeliveryQueue::STATUS_SENDING);
        $this->zdb->execute($update);
        return $this->deliver($id);
    }

    /**
     * Claim and send a delivery
     *
     * @param int $id Delivery identifier
     *
     * @return ?DeliveryResult Null if another worker took it
     */
    private function deliver(int $id): ?DeliveryResult
    {
        if (!$this->claim($id)) {
            return null;
        }

        $row = $this->load($id);
        if ($row === null) {
            return null;
        }

        try {
            $webhook = new Webhook((int)$row->{Webhook::PK});
        } catch (Throwable $e) {
            return $this->markFailed(
                row: $row,
                code: null,
                error: 'Webhook cannot be loaded: ' . $e->getMessage(),
                final: true
            );
        }

        $payload = (string)$row->payload;
        $timestamp = $this->clock->now()->getTimestamp();
        try {
            $headers = (new Signer($webhook->getSecret()))->getHeaders((string)$row->message_id, $timestamp, $payload);
            $response = $this->client->request('POST', $webhook->getUrl(), [
                RequestOptions::HEADERS => $headers + [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Galette/' . ltrim(GALETTE_VERSION, 'v'),
                ],
                RequestOptions::BODY => $payload,
                //a redirection could lead anywhere, including to non http targets
                RequestOptions::ALLOW_REDIRECTS => false,
                //error statuses are handled below, they are not exceptional
                RequestOptions::HTTP_ERRORS => false,
            ]);
        } catch (Throwable $e) {
            //network errors, timeouts, as well as an invalid stored secret
            return $this->markFailed($row, code: null, error: $e->getMessage());
        }

        $code = $response->getStatusCode();
        if ($code >= 200 && $code < 300) {
            return $this->markDelivered($row, $code);
        }

        //only an excerpt of the answer is kept, no need to read it all
        $error = sprintf('HTTP %1$d: %2$s', $code, $response->getBody()->read(self::EXCERPT_LENGTH));
        if ($code === 410) {
            //receiver asks not to be called anymore, as Standard Webhooks recommends
            $webhook->setActive(active: false)->save();
            Analog::log(
                sprintf('Webhook "%1$s" has been disabled, its receiver answered 410 Gone.', $webhook->getName()),
                Analog::WARNING
            );
            return $this->markFailed(row: $row, code: $code, error: $error, final: true);
        }
        return $this->markFailed($row, $code, $error);
    }

    /**
     * Take ownership of a pending delivery
     *
     * @param int $id Delivery identifier
     *
     * @return bool Whether the delivery now belongs to this worker
     */
    private function claim(int $id): bool
    {
        $update = $this->zdb->update(DeliveryQueue::TABLE);
        $update
            ->set(['status' => DeliveryQueue::STATUS_SENDING, 'claimed_at' => $this->now()])
            ->where([DeliveryQueue::PK => $id, 'status' => DeliveryQueue::STATUS_PENDING]);
        return $this->zdb->execute($update)->count() === 1;
    }

    /**
     * Load a delivery row
     *
     * @param int $id Delivery identifier
     *
     * @return ?ArrayObject<string, mixed>
     */
    private function load(int $id): ?ArrayObject
    {
        $select = $this->zdb->select(DeliveryQueue::TABLE);
        $select->where([DeliveryQueue::PK => $id])->limit(1);
        $row = $this->zdb->execute($select)->current();
        return $row instanceof ArrayObject ? $row : null;
    }

    /**
     * Record a successful delivery
     *
     * @param ArrayObject<string, mixed> $row  Delivery row
     * @param int                        $code HTTP status code
     */
    private function markDelivered(ArrayObject $row, int $code): DeliveryResult
    {
        $update = $this->zdb->update(DeliveryQueue::TABLE);
        $update
            ->set([
                'status' => DeliveryQueue::STATUS_DELIVERED,
                'attempts' => new Expression('attempts + 1'),
                'claimed_at' => null,
                'next_attempt_at' => null,
                'delivered_at' => $this->now(),
                'response_code' => $code,
                'last_error' => null,
            ])
            ->where([DeliveryQueue::PK => (int)$row->{DeliveryQueue::PK}]);
        $this->zdb->execute($update);
        return new DeliveryResult(
            id: (int)$row->{DeliveryQueue::PK},
            status: DeliveryQueue::STATUS_DELIVERED,
            status_code: $code,
            error: null
        );
    }

    /**
     * Record a failed attempt, scheduling the next one if any
     *
     * @param ArrayObject<string, mixed> $row   Delivery row
     * @param ?int                       $code  HTTP status code, if a response was received
     * @param string                     $error Error message
     * @param bool                       $final Do not retry
     */
    private function markFailed(ArrayObject $row, ?int $code, string $error, bool $final = false): DeliveryResult
    {
        $attempts = (int)$row->attempts + 1;
        $final = $final || $attempts >= self::MAX_ATTEMPTS;
        $status = $final ? DeliveryQueue::STATUS_FAILED : DeliveryQueue::STATUS_PENDING;
        $error = mb_substr($error, 0, self::EXCERPT_LENGTH);

        $update = $this->zdb->update(DeliveryQueue::TABLE);
        $update
            ->set([
                'status' => $status,
                'attempts' => $attempts,
                'claimed_at' => null,
                'next_attempt_at' => $final ? null : $this->now(self::BACKOFF[$attempts]),
                'response_code' => $code,
                'last_error' => $error,
            ])
            ->where([DeliveryQueue::PK => (int)$row->{DeliveryQueue::PK}]);
        $this->zdb->execute($update);

        Analog::log(
            sprintf(
                'Webhook delivery #%1$d (%2$s) failed, attempt %3$d/%4$d: %5$s',
                (int)$row->{DeliveryQueue::PK},
                (string)$row->event_type,
                $attempts,
                self::MAX_ATTEMPTS,
                $error
            ),
            $final ? Analog::WARNING : Analog::INFO
        );
        return new DeliveryResult(
            id: (int)$row->{DeliveryQueue::PK},
            status: $status,
            status_code: $code,
            error: $error
        );
    }

    /**
     * Hand back deliveries of a worker that never came back
     *
     * Such a delivery may or may not have reached its receiver; sending it
     * again is acceptable since receivers deduplicate on the message identifier.
     */
    private function releaseStaleClaims(): void
    {
        $update = $this->zdb->update(DeliveryQueue::TABLE);
        $update->set(['status' => DeliveryQueue::STATUS_PENDING, 'claimed_at' => null]);
        $update->where
            ->equalTo('status', DeliveryQueue::STATUS_SENDING)
            ->lessThan('claimed_at', $this->now(-self::CLAIM_TIMEOUT * 60));
        $this->zdb->execute($update);
    }

    /**
     * Remove old delivered and failed deliveries
     */
    private function purge(): void
    {
        try {
            $delete = $this->zdb->delete(DeliveryQueue::TABLE);
            $delete->where
                ->in('status', [DeliveryQueue::STATUS_DELIVERED, DeliveryQueue::STATUS_FAILED])
                ->lessThan('creation_date', $this->now(-self::RETENTION_DAYS * 86400));
            $this->zdb->execute($delete);
        } catch (Throwable $e) {
            //housekeeping must never get in the way of a sending
            Analog::log('Unable to purge webhooks deliveries | ' . $e->getMessage(), Analog::WARNING);
        }
    }

    /**
     * Get database formatted date
     *
     * @param int $offset Offset from now, in seconds
     */
    private function now(int $offset = 0): string
    {
        return $this->clock->now()->modify(sprintf('%+d seconds', $offset))->format('Y-m-d H:i:s');
    }
}
