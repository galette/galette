<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Webhooks;

/**
 * Summary of a dispatcher run
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class DispatchReport
{
    private int $delivered = 0;
    private int $retried = 0;
    private int $failed = 0;

    /**
     * Account for a delivery attempt
     *
     * @param DeliveryResult $result Attempt result
     */
    public function add(DeliveryResult $result): void
    {
        match ($result->status) {
            DeliveryQueue::STATUS_DELIVERED => ++$this->delivered,
            DeliveryQueue::STATUS_FAILED => ++$this->failed,
            default => ++$this->retried,
        };
    }

    /**
     * Number of successful deliveries
     */
    public function getDelivered(): int
    {
        return $this->delivered;
    }

    /**
     * Number of failed attempts that will be retried
     */
    public function getRetried(): int
    {
        return $this->retried;
    }

    /**
     * Number of deliveries given up on
     */
    public function getFailed(): int
    {
        return $this->failed;
    }

    /**
     * Total number of attempts
     */
    public function getTotal(): int
    {
        return $this->delivered + $this->retried + $this->failed;
    }
}
