<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Error handler that overrides slim's one
 *
 * PHP version 5
 *
 * Copyright © 2017 The Galette Team
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
 * @category  Handlers
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2017-02-25
 */

namespace Galette\Handlers;

use Slim\Handlers\PhpError as SlimError;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Analog\Analog;

/**
 * Error handler
 *
 * @category  Handlers
 * @name      PhpError
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2017-02-25
 */
class PhpError extends SlimError
{
    use GaletteError;

    /**
     * Invoke error handler
     *
     * @param ServerRequestInterface $request  The most recent Request object
     * @param ResponseInterface      $response The most recent Response object
     * @param \Throwable             $error    The caught Throwable object
     *
     * @return ResponseInterface
     * @throws UnexpectedValueException
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, \Throwable $error)
    {
        $response = parent::__invoke($request, $response, $error);

        if ($response->getHeaderLine('Content-Type') == 'text/html') {
            $response->getBody()->rewind();

            $this->view->render(
                $response,
                '500.tpl',
                [
                    'page_title'    => __('Galette error'),
                    'exception'     => $error
                ]
            );
        }

        return $response;
    }
}
