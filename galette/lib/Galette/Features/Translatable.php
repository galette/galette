<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Features;

/**
 * Translatable objects trait
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

trait Translatable
{
    protected ?string $old_name = null;
    protected ?string $name = null;

    /**
     * Get field name
     *
     * @param bool $translated Get translated or raw name
     */
    public function getName(bool $translated = true): string
    {
        if (empty($this->name)) {
            return '';
        } elseif ($translated === true) {
            return _T(strip_tags($this->name));
        } else {
            return strip_tags($this->name);
        }
    }
}
