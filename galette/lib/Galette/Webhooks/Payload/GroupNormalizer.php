<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Webhooks\Payload;

use Galette\Entity\Group;
use InvalidArgumentException;

/**
 * Groups normalizer
 *
 * Members and managers are not sent: a group event does not tell they changed.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class GroupNormalizer implements Normalizer
{
    /**
     * Can this normalizer handle given object?
     *
     * @param object $subject Object carried by the event
     */
    public function supports(object $subject): bool
    {
        return $subject instanceof Group;
    }

    /**
     * Normalize group
     *
     * @param object $subject      Group
     * @param int    $access_level Webhook access level
     *
     * @return array<string, mixed>
     */
    public function normalize(object $subject, int $access_level): array
    {
        if (!$subject instanceof Group) {
            throw new InvalidArgumentException('Unsupported subject ' . $subject::class);
        }

        return [
            'id' => $subject->getId(),
            'name' => $subject->getName(),
            'parent_id' => $subject->getParentGroup()?->getId(),
            'creation_date' => $subject->getCreationDate(formatted: false),
        ];
    }
}
