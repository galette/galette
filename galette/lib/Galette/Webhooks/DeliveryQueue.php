<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Webhooks;

use Galette\Core\Db;
use Galette\Entity\Webhook;
use Galette\Webhooks\Payload\Envelope;
use Laminas\Db\Sql\Expression;

use function Safe\json_encode;

/**
 * Webhooks deliveries queue
 *
 * Rows are written by events listeners and sent later by the dispatcher,
 * so that a slow or unreachable receiver never delays nor breaks the change
 * that triggered the event.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DeliveryQueue
{
    public const string TABLE = 'webhooks_deliveries';
    public const string PK = 'id_delivery';

    public const int STATUS_PENDING = 0;
    public const int STATUS_DELIVERED = 1;
    public const int STATUS_FAILED = 2;
    /** Row claimed by a worker, delivery in progress */
    public const int STATUS_SENDING = 3;

    /**
     * Constructor
     *
     * @param Db $zdb Database instance
     */
    public function __construct(private readonly Db $zdb)
    {
    }

    /**
     * Generate a message identifier
     */
    public static function generateMessageId(): string
    {
        return 'msg_' . bin2hex(random_bytes(16));
    }

    /**
     * Queue a delivery
     *
     * No transaction is opened here: when the event is sent from within one,
     * the delivery is rolled back along with the change that triggered it.
     *
     * @param Webhook  $webhook    Target webhook
     * @param Envelope $envelope   Request body
     * @param string   $message_id Message identifier, shared by all webhooks receiving the same event
     *
     * @return int Delivery identifier
     */
    public function enqueue(Webhook $webhook, Envelope $envelope, string $message_id): int
    {
        $now = date('Y-m-d H:i:s');
        $insert = $this->zdb->insert(self::TABLE);
        $insert->values([
            Webhook::PK => $webhook->getId(),
            'message_id' => $message_id,
            'event_type' => $envelope->type->value,
            'payload' => json_encode($envelope),
            'status' => self::STATUS_PENDING,
            'attempts' => 0,
            'next_attempt_at' => $now,
            'creation_date' => $now,
        ]);
        $this->zdb->execute($insert);
        return $this->zdb->getLastGeneratedValue($this);
    }

    /**
     * Get deliveries summary for each webhook
     *
     * @return array<int, array{pending: int, failed: int, last_status: ?int, last_date: ?string, last_code: ?int}>
     *         Webhook identifier as key
     */
    public function getSummaries(): array
    {
        $summaries = [];
        $empty = ['pending' => 0, 'failed' => 0, 'last_status' => null, 'last_date' => null, 'last_code' => null];

        $select = $this->zdb->select(self::TABLE);
        $select
            ->columns([Webhook::PK, 'status', 'count' => new Expression('COUNT(*)')])
            ->group([Webhook::PK, 'status']);
        $select->where->in('status', [self::STATUS_PENDING, self::STATUS_SENDING, self::STATUS_FAILED]);
        foreach ($this->zdb->execute($select) as $row) {
            $id = (int)$row->{Webhook::PK};
            $summaries[$id] ??= $empty;
            $key = (int)$row->status === self::STATUS_FAILED ? 'failed' : 'pending';
            $summaries[$id][$key] += (int)$row->count;
        }

        $last = $this->zdb->select(self::TABLE);
        $last
            ->columns(['last' => new Expression('MAX(' . self::PK . ')')])
            ->group(Webhook::PK);
        $select = $this->zdb->select(self::TABLE);
        $select->where->in(self::PK, $last);
        foreach ($this->zdb->execute($select) as $row) {
            $id = (int)$row->{Webhook::PK};
            $summaries[$id] ??= $empty;
            $summaries[$id]['last_status'] = (int)$row->status;
            $summaries[$id]['last_date'] = (string)($row->delivered_at ?? $row->creation_date);
            $summaries[$id]['last_code'] = $row->response_code !== null ? (int)$row->response_code : null;
        }

        return $summaries;
    }

    /**
     * Get latest deliveries of a webhook
     *
     * @param int $webhook_id Webhook identifier
     * @param int $limit      Maximum number of deliveries
     *
     * @return list<\ArrayObject<string, mixed>>
     */
    public function getForWebhook(int $webhook_id, int $limit = 100): array
    {
        $select = $this->zdb->select(self::TABLE);
        $select
            ->where([Webhook::PK => $webhook_id])
            ->order(self::PK . ' DESC')
            ->limit($limit);
        $deliveries = [];
        foreach ($this->zdb->execute($select) as $row) {
            $deliveries[] = $row;
        }
        return $deliveries;
    }

    /**
     * Get webhook a delivery belongs to
     *
     * @param int $id Delivery identifier
     */
    public function getWebhookId(int $id): ?int
    {
        $select = $this->zdb->select(self::TABLE);
        $select->columns([Webhook::PK])->where([self::PK => $id])->limit(1);
        $row = $this->zdb->execute($select)->current();
        return $row !== null ? (int)$row->{Webhook::PK} : null;
    }
}
