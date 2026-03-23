<?php

/**
 * Copyright © 2003-2026 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Galette\Core\Installation;

/**
 * Installation step status
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
enum StepStatus: string
{
    case SUCCESS = 'success';
    case ERROR = 'error';
    case WARNING = 'warning';
    case INFO = 'info';

    /**
     * Check if status is successful
     */
    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    /**
     * Check if status is an error
     */
    public function isError(): bool
    {
        return $this === self::ERROR;
    }

    /**
     * Check if status is a warning
     */
    public function isWarning(): bool
    {
        return $this === self::WARNING;
    }

    /**
     * Get CSS class for Semantic UI
     */
    public function getCssClass(): string
    {
        return match ($this) {
            self::SUCCESS => 'green',
            self::ERROR => 'red',
            self::WARNING => 'orange',
            self::INFO => 'blue'
        };
    }

    /**
     * Get icon name for Semantic UI
     */
    public function getIconName(): string
    {
        return match ($this) {
            self::SUCCESS => 'check',
            self::ERROR => 'times',
            self::WARNING => 'exclamation triangle',
            self::INFO => 'info circle'
        };
    }
}
