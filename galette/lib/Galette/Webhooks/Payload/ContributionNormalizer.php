<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Webhooks\Payload;

use Galette\Core\Authentication;
use Galette\Entity\Contribution;
use InvalidArgumentException;

/**
 * Contributions normalizer
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ContributionNormalizer implements Normalizer
{
    /**
     * Constructor
     *
     * @param MemberNormalizer $members Members normalizer, for the contribution member
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
        return $subject instanceof Contribution;
    }

    /**
     * Normalize contribution
     *
     * @param object $subject      Contribution
     * @param int    $access_level Webhook access level
     *
     * @return array<string, mixed>
     */
    public function normalize(object $subject, int $access_level): array
    {
        if (!$subject instanceof Contribution) {
            throw new InvalidArgumentException('Unsupported subject ' . $subject::class);
        }

        $member_id = $subject->member;
        $type = $subject->type;
        $data = [
            'id' => $subject->id,
            'kind' => $subject->getRawType(),
            'type' => $type !== null ? ['id' => (int)$type->id, 'label' => (string)$type->libelle] : null,
            'amount' => $subject->amount,
            'payment_type' => ['id' => $subject->getPaymentTypeId(), 'label' => $subject->getPaymentType()],
            'date' => $subject->getDate('date', translated: false),
            'begin_date' => $subject->getDate('begin_date', translated: false),
            'end_date' => $subject->getDate('end_date', translated: false),
            'transaction_id' => $subject->transaction?->id,
            'member' => $member_id !== null ? $this->members->summarize((int)$member_id, $access_level) : null,
        ];
        if ($access_level >= Authentication::ACCESS_STAFF) {
            $data['info'] = $subject->info;
        }
        return $data;
    }
}
