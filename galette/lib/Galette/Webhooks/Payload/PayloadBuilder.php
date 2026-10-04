<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Webhooks\Payload;

use Galette\Webhooks\WebhookEvent;
use InvalidArgumentException;

/**
 * Builds webhooks request bodies from internal events objects
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PayloadBuilder
{
    /** @var list<Normalizer> */
    private readonly array $normalizers;

    /**
     * Constructor
     *
     * @param MemberNormalizer       $members       Members normalizer
     * @param ContributionNormalizer $contributions Contributions normalizer
     * @param TransactionNormalizer  $transactions  Transactions normalizer
     * @param GroupNormalizer        $groups        Groups normalizer
     */
    public function __construct(
        MemberNormalizer $members,
        ContributionNormalizer $contributions,
        TransactionNormalizer $transactions,
        GroupNormalizer $groups
    ) {
        $this->normalizers = [$members, $contributions, $transactions, $groups];
    }

    /**
     * Build envelope for an event
     *
     * @param WebhookEvent $event        Public event
     * @param object       $subject      Object carried by the internal event
     * @param int          $access_level Webhook access level
     */
    public function build(WebhookEvent $event, object $subject, int $access_level): Envelope
    {
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->supports($subject)) {
                return new Envelope($event, $normalizer->normalize($subject, $access_level));
            }
        }
        throw new InvalidArgumentException(
            sprintf('No normalizer for %1$s on %2$s event', $subject::class, $event->value)
        );
    }

    /**
     * Build test event envelope
     */
    public function ping(): Envelope
    {
        return new Envelope(
            WebhookEvent::Ping,
            ['message' => 'This is a test event sent from Galette.']
        );
    }
}
