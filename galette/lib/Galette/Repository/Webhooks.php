<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Repository;

use Galette\Core\Db;
use Galette\Entity\Webhook;
use Galette\Webhooks\WebhookEvent;

/**
 * Webhooks repository
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Webhooks
{
    /**
     * Default constructor
     *
     * @param Db $zdb Database instance
     */
    public function __construct(private readonly Db $zdb)
    {
    }

    /**
     * Get all webhooks
     *
     * @return array<int, Webhook> Identifier as key
     */
    public function getList(): array
    {
        $select = $this->zdb->select(Webhook::TABLE);
        $select->order('name');
        return $this->hydrate($this->zdb->execute($select));
    }

    /**
     * Get active webhooks
     *
     * @return array<int, Webhook> Identifier as key
     */
    public function getActive(): array
    {
        $select = $this->zdb->select(Webhook::TABLE);
        $select
            ->where(['active' => $this->zdb->isPostgres() ? 'true' : 1])
            ->order(Webhook::PK);
        return $this->hydrate($this->zdb->execute($select));
    }

    /**
     * Get active webhooks subscribed to given event
     *
     * @param WebhookEvent $event Event
     *
     * @return array<int, Webhook> Identifier as key
     */
    public function getActiveFor(WebhookEvent $event): array
    {
        return array_filter(
            $this->getActive(),
            static fn(Webhook $webhook): bool => $webhook->subscribesTo($event)
        );
    }

    /**
     * Build entities from results
     *
     * @param iterable<\ArrayObject<string, int|string>> $results Database results
     *
     * @return array<int, Webhook>
     */
    private function hydrate(iterable $results): array
    {
        $webhooks = [];
        foreach ($results as $row) {
            $webhook = new Webhook($row);
            $webhooks[$webhook->getId()] = $webhook;
        }
        return $webhooks;
    }
}
