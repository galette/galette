<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests;

use Galette\Util\Release;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

use function Safe\file_get_contents;

/**
 * Release check that never reaches the network.
 *
 * `Galette\Util\Release` is autowired into controllers (`AuthController::doLogin()`),
 * and its constructor already fires a request through the cache layer. Left alone, the
 * test suite therefore queries https://galette.eu/download/ for real, and fails as soon
 * as that host is unreachable - which is regularly the case from CI runners.
 *
 * Only the client factory is replaced, so the release listing is still parsed by the
 * real `findLatestRelease()` code and the stub cannot hide a regression there.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class StubRelease extends Release
{
    /**
     * Set ups a Guzzle client serving the local releases listing fixture
     */
    public function setupClient(): Client
    {
        //slim/psr7 is the PSR-7 implementation Galette depends on; guzzlehttp/psr7
        //is only a transitive dependency of the Guzzle client itself
        $response = (new ResponseFactory())
            ->createResponse()
            ->withBody(
                (new StreamFactory())->createStream(
                    file_get_contents(GALETTE_TESTS_PATH . '/releases.html')
                )
            );

        return new Client(
            ['handler' => HandlerStack::create(new MockHandler([$response]))]
        );
    }
}
