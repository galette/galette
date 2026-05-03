<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Plugins;

use Galette\Entity\Adherent;

/**
 * Action provider interface
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
interface MemberActionProviderInterface
{
    /**
     * Get member actions
     *
     * @param Adherent $member Current member
     *
     * @return array<int, string|array<string,mixed>>
     */
    public function getListActions(Adherent $member): array;

    /**
     * Get detailed member actions
     *
     * @param Adherent $member Current member
     *
     * @return array<int, string|array<string,mixed>>
     */
    public function getDetailedActions(Adherent $member): array;

    /**
     * Get member batch actions
     *
     * @return array<int, string|array<string,mixed>>
     */
    public function getBatchActions(): array;
}
