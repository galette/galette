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

use Analog\Analog;
use Slim\Views\Smarty;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Error handler
 *
 * @category  Handlers
 * @name      GaletteError
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2017-02-25
 */
trait GaletteError
{
    protected $view;

    /**
     * Constructor
     *
     * @param Smarty $view                View instance
     * @param bool   $displayErrorDetails Set to true to display full details
     */
    public function __construct(Smarty $view, $displayErrorDetails = false)
    {
        $this->view = $view;
        $this->displayErrorDetails = (bool) $displayErrorDetails;
    }


    /**
     * Write to the error log whether displayErrorDetails is false or not
     *
     * @param \Exception|\Throwable $throwable Error
     * @overrides \Slim\Handlers\AbstractError::writeToErrorLog()
     *
     * @return void
     */
    protected function writeToErrorLog($throwable)
    {
        $message = 'Galette error:' . PHP_EOL;
        $message .= $this->renderThrowableAsText($throwable);
        while ($throwable = $throwable->getPrevious()) {
            $message .= PHP_EOL . 'Previous error:' . PHP_EOL;
            $message .= $this->renderThrowableAsText($throwable);
        }

        $this->logError($message);
    }

    /**
     * Determine which content type we know about is wanted using Accept header
     *
     * Note: This method is a bare-bones implementation designed specifically for
     * Slim's error handling requirements. Consider a fully-feature solution such
     * as willdurand/negotiation for any other situation.
     *
     * @param ServerRequestInterface $request Request
     * @return string
     */
    protected function determineContentType(ServerRequestInterface $request)
    {
        if ($request->isXhr()) {
            //get error as JSON for XHR request; more lisible
            return 'application/json';
        }
        return parent::determineContentType($request);
    }
}
