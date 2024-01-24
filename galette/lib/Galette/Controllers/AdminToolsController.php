<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

namespace Galette\Controllers;

use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Core\CheckModules;
use Galette\Entity\Texts;
use Galette\Repository\Members;
use Galette\Repository\PdfModels;
use Analog\Analog;

/**
 * Galette main controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class AdminToolsController extends AbstractController
{
    /**
     * Administration tools page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function adminTools(Request $request, Response $response): Response
    {
        $params = [
            'page_title'        => _T('Administration tools')
        ];

        $cm = new CheckModules();
        $modules_ok = $cm->isValid();
        if (!$modules_ok) {
            $this->flash->addMessage(
                'error_detected',
                _T("Some PHP modules are missing. Please install them or contact your support.<br/>More information on required modules may be found in the documentation.")
            );
        }

        // display page
        $this->view->render(
            $response,
            'pages/admintools.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Process Administration tools
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function process(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();

        $error_detected = [];
        $success_detected = [];

        if (isset($post['inittexts'])) {
            //proceed emails texts reinitialization
            $texts = new Texts($this->preferences);
            $res = $texts->installInit(false);
            if ($res === true) {
                $success_detected[] = _T("Texts has been successfully reinitialized.");
            } else {
                $error_detected[] = _T("An error occurred reinitializing texts :(");
            }
        }

        if (isset($post['initfields'])) {
            //proceed fields configuration reinitialization
            $fc = $this->fields_config;
            $res = $fc->installInit();
            if ($res === true) {
                $success_detected[] = _T("Fields configuration has been successfully reinitialized.");
            } else {
                $error_detected[] = _T("An error occurred reinitializing fields configuration :(");
            }
        }

        if (isset($post['initpdfmodels'])) {
            //proceed emails texts reinitialization
            $models = new PdfModels($this->zdb, $this->preferences, $this->login);
            $res = $models->installInit(false);
            if ($res === true) {
                $success_detected[] = _T("PDF models has been successfully reinitialized.");
            } else {
                $error_detected[] = _T("An error occurred reinitializing PDF models :(");
            }
        }

        if (isset($post['emptylogins'])) {
            //proceed empty logins and passwords
            //those ones cannot be null
            $members = new Members();
            $res = $members->emptylogins();
            if ($res === true) {
                $success_detected[] = str_replace(
                    '%i',
                    (string)$members->getCount(),
                    _T("Logins and passwords have been successfully filled (%i processed).")
                );
            } else {
                $error_detected[] = _T("An error occurred filling empty logins and passwords :(");
            }
        }

        //flash messages
        if (count($error_detected) > 0) {
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        }
        if (count($success_detected) > 0) {
            foreach ($success_detected as $success) {
                $this->flash->addMessage(
                    'success_detected',
                    $success
                );
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('adminTools'));
    }
}
