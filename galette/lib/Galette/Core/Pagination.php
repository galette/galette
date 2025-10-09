<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

namespace Galette\Core;

use Galette\Enums\SQLOrder;
use Slim\Routing\RouteParser;
use Analog\Analog;
use Laminas\Db\Sql\Select;
use Slim\Views\Twig;

/**
 * Pagination and ordering facilities
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property integer $current_page
 * @property string $orderby
 * @property SQLOrder $ordered
 * @property integer $show
 * @property integer $pages
 * @property integer $counter
 */

abstract class Pagination
{
    private int $current_page;
    private int|string $orderby;
    private SQLOrder $ordered;
    private int $show;
    private int $pages = 1;
    private ?int $counter = null;
    protected ?Twig $view;
    protected ?RouteParser $routeparser;
    /** @var array<string> */
    protected array $errors = [];

    /** @var array<string> */
    protected array $pagination_fields = [
        'current_page',
        'orderby',
        'ordered',
        'show',
        'pages',
        'counter'
    ];

    /**
     * Default constructor
     */
    public function __construct()
    {
        $this->reinit();
    }

    /**
     * Returns the field we want to default set order to
     *
     * @return int|string
     */
    abstract protected function getDefaultOrder(): int|string;

    /**
     * Return the default direction for ordering
     *
     * @return SQLOrder
     */
    protected function getDefaultDirection(): SQLOrder
    {
        return SQLOrder::ASC;
    }

    /**
     * Reinit default parameters
     *
     * @return void
     */
    public function reinit(): void
    {
        global $preferences;

        $this->current_page = 1;
        $this->orderby = $this->getDefaultOrder();
        $this->ordered = $this->getDefaultDirection();
        $this->show = $preferences->pref_numrows;
    }

    /**
     * Invert sort order
     *
     * @return void
     */
    public function invertorder(): void
    {
        $actual = $this->ordered;
        if ($actual === SQLOrder::ASC) {
            $this->ordered = SQLOrder::DESC;
        }
        if ($actual === SQLOrder::DESC) {
            $this->ordered = SQLOrder::ASC;
        }
    }

    /**
     * Get current sort direction
     *
     * @return string
     */
    public function getDirection(): string
    {
        return $this->ordered->value;
    }

    /**
     * Set sort direction
     *
     * @param SQLOrder|string|null $direction Order direction
     *
     * @return self
     */
    public function setDirection(SQLOrder|string|null $direction): self
    {
        if (($direction ?? $this->getDefaultDirection()) instanceof SQLOrder) {
            $this->ordered = $direction;
            return $this;
        }

        try {
            $odirection = SQLOrder::from($direction);
            $this->ordered = $odirection;
        } catch (\ValueError $e) {
            Analog::log(
                '[' . static::class
                . '|Pagination] ' . $e->getMessage(),
                Analog::WARNING
            );
        }

        return $this;
    }

    /**
     * Add limits so we retrieve only relavant rows
     *
     * @param Select $select Original select
     *
     * @return void
     */
    public function setLimits(Select $select): void
    {
        if ($this->show !== 0) {
            $select->limit($this->show);
            $select->offset(
                ($this->current_page - 1) * $this->show
            );
        }
    }

    /**
     * Set counter
     *
     * @param int $c Count
     *
     * @return void
     */
    public function setCounter(int $c): void
    {
        $this->counter = $c;
        $this->countPages();
    }

    /**
     * Update or set pages count
     *
     * @return void
     */
    protected function countPages(): void
    {
        if ($this->show !== 0) {
            if ($this->counter % $this->show == 0) {
                $this->pages = (int)($this->counter / $this->show);
            } else {
                $this->pages = (int)($this->counter / $this->show) + 1;
            }
        } else {
            $this->pages = 0;
        }
        if ($this->pages === 0) {
            $this->pages = 1;
        }
        if ($this->current_page > $this->pages) {
            $this->current_page = $this->pages;
        }
    }

