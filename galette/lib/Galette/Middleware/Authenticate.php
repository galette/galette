<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Middleware;

use DI\Attribute\Inject;
use Galette\Core\Login;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Analog\Analog;
use RKA\Session;
use RuntimeException;
use Safe\Exceptions\PcreException;
use Slim\Flash\Messages;
use Slim\Routing\RouteContext;
use Slim\Routing\RouteParser;

use function Safe\preg_match;

/**
 * Galette Slim middleware for authentication
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Authenticate
{
    /**
     * @var array<string, string>
     */
    #[Inject('acls')]
    protected array $acls;

    /**
     * Constructor
     *
     * @param Login       $login       Login instance
     * @param Session     $session     Session instance
     * @param RouteParser $routeparser Route parser instance
     * @param Messages    $flash       Flash messages instance
     */
    public function __construct(
        private readonly Login $login,
        private readonly Session $session,
        private readonly RouteParser $routeparser,
        protected Messages $flash
    ) {
    }

    /**
     * Middleware invokable class
     *
     * @param Request        $request PSR7 request
     * @param RequestHandler $handler PSR7 request handler
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $response = new \Slim\Psr7\Response();

        if (!$this->login->isLogged()) {
            if ($request->getMethod() === 'GET') {
                $this->session->set('urlRedirect', $request->getUri()->getPath());
                Analog::log(
                    'Login required to access ' . $this->session->get('urlRedirect'),
                    Analog::DEBUG
                );
            }

            $this->flash->addMessage('error_detected', _T("Login required"));
            return $response
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('slash')
                )->withStatus(302);
        }

        //check for ACLs
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $cur_route = $route->getName();
        $acl = $this->getAclFor($cur_route);

        $go = false;
        switch ($acl) {
            case 'superadmin':
                if ($this->login->isSuperAdmin()) {
                    $go = true;
                }
                break;
            case 'admin':
                if (
                    $this->login->isSuperAdmin()
                    || $this->login->isAdmin()
                ) {
                    $go = true;
                }
                break;
            case 'staff':
                if (
                    $this->login->isSuperAdmin()
                    || $this->login->isAdmin()
                    || $this->login->isStaff()
                ) {
                    $go = true;
                }
                break;
            case 'groupmanager':
                if (
                    $this->login->isSuperAdmin()
                    || $this->login->isAdmin()
                    || $this->login->isStaff()
                    || $this->login->isGroupManager()
                ) {
                    $go = true;
                }
                break;
            case 'member':
                $go = true;
                break;
            default:
                throw new RuntimeException(
                    str_replace(
                        '%acl',
                        $acl,
                        _T("Unknown ACL rule '%acl'!")
                    )
                );
        }
        if (!$go) {
            Analog::log(
                'Permission denied for route ' . $cur_route . ' for user ' . $this->login->login,
                Analog::DEBUG
            );
            $this->flash->addMessage(
                'error_detected',
                _T("You do not have permission for requested URL.")
            );
            return $response
                ->withHeader('Location', $this->routeparser->urlFor('slash'))
                ->withStatus(302);
        }

        return $handler->handle($request);
    }

    /**
     * Get ACL for route name
     *
     * @param string $name Route name
     *
     * @throws RuntimeException
     * @throws PcreException
     */
    public function getAclFor(string $name): string
    {
        //first, check for exact match
        if (isset($this->acls[$name])) {
            return $this->acls[$name];
        }

        //handle routes regexps
        foreach ($this->acls as $regex => $route_acl) {
            //looks like a regular expression, go
            if (preg_match('@/(.+)/[imsxADU]?@', $regex) && preg_match($regex, $name)) {
                return $route_acl;
            }
        }

        throw new RuntimeException(
            sprintf(
                _T('Route \'%1$s\' is not registered in ACLs!'),
                $name,
            )
        );
    }
}
