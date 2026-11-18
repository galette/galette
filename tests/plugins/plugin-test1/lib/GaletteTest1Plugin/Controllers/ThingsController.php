<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteTest1Plugin\Controllers;

use DI\Attribute\Inject;
use Galette\Controllers\AbstractPluginController;
use Galette\Controllers\Attributes\Route;
use GaletteTest1Plugin\Thing;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Test plugin things pages: their dynamic fields are drawn, checked and stored by the core
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ThingsController extends AbstractPluginController
{
    /**
     * @var array<string, mixed>
     */
    #[Inject("Plugin Galette Test1 Plugin")]
    protected array $module_info;

    /**
     * Display a thing
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param int      $id       Thing identifier
     */
    #[Route(name: 'plugin1_thing', pattern: '/thing/{id:\d+}')]
    public function show(Request $request, Response $response, int $id): Response
    {
        $this->view->render(
            $response,
            $this->getTemplate('thing_show'),
            [
                'page_title' => sprintf('Thing #%d', $id),
                'thing' => $this->getThing($request, $id)
            ]
        );
        return $response;
    }

    /**
     * Thing form
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param int      $id       Thing identifier
     */
    #[Route(name: 'plugin1_thing_edit', pattern: '/thing/{id:\d+}/edit')]
    public function edit(Request $request, Response $response, int $id): Response
    {
        $this->view->render(
            $response,
            $this->getTemplate('thing_form'),
            [
                'page_title' => sprintf('Edit thing #%d', $id),
                'thing' => $this->getThing($request, $id)
            ]
        );
        return $response;
    }

    /**
     * Store a thing
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param int      $id       Thing identifier
     */
    #[Route(name: 'plugin1_thing_doedit', pattern: '/thing/{id:\d+}/edit', methods: ['POST'])]
    public function doEdit(Request $request, Response $response, int $id): Response
    {
        $thing = $this->getThing($request, $id);
        $files = $request->getUploadedFiles();

        $stored = $thing->check((array)$request->getParsedBody(), $files) && $thing->store();
        if ($stored) {
            //other values are stored: files that failed are to be sent again
            $thing->storeFiles($files);
        }

        if ($thing->getErrors() !== []) {
            return $this->redirectWithErrors(
                response: $response,
                errors: $stored ? ['Thing has been stored, but not all its files.', ...$thing->getErrors()] : $thing->getErrors(),
                redirect_url: $this->routeparser->urlFor('plugin1_thing_edit', ['id' => (string)$id])
            );
        }

        return $this->redirect(
            response: $response,
            redirect_url: $this->routeparser->urlFor('plugin1_thing', ['id' => (string)$id]),
            successes: ['Thing has been stored.']
        );
    }

    /**
     * Get a thing the user can see
     *
     * @param Request $request PSR Request
     * @param int     $id      Thing identifier
     */
    private function getThing(Request $request, int $id): Thing
    {
        if (!Thing::canSee($id, $this->login)) {
            throw new HttpNotFoundException($request);
        }
        return new Thing($this->zdb, $this->login, $id);
    }
}
