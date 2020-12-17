<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Abstract Galette list for display
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
 * Abstract Galette list for display
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

abstract class AbstractList
{
    /** @var boolean */
    private $has_pagination = false;

    /** @var boolean */
    private $has_search = false;

    /** @var array */
    private $search_parameters = [];

    /** @var boolean */
    private $has_massive = false;

    /** @var boolean */
    private $has_legend = false;

    /** @var boolean */
    private $has_footer = false;

    /** @var array */
    private $known_actions = [
        'edit',
        'remove',
        'translate',
        'print',
    ];

    /** @var array */
    private $default_actions = [
        'edit',
        'remove',
        'translate'
    ];

    /** @var array */
    private $actions = [];

    /** @var array */
    private $headers = [];

    /** @var array */
    private $footers = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        return $this->withPagination();
    }

    /**
     * Enable simple search
     *
     * @return AbstractList
     */
    protected function withSearch()
    {
        $this->enable('search');
        return $this;
    }

    /**
     * Enable massive actions
     *
     * @return AbstractList
     */
    protected function withMassive()
    {
        $this->enable('massive');
        return $this;
    }

    /**
     * Enable legend
     *
     * @return AbstractList
     */
    protected function withLegend()
    {
        $this->enable('legend');
        return $this;
    }

    /**
     * Enable footer
     *
     * @return AbstractList
     */
    protected function withFooter()
    {
        $this->enable('search');
        return $this;
    }

    /**
     * Enable pagination
     *
     * @return AbstractList
     */
    protected function withPagination()
    {
        $this->enable('pagination');
        return $this;
    }

    /**
     * Enable feature
     *
     * @param string $name Feature name
     *
     * @return void
     */
    private function enable($name): void
    {
        $propname = 'has_' . $name;
        if (property_exists($this, $propname)) {
            $this->$propname = true;
        }
        throw new \RuntimeException(
            sprintf(
                'Property %1$s does not exists.',
                $name
            )
        );
    }

    /**
     * Does list supports pagination?
     *
     * @return boolean
     */
    public function hasPagination(): bool
    {
        return $this->has_pagination;
    }

    /**
     * Does list supports simple search?
     *
     * @return boolean
     */
    public function hasSearch(): bool
    {
        return $this->has_search;
    }

    /**
     * Does list supports massive actions?
     *
     * @return boolean
     */
    public function hasMassiveActions(): bool
    {
        return $this->has_massive;
    }

    /**
     * Get simple search parameters
     *
     * @return array
     */
    public function getSearchParameters(): array
    {
        $this->search_parameters = $this->registerSearchParameters();
        $this->search_parameters += $this->registerSearchParametersSecondLine();
        return $this->search_parameters;
    }

    /**
     * Register search parameters
     *
     * @return array
     */
    abstract protected function registerSearchParameters(): array;

    /**
     * Register second line search parameters
     *
     * @return array
     */
    protected function registerSearchParametersSecondLine(): array
    {
        return [];
    }

    /**
     * Get lists actions
     *
     * @return array
     */
    public function getActions(): array
    {
        $this->actions = $this->registerActions();
        if (!count($this->actions)) {
            $this->actions = $this->default_actions;
        }
        return $this->actions;
    }

    /**
     * Register actions
     *
     * @return array
     */
    abstract protected function registerActions(): array;

    /**
     * Get list headers for display
     *
     * @return array
     */
    public function getHeaders(): array
    {
        $this->headers = $this->generateHeaders();
        return $this->headers;
    }

    /**
     * Generate list headers
     *
     * @return array
     */
    abstract protected function generateHeaders(): array;

    /**
     * Get list rows for display
     *
     * @return array
     */
    public function getRows(): array
    {
        $this->rows = $this->generateRows();
        return $this->rows;
    }

    /**
     * Generate list rows
     *
     * @return array
     */
    abstract protected function generateRows(): array;

    /**
     * Does list have footer?
     *
     * @return boolean
     */
    public function hasFooter(): bool
    {
        return $this->has_footer;
    }

    /**
     * Get list footers for display
     *
     * @return array
     */
    public function getFooters(): array
    {
        $this->footers = $this->generateFooters();
        return $this->footers;
    }

    /**
     * Generate list footers
     *
     * @return array
     */
    protected function generateFooters(): array
    {
        //per default, many list does not have footer.
    }

    /**
     * Retrieve default actions
     *
     * @return array
     */
    public function getDefaultActions(): array
    {
        return $this->default_actions();
    }

    /**
     * Get filter path
     *
     * @return string
     */
    public function getFilterPath()
    {
        //TODO: build, or make abstract
        return 'filter-memberslist';
    }

    /**
     * Get batch path
     *
     * @return string
     */
    public function getBatchPath()
    {
        //TODO: build, or make abstract
        return 'batch-memberslist';
    }

    /**
     * Get count info line, localized
     *
     * @param interger $count Count
     *
     * @return string
     */
    public function getCountInfo($count)
    {
        //TODO: make abstract
        return str_replace(
            '%count',
            $count,
            _Tx('%count member', '%count members', $count)
        );
    }
}
