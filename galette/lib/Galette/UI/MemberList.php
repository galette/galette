<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette members list
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
 * Galette members list
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

class MemberList extends AbstractList
{
    /** @var array */
    private $search_parameters = [];

    /** @var array */
    private $actions = [
        'edit',
        'contributions',
        'remove',
        'impersonate'
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        return $this
            ->withSearch()
            ->withMassive()
            ->withPagination()
            ->withLegend()
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
     * Register second line search parameters
     *
     * @return array
     */
    protected function registerSearchParametersSecondLine(): array
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
        //TODO
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