    /**
     * Creates pagination links and assign some useful variables to the template
     *
     * @param RouteParser $routeparser Application instance
     * @param Twig        $view        View instance
     * @param boolean     $restricted  Do not permit displaying all
     *
     * @return void
     */
    public function setViewPagination(RouteParser $routeparser, Twig $view, bool $restricted = true): void
    {
        $is_paginated = true;
        $paginate = null;
        $this->view = $view;
        $this->routeparser = $routeparser;

        //Create pagination links
        $idepart = $this->current_page < 11 ? 1 : $this->current_page - 10;
        $ifin = $this->current_page + 10 < $this->pages ? $this->current_page + 10 : $this->pages;

        $next = $this->current_page + 1;
        $previous = $this->current_page - 1;

        if ($this->current_page != 1) {
            $paginate .= $this->getLink(
                '<i class="fast backward small icon" aria-hidden="true"></i>',
                $this->getHref(1),
                _T('First page')
            );

            $paginate .= $this->getLink(
                '<i class="step backward small icon" aria-hidden="true"></i>',
                $this->getHref($previous),
                sprintf(_T('Previous page (%1$s)'), (string)$previous)
            );
        }

        for ($i = $idepart; $i <= $ifin; $i++) {
            if ($i == $this->current_page) {
                $paginate .= $this->getLink(
                    "$i",
                    $this->getHref($this->current_page),
                    sprintf(_T('Current page (%1$s)'), (string)$this->current_page),
                    true
                );
            } else {
                $paginate .= $this->getLink(
                    (string)$i,
                    $this->getHref($i),
                    sprintf(_T('Page %1$s'), (string)$i)
                );
            }
        }
        if ($this->current_page != $this->pages) {
            $paginate .= $this->getLink(
                '<i class="step forward small icon" aria-hidden="true"></i>',
                $this->getHref($next),
                sprintf(_T('Next page (%1$s)'), (string)$next)
            );

            $paginate .= $this->getLink(
                '<i class="fast forward small icon" aria-hidden="true"></i>',
                $this->getHref($this->pages),
                sprintf(_T('Last page (%1$s)'), (string)$this->pages)
            );
        }
        if ($this->current_page == 1 && $this->current_page == $this->pages) {
            $is_paginated = false;
        }

        $options = [
            10 => "10",
            20 => "20",
            50 => "50",
            100 => "100"
        ];

        if ($restricted === false) {
            $options[0] = _T("All");
        }

        //Now, we assign common variables to template
        $view->getEnvironment()->addGlobal('nb_pages', $this->pages);
        $view->getEnvironment()->addGlobal('page', $this->current_page);
        $view->getEnvironment()->addGlobal('numrows', $this->show);
        $view->getEnvironment()->addGlobal('is_paginated', $is_paginated);
        $view->getEnvironment()->addGlobal('pagination', $paginate);
        $view->getEnvironment()->addGlobal('nbshow_options', $options);

        //resetting prevents following error:
        //PHP Fatal error:  Uncaught Exception: Serialization of '[...]' is not allowed in [no active file]:0
        $this->view = null;
        $this->routeparser = null;
    }

    /**
     * Get a pagination link
     *
     * @param string $content Links content
     * @param string $url     URL the link to point on
     * @param string $title   Link's title
     * @param bool   $current Is current page
     *
     * @return string
     */
    private function getLink(string $content, string $url, string $title, bool $current = false): string
    {
        $active = $current === true ? "active " : "";
        $link = "<a href=\"" . $url . "\" "
            . "title=\"" . $title . "\" class=\"" . $active . "item\">" . $content . "</a>\n";
        return $link;
    }

    /**
     * Build href
     *
     * @param int $page Page
     *
     * @return string
     */
    protected function getHref(int $page): string
    {
        $args = [
            'option'    => 'page',
            'value'     => (string)$page
        ];

        if ($this->view->getEnvironment()->getGlobals()['cur_subroute']) {
            $args['type'] = $this->view->getEnvironment()->getGlobals()['cur_subroute'];
        }

        $href = $this->routeparser->urlFor(
            $this->view->getEnvironment()->getGlobals()['cur_route'],
            $args
        );
        return $href;
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return mixed the called property
     */
    public function __get(string $name): mixed
    {
        if ($name === 'ordered') {
            Analog::log(
                '[' . static::class
                . '|Pagination] ' . $name . ' is deprecated, use getDirection() instead',
                Analog::WARNING
            );
            return $this->getDirection();
        }

        if (in_array($name, $this->pagination_fields)) {
            return $this->$name;
        }

        throw new \RuntimeException(
            sprintf(
                'Unable to get property "%s::%s"!',
                static::class,
                $name
            )
        );
    }

    /**
     * Global isset method
     * Required for twig to access properties via __get
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        if (in_array($name, $this->pagination_fields)) {
            return true;
        }
        return property_exists($this, $name);
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param mixed  $value a relevant value for the property
     *
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        switch ($name) {
            case 'ordered':
                Analog::log(
                    '[' . static::class
                    . '|Pagination] ' . $name . ' is deprecated, use setDirection() instead',
                    Analog::WARNING
                );
                $this->setDirection($value);
                break;
            case 'orderby':
                if ($this->$name == $value) {
                    $this->invertorder();
                } else {
                    $this->$name = $value;
                    $this->setDirection($this->getDefaultDirection());
                }
                break;
            case 'current_page':
            case 'counter':
            case 'pages':
                if (is_int($value) && $value > 0) {
                    $this->$name = $value;
                } else {
                    Analog::log(
                        '[' . static::class
                        . '|Pagination] Value for field `'
                        . $name . '` should be a positive integer - ('
                        . gettype($value) . ')' . $value . ' given',
                        Analog::WARNING
                    );
                }
                break;
            case 'show':
                if (
                    $value == 'all'
                    || preg_match('/[[:digit:]]/', (string)$value)
                    && $value >= 0
                ) {
                    $this->$name = (int)$value;
                } else {
                    Analog::log(
                        '[' . static::class . '|Pagination] Value for `'
                        . $name . '` should be a positive integer or \'all\' - ('
                        . gettype($value) . ')' . $value . ' given',
                        Analog::WARNING
                    );
                }
                break;
            default:
                Analog::log(
                    '[' . static::class
                    . '|Pagination] Unable to set property `' . $name . '`',
                    Analog::WARNING
                );
                break;
        }
    }
}
