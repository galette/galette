<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Webhooks;

/**
 * Outcome of a delivery attempt
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final readonly class DeliveryResult
{
    /**
     * Constructor
     *
     * @param int     $id          Delivery identifier
     * @param int     $status      Delivery status after the attempt, one of DeliveryQueue::STATUS_* constants
     * @param ?int    $status_code HTTP status code, if a response has been received
     * @param ?string $error       Error message
     */
    public function __construct(
        public int $id,
        public int $status,
        public ?int $status_code,
        public ?string $error
    ) {
    }

    /**
     * Has delivery succeeded?
     */
    public function isSuccess(): bool
    {
        return $this->status === DeliveryQueue::STATUS_DELIVERED;
    }
}
