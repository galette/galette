<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette contributions list
 *
 * PHP version 5
 *
 * Copyright © 2020 The Galette Team
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
 * @category  UI
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-12-10
 */

namespace Galette\UI;

/**
 * Galette contributions list
 *
 * @name      Lists
 * @category  UI
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

class COntributionList extends AbstractList
{
    /**
     * Constructor
     */
    public function __construct()
    {
        return $this
            ->withSearch()
            ->withPagination()
            ->withLegend()
            ->withFooter()
        ;
    }

    /**
     * Register search parameters
     *
     * @return array
     */
    protected function registerSearchParameters(): array
    {
        //TODO
    }

    /**
     * Register actions
     *
     * @return array
     */
    protected function registerActions(): array
    {
        return $this->getDefaultActions() + ['contrib_pdf'];
    }

    /**
     * Generate list headers
     *
     * @return array
     */
    protected function generateHeaders(): array
    {
        //TODO
    }

    /**
     * Generate list rows
     *
     * @return array
     */
    protected function generateRows(): array
    {
        //TODO
    }
}
