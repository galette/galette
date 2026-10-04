<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Webhooks\Payload;

use Galette\Core\Authentication;
use Galette\Entity\Transaction;
use InvalidArgumentException;

/**
 * Transactions normalizer
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TransactionNormalizer implements Normalizer
{
    /**
     * Constructor
     *
     * @param MemberNormalizer $members Members normalizer, for the transaction member
     */
    public function __construct(private readonly MemberNormalizer $members)
    {
    }

    /**
     * Can this normalizer handle given object?
     *
     * @param object $subject Object carried by the event
     */
    public function supports(object $subject): bool
    {
        return $subject instanceof Transaction;
    }

    /**
     * Normalize transaction
     *
     * @param object $subject      Transaction
     * @param int    $access_level Webhook access level
     *
     * @return array<string, mixed>
     */
    public function normalize(object $subject, int $access_level): array
    {
        if (!$subject instanceof Transaction) {
            throw new InvalidArgumentException('Unsupported subject ' . $subject::class);
        }

        $member_id = $subject->member;
        $data = [
            'id' => $subject->id,
            'date' => $subject->getDate('date', translated: false),
            'amount' => $subject->amount,
            'payment_type' => $subject->payment_type !== null
                ? ['id' => (int)$subject->payment_type, 'label' => $subject->getPaymentType()]
                : null,
            'member' => $member_id !== null ? $this->members->summarize((int)$member_id, $access_level) : null,
        ];
        if ($access_level >= Authentication::ACCESS_STAFF) {
            $data['description'] = $subject->description;
        }
        return $data;
    }
}
