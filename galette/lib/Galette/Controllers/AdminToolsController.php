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

namespace Galette\Controllers;

use Galette\DynamicFields\Date;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Entity\Texts;
use Galette\Repository\Members;
use Galette\Repository\PdfModels;

/**
 * Galette main controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class AdminToolsController extends AbstractController
{
    /**
     * Administration tools page
     */
    public function adminTools(Response $response): Response
    {
        $params = [
            'page_title'        => _T('Administration tools'),
            'documentation'     => 'usermanual/avancee.html#administration-tools'
        ];

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
     */
    public function process(
        Request $request,
        Response $response,
        Texts $texts,
        PdfModels $models,
        Members $members,
    ): Response {
        $post = $request->getParsedBody();

        $error_detected = [];
        $success_detected = [];

        if (isset($post['inittexts'])) {
            //proceed emails texts reinitialization
            $res = $texts->installInit(false);
            if ($res === true) {
                $success_detected[] = _T("Texts has been successfully reinitialized.");
            } else {
                $error_detected[] = _T("An error occurred reinitializing texts :(");
            }
        }

        if (isset($post['initfields'])) {
            //proceed fields configuration reinitialization
            $res = $this->fields_config->installInit();
            if ($res === true) {
                $success_detected[] = _T("Fields configuration has been successfully reinitialized.");
            } else {
                $error_detected[] = _T("An error occurred reinitializing fields configuration :(");
            }
        }

        if (isset($post['initpdfmodels'])) {
            //proceed emails texts reinitialization
            $res = $models->installInit(false);
            if ($res === true) {
                $success_detected[] = _T("PDF models has been successfully reinitialized.");
            } else {
                $error_detected[] = _T("An error occurred reinitializing PDF models :(");
            }
        }

        if (isset($post['emptylogins'])) {
            //proceed empty logins and passwords
            //those cannot be null
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

        if (isset($post['dynamicdates'])) {
            //proceed dynamic dates fix
            $res = Date::resetLocalizedFormats($this->zdb);
            if ($res === true) {
                $success_detected[] = _T("Dynamic dates has been fixed.");
            } else {
                $error_detected[] = _T("An error occurred fixing dynamic dates :(");
            }
        }

        return $this->redirect(
            response: $response,
            redirect_url: $this->routeparser->urlFor('adminTools'),
            successes: $success_detected,
            errors: $error_detected
        );
    }
}
