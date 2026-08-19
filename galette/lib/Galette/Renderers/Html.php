<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Renderers;

use Galette\Exception\PHPStartupException;
use Slim\Exception\HttpException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpNotFoundException;
use Slim\Flash\Messages;
use Slim\Interfaces\ErrorRendererInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Throwable;

/**
 * HTML error renderer
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Html implements ErrorRendererInterface
{
    /**
     * Constructor
     *
     * @param Twig     $view  View instance
     * @param Messages $flash Flash messages
     */
    public function __construct(
        protected Twig $view,
        protected Messages $flash
    ) {
    }

    /**
     * Invoke renderer
     *
     * @param Throwable $exception           The exception
     * @param bool      $displayErrorDetails Should we display the error details
     */
    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        $code = 500;
        $title = __('Galette error');
        if ($exception instanceof HttpNotFoundException) {
            $code = 404;
            $title = __('Page not found');
        } elseif ($exception instanceof HttpForbiddenException) {
            $code = 403;
            $title = __('Access denied');
        } elseif ($exception instanceof HttpException && $exception->getCode() >= 400 && $exception->getCode() < 600) {
            //any other HTTP error; rely on generic error page if there is no dedicated one
            $code = $exception->getCode();
        }

        $php_error = error_get_last();
        if ($php_error !== null) {
            $this->flash->addMessageNow('error', $php_error['message']);
            $exception = new PHPStartupException($php_error['message'], $php_error['type'], $exception);
        }

        $template = 'pages/' . (string)$code . '.html.twig';
        if (!$this->view->getLoader()->exists($template)) {
            $template = 'pages/500.html.twig';
        }

        $response = (new Response())->withStatus($code);
        $response = $this->view->render(
            $response,
            $template,
            [
                'page_title'    => $title,
                'exception'     => $exception
            ]
        );

        return (string)$response->getBody();
    }
}
