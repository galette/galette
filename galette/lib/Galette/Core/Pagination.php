<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Global pagination
 *
 * PHP version 5
 *
 * Copyright © 2010-2014 The Galette Team
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
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2010-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2010-03-03
 */

namespace Galette\Core;

use Slim\Slim;
use Analog\Analog;
use Laminas\Db\Sql\Select;

/**
 * Pagination and ordering facilities
 *
 * @name      Pagination
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2010-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

abstract class Pagination
{
    private $current_page;
    private $orderby;
    private $ordered;
    private $show;
    private $pages = 1;
    private $counter = null;
    protected $view;
    protected $router;

    public const ORDER_ASC = 'ASC';
    public const ORDER_DESC = 'DESC';

    protected $pagination_fields = array(
        'current_page',
        'orderby',
        'ordered',
        'show',
        'pages',
        'counter'
    );

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
     * @return string field name
     */
    abstract protected function getDefaultOrder();

    /**
     * Return the default direction for ordering
     *
     * @return string ASC or DESC
     */
    protected function getDefaultDirection()
    {
        return self::ORDER_ASC;
    }

    /**
     * Reinit default parameters
     *
     * @return void
     */
    public function reinit()
    {
        global $preferences;

        $this->current_page = 1;
        $this->orderby = $this->getDefaultOrder();
        $this->ordered = $this->getDefaultDirection();
        $this->show = (int)$preferences->pref_numrows;
    }

    /**
     * Invert sort order
     *
     * @return void
     */
    public function invertorder()
    {
        $actual = $this->ordered;
        if ($actual == self::ORDER_ASC) {
                $this->ordered = self::ORDER_DESC;
        }
        if ($actual == self::ORDER_DESC) {
                $this->ordered = self::ORDER_ASC;
        }
    }

    /**
     * Get current sort direction
     *
     * @return self::ORDER_ASC|self::ORDER_DESC
     */
    public function getDirection()
    {
        return $this->ordered;
    }

    /**
     * Set sort direction
     *
     * @param string $direction self::ORDER_ASC|self::ORDER_DESC
     *
     * @return void
     */
    public function setDirection($direction)
    {
        if ($direction == self::ORDER_ASC || $direction == self::ORDER_DESC) {
            $this->ordered = $direction;
        } else {
            Analog::log(
                'Trying to set a sort direction that is not know (`' .
                $direction . '`). Reverting to default value.',
                Analog::WARNING
            );
            $this->ordered == self::ORDER_ASC;
        }
    }

    /**
     * Add limits so we retrieve only relavant rows
     *
     * @param Select $select Original select
     *
     * @return void
     */
    public function setLimits(Select $select)
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
    public function setCounter($c)
    {
        $this->counter = (int)$c;
        $this->countPages();
    }

    /**
     * Update or set pages count
     *
     * @return void
     */
    protected function countPages()
    {
        if ($this->show !== 0) {
            if ($this->counter % $this->show == 0) {
                $this->pages = intval($this->counter / $this->show);
            } else {
                $this->pages = intval($this->counter / $this->show) + 1;
            }
        } else {
            $this->pages = 0;
        }
        if ($this->pages == 0) {
            $this->pages = 1;
        }
        if ($this->current_page > $this->pages) {
            $this->current_page = $this->pages;
        }
    }

    /**
     * Creates pagination links and assign some usefull variables to the
     * Smarty template
     *
     * @param Router  $router     Application instance
     * @param Smarty  $view       View instance
     * @param boolean $restricted Do not permit to display all
     *
     * @return void
     */
    public function setSmartyPagination(\Slim\Router $router, \Smarty $view, $restricted = true)
    {
        $paginate = null;
        $this->view = $view;
        $this->router = $router;

        //Create pagination links
        if ($this->current_page < 11) {
            $idepart = 1;
        } else {
            $idepart = $this->current_page - 10;
        }
        if ($this->current_page + 10 < $this->pages) {
            $ifin = $this->current_page + 10;
        } else {
            $ifin = $this->pages;
        }

        $next = $this->current_page + 1;
        $previous = $this->current_page - 1;

        if ($this->current_page != 1) {
            $paginate .= $this->getLink(
                '&lt;&lt;',
                $this->getHref(1),
                preg_replace("(%i)", $next, _T("First page"))
            );

            $paginate .= $this->getLink(
                '&lt;',
                $this->getHref($previous),
                preg_replace("(%i)", $previous, _T("Previous page (%i)"))
            );
        }

        for ($i = $idepart; $i <= $ifin; $i++) {
            if ($i == $this->current_page) {
                $paginate .= $this->getLink(
                    "-&nbsp;$i&nbsp;-",
                    $this->getHref($this->current_page),
                    preg_replace(
                        "(%i)",
                        $this->current_page,
                        _T("Current page (%i)")
                    ),
                    true
                );
            } else {
                $paginate .= $this->getLink(
                    $i,
                    $this->getHref($i),
                    preg_replace("(%i)", $i, _T("Page %i"))
                );
            }
        }
        if ($this->current_page != $this->pages) {
            $paginate .= $this->getLink(
                '&gt;',
                $this->getHref($next),
                preg_replace("(%i)", $next, _T("Next page (%i)"))
            );

            $paginate .= $this->getLink(
                '&gt;&gt;',
                $this->getHref($this->pages),
                preg_replace("(%i)", $this->pages, _T("Last page (%i)"))
            );
        }

        $options = array(
            10 => "10",
            20 => "20",
            50 => "50",
            100 => "100"
        );

        if ($restricted === false) {
            $options[0] = _T("All");
        }

        //Now, we assign common variables to Smarty template
        $view->assign('nb_pages', $this->pages);
        $view->assign('page', $this->current_page);
        $view->assign('numrows', $this->show);
        $view->assign('pagination', $paginate);
        $view->assign('nbshow_options', $options);

        $this->view = null;
        $this->router = null;
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
    private function getLink($content, $url, $title, $current = false)
    {
        $tabs = "\t\t\t\t\t\t";
        $link = $tabs . "<li";
        if ($current === true) {
            $link .= " class=\"current\" ";
        }
        $link .= "><a href=\"" . $url . "\" " .
            "title=\"" . $title . "\">" . $content . "</a></li>\n";
        return $link;
    }

    /**
     * Build href
     *
     * @param int $page Page
     *
     * @return string
     */
    protected function getHref($page)
    {
        $args = [
            'option'    => 'page',
            'value'     => $page
        ];

        if ($this->view->getTemplateVars('cur_subroute')) {
            $args['type'] = $this->view->getTemplateVars('cur_subroute');
        }

        $href = $this->router->pathFor(
            $this->view->getTemplateVars('cur_route'),
            $args
        );
        return $href;
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrive
     *
     * @return object the called property
     */
    public function __get($name)
    {

        Analog::log(
            '[' . get_class($this) .
            '|Pagination] Getting property `' . $name . '`',
            Analog::DEBUG
        );

        if (in_array($name, $this->pagination_fields)) {
            return $this->$name;
        } else {
            Analog::log(
                '[' . get_class($this) .
                '|Pagination] Unable to get proprety `' . $name . '`',
                Analog::WARNING
            );
        }
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param object $value a relevant value for the property
     *
     * @return void
     */
    public function __set($name, $value)
    {

        Analog::log(
            '[' . get_class($this) . '|Pagination] Setting property `' .
            $name . '`',
            Analog::DEBUG
        );

        switch ($name) {
            case 'ordered':
                if ($value == self::ORDER_ASC || $value == self::ORDER_DESC) {
                    $this->$name = $value;
                } else {
                    Analog::log(
                        '[' . get_class($this) .
                        '|Pagination] Possibles values for field `' .
                        $name . '` are: `' . self::ORDER_ASC . '` or `' .
                        self::ORDER_DESC . '` - `' . $value . '` given',
                        Analog::WARNING
                    );
                }
                break;
            case 'orderby':
                if ($this->$name == $value) {
                    $this->invertorder();
                } else {
                    $this->$name = $value;
                    $this->setDirection(self::ORDER_ASC);
                }
                break;
            case 'current_page':
            case 'counter':
            case 'pages':
                if (is_int($value) && $value > 0) {
                    $this->$name = $value;
                } else {
                    Analog::log(
                        '[' . get_class($this) .
                        '|Pagination] Value for field `' .
                        $name . '` should be a positive integer - (' .
                        gettype($value) . ')' . $value . ' given',
                        Analog::WARNING
                    );
                }
                break;
            case 'show':
                if (
                    $value == 'all'
                    || preg_match('/[[:digit:]]/', $value)
                    && $value >= 0
                ) {
                    $this->$name = (int)$value;
                } else {
                    Analog::log(
                        '[' . get_class($this) . '|Pagination] Value for `' .
                        $name . '` should be a positive integer or \'all\' - (' .
                        gettype($value) . ')' . $value . ' given',
                        Analog::WARNING
                    );
                }
                break;
            default:
                Analog::log(
                    '[' . get_class($this) .
                    '|Pagination] Unable to set proprety `' . $name . '`',
                    Analog::WARNING
                );
                break;
        }
    }
}
