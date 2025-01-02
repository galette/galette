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

namespace Galette\Renderers;

use Galette\Exception\PHPStartupException;
use Slim\Exception\HttpNotFoundException;
use Slim\Flash\Messages;
use Slim\Interfaces\ErrorRendererInterface;
use Slim\Views\Twig;
use Throwable;

/**
 * HTML error renderer
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Html implements ErrorRendererInterface
{
    protected Twig $view;
    protected Messages $flash;

    /**
     * Constructor
     *
     * @param Twig     $view  View instance
     * @param Messages $flash Flash messages
     */
    public function __construct(Twig $view, Messages $flash)
    {
        $this->view = $view;
        $this->flash = $flash;
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

        $php_error = error_get_last();
        if ($php_error !== null) {
            $this->flash->addMessageNow('error', $php_error['message']);
            $exception = new PHPStartupException($php_error['message'], $php_error['type'], $exception);
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

        return (string)$response->getBody();
    }
}
