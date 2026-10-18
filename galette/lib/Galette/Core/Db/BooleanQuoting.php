<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Db;

/**
 * Quote PHP booleans the same way on every engine
 *
 * Queries are not prepared: values are interpolated through PDO::quote(),
 * which casts false to an empty string. PostgreSQL boolean and MySQL strict
 * integer columns both refuse it, while '0' and '1' are accepted everywhere.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
trait BooleanQuoting
{
    /**
     * Quote value
     *
     * @param mixed $value Value to quote
     */
    public function quoteValue(mixed $value): string
    {
        return parent::quoteValue(is_bool($value) ? ($value ? '1' : '0') : $value);
    }

    /**
     * Quote trusted value
     *
     * @param mixed $value Value to quote
     */
    public function quoteTrustedValue(mixed $value): string
    {
        return parent::quoteTrustedValue(is_bool($value) ? ($value ? '1' : '0') : $value);
    }
}
