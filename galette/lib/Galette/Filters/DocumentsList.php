<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Filters;

use Galette\Core\Pagination;
use Galette\Enums\SQLOrder;

/**
 * Documents list filters and paginator
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class DocumentsList extends Pagination
{
    public const int ORDERBY_DATE = 0;
    public const int ORDERBY_TYPE = 1;
    public const int ORDERBY_NAME = 2;
    public const int ORDERBY_ID = 3;

    /**
     * Returns the field we want to default set order to
     */
    protected function getDefaultOrder(): int|string
    {
        return 'creation_date';
    }

    /**
     * Return the default direction for ordering
     */
    protected function getDefaultDirection(): SQLOrder
    {
        return SQLOrder::DESC;
    }
}
