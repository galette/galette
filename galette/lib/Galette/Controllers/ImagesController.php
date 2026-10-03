<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers;

use Galette\Controllers\Attributes\Route;
use Slim\Psr7\Response;
use Galette\Core\Picture;
use Galette\Entity\Adherent;

use function Safe\file_get_contents;
use function Safe\fopen;
use function Safe\fwrite;
use function Safe\rewind;

/**
 * Galette images controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class ImagesController extends AbstractController
{
    /**
     * Send response
     */
    protected function sendResponse(Response $response, Picture $picture): Response
    {
        $response = $response->withHeader('Content-Type', $picture->getMime())
            ->withHeader('Content-Transfer-Encoding', 'binary')
            ->withHeader('Expires', '0')
            ->withHeader('Cache-Control', 'must-revalidate')
            ->withHeader('Pragma', 'public');

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, file_get_contents($picture->getPath()));
        rewind($stream);

        return $response->withBody(new \Slim\Psr7\Stream($stream));
    }

    /**
     * Logo route
     */
    #[Route(
        name: 'logo',
        pattern: '/logo',
        methods: ['GET'],
        requiresAuth: false
    )]
    public function logo(Response $response): Response
    {
        return $this->sendResponse($response, $this->logo);
    }

    /**
     * Print logo route
     */
    #[Route(
        name: 'printLogo',
        pattern: '/print-logo',
        methods: ['GET'],
        requiresAuth: false
    )]
    public function printLogo(Response $response): Response
    {
        return $this->sendResponse($response, $this->print_logo);
    }

    /**
     * Photos
     *
     * @param int $id Member id
     */
    #[Route(
        name: 'photo',
        pattern: '/photo/{id:\d+}',
        methods: ['GET'],
        requiresAuth: false
    )]
    public function photo(Response $response, int $id, Adherent $adh, Picture $picture): Response
    {
        $adh->disableDep('dues');
        if (!$this->login->isGroupManager()) {
            //if logged-in user is a group manager, we have to check
            //he manages a group requested member belongs to.
            $adh->disableDep('groups');
        }
        $adh->load($id);

        if (
            $adh->canEdit($this->login)
            || ($this->preferences->showPublicPage($this->login, 'pref_publicpages_visibility_membersgallery')
                || $this->preferences->showPublicPage($this->login, 'pref_publicpages_visibility_staffgallery'))
            && $adh->appearsInMembersList()
        ) {
            $picture = $adh->picture;
        }

        return $this->sendResponse($response, $picture);
    }
}
