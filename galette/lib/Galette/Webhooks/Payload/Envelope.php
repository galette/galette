<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Webhooks\Payload;

use DateTimeInterface;
use Galette\Webhooks\WebhookEvent;
use JsonSerializable;
use Safe\DateTimeImmutable;

/**
 * Webhook request body: event type, timestamp and data, as the Standard
 * Webhooks specification recommends
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final readonly class Envelope implements JsonSerializable
{
    /**
     * Constructor
     *
     * @param WebhookEvent        $type      Event type
     * @param array<string,mixed> $data      Event data
     * @param DateTimeInterface   $timestamp When the event occurred
     */
    public function __construct(
        public WebhookEvent $type,
        public array $data,
        public DateTimeInterface $timestamp = new DateTimeImmutable()
    ) {
    }

    /**
     * Serialize
     *
     * @return array{type: string, timestamp: string, data: array<string,mixed>}
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type->value,
            'timestamp' => $this->timestamp->format(DateTimeInterface::RFC3339),
            'data' => $this->data,
        ];
    }
}
