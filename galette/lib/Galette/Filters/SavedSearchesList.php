<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Saved searches list filters and paginator
 *
 * PHP version 5
 *
 * Copyright © 2019-2023 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Filters
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2019-09-21
 */

namespace Galette\Filters;

use Analog\Analog;
use Galette\Core\Pagination;
use Galette\Entity\Group;
use Galette\Repository\Members;

/**
 * Saved searches list filters and paginator
 *
 * @name      SavedSearchesList
 * @category  Filters
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

class SavedSearchesList extends Pagination
{
    public const ORDERBY_DATE = 0;

    /**
     * Returns the field we want to default set order to
     *
     * @return int|string
     */
    protected function getDefaultOrder(): int|string
    {
        return 'creation_date';
    }

    /**
     * Return the default direction for ordering
     *
     * @return string ASC or DESC
     */
    protected function getDefaultDirection(): string
    {
        return self::ORDER_DESC;
    }
}
