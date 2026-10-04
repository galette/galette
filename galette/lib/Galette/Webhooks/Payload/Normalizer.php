<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Webhooks\Payload;

/**
 * Turns the object carried by an internal event into webhook data
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
interface Normalizer
{
    /**
     * Can this normalizer handle given object?
     *
     * @param object $subject Object carried by the event
     */
    public function supports(object $subject): bool;

    /**
     * Normalize object
     *
     * @param object $subject      Object carried by the event
     * @param int    $access_level Webhook access level, one of Authentication::ACCESS_* constants
     *
     * @return array<string, mixed>
     */
    public function normalize(object $subject, int $access_level): array;
}
