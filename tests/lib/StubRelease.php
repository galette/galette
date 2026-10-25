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
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

use function Safe\file_get_contents;

/**
 * Release check that never reaches the network.
 *
 * `Galette\Util\Release` is autowired into controllers (`AuthController::doLogin()`),
 * and its constructor already fires requests through the cache layer. Left alone, the
 * test suite therefore queries GitHub API for real, and fails as soon as that host is
 * unreachable or rate limits the runner - which is regularly the case from CI.
 *
 * Only the client factory is replaced, so the API responses are still parsed by the
 * real `findLatestReleases()` code and the stub cannot hide a regression there.
 * Galette repository serves the local release fixture, any other one answers 404.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class StubRelease extends Release
{
    /**
     * Set ups a Guzzle client serving the local release fixture
     */
    public function setupClient(): Client
    {
        $handler = function (RequestInterface $request): PromiseInterface {
            //slim/psr7 is the PSR-7 implementation Galette depends on; guzzlehttp/psr7
            //is only a transitive dependency of the Guzzle client itself
            $response = (new ResponseFactory())->createResponse();
            if (str_contains($request->getUri()->getPath(), '/' . self::CORE_REPOSITORY . '/')) {
                $response = $response->withBody(
                    (new StreamFactory())->createStream(
                        file_get_contents(GALETTE_TESTS_PATH . '/releases.json')
                    )
                );
            } else {
                $response = $response->withStatus(404);
            }
            return Create::promiseFor($response);
        };

        return new Client(
            ['handler' => HandlerStack::create($handler)]
        );
    }
}
