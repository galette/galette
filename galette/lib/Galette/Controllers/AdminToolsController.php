<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers;

use Galette\Controllers\Attributes\Route;
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
    #[Route(
        name: 'adminTools',
        pattern: '/admin-tools',
        methods: ['GET']
    )]
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
    #[Route(
        name: 'doAdminTools',
        pattern: '/admin-tools',
        methods: ['POST']
    )]
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
