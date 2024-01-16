<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Error handler that overrides Slim's one
 *
 * PHP version 5
 *
 * Copyright © 2023 The Galette Team
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
 *
 * @category  Renderers
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     2023-02-11
 */

namespace Galette\Renderers;

use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\ErrorRendererInterface;
use Slim\Views\Twig;
use Throwable;

/**
 * HMTL error renderer
 *
 * @category  Renderers
 * @name      Html
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     2023-02-11
 */
class Html implements ErrorRendererInterface
{
    protected Twig $view;

    /**
     * Constructor
     *
     * @param Twig $view View instance
     */
    public function __construct(Twig $view)
    {
        $this->view = $view;
    }

    /**
     * Invoke renderer
     *
     * @param Throwable $exception           The exception
     * @param bool      $displayErrorDetails Should we display the error details
     *
     * @return string
     */
    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        $code = 500;
        $title = __('Galette error');
        if ($exception instanceof HttpNotFoundException) {
            $code = 404;
            $title = __('Page not found');
        }

        $response = (new \Slim\Psr7\Response())->withStatus($code);
        $response = $this->view->render(
            $response,
            'pages/' . (string)$code . '.html.twig',
            [
                'page_title'    => $title,
                'exception'     => $exception
            ]
        );

        return $response->getBody();
    }
}
