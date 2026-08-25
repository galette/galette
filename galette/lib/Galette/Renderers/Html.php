<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Renderers;

use Galette\Exception\MissingAssetException;
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

        $missing = $this->findMissingAsset($exception);
        if ($missing !== null) {
            //Twig error pages display the logo, which is exactly what is
            //missing; render a self-contained page instead.
            return $this->renderMissingAsset($missing);
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

    /**
     * Look for a missing asset error in the exception chain
     *
     * @param Throwable $exception The exception
     */
    private function findMissingAsset(Throwable $exception): ?MissingAssetException
    {
        $current = $exception;
        while ($current !== null) {
            if ($current instanceof MissingAssetException) {
                return $current;
            }
            $current = $current->getPrevious();
        }
        return null;
    }

    /**
     * Render a self-contained page for a missing asset.
     *
     * Neither Twig nor any stylesheet, script or image is involved: they all
     * come from the very build that is missing.
     *
     * @param MissingAssetException $exception The missing asset
     */
    private function renderMissingAsset(MissingAssetException $exception): string
    {
        $title = 'Galette assets are missing';
        $path = htmlspecialchars($exception->getPath(), ENT_QUOTES);

        return '<!DOCTYPE html>
<html lang="en">
    <head>
        <title>' . $title . '</title>
        <meta charset="UTF-8"/>
        <meta name="viewport" content="width=device-width"/>
        <style type="text/css">
            body { margin: 0; padding: 2em 1em; background: #f4f4f5; color: #1b1c1d;
                font-family: system-ui, -apple-system, "Segoe UI", sans-serif; line-height: 1.5; }
            main { max-width: 45em; margin: 0 auto; background: #fff; padding: 1.5em 2em;
                border: 1px solid #d4d4d5; border-radius: .3em; }
            h1 { margin-top: 0; }
            p.error { padding: .8em 1em; border-radius: .3em;
                background: #fbe9e9; border: 1px solid #e0b4b4; color: #9f3a38; }
            code { background: #f4f4f5; padding: .1em .3em; border-radius: .2em; word-break: break-all; }
        </style>
    </head>
    <body>
        <main>
            <h1>' . $title . '</h1>
            <p class="error">Galette cannot find <code>' . $path . '</code>.</p>
            <p>Assets are not part of the source repository, and have to be built.</p>
            <p>If you installed Galette from a release archive, please download it again from
                <a href="https://galette.eu">galette.eu</a>.</p>
        </main>
    </body>
</html>';
    }
}
