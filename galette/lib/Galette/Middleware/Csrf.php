<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Middleware;

use Analog\Analog;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpForbiddenException;
use Slim\Interfaces\RouteInterface;
use Slim\Routing\RouteContext;

use function Safe\parse_url;
use function Safe\preg_match;

/**
 * Galette Slim CSRF protection middleware
 *
 * Protection relies on headers browsers do not let scripts forge (`Sec-Fetch-Site`,
 * with a fallback on `Origin` for browsers older than 2023), rather than on per form
 * tokens.
 *
 * A request that carries none of those headers does not come from a browser, and
 * therefore cannot be a CSRF attack; it is let through (payment gateways
 * notifications, API clients, ...).
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Csrf
{
    public const array BODYLESS_METHODS = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];

    private const array TRUSTED_FETCH_SITES = [
        //request comes from Galette itself
        'same-origin',
        //request comes from a user action (bookmark, typed URL, ...)
        'none'
    ];

    /**
     * Constructor
     *
     * @param string[] $exclusions Route names patterns excluded from CSRF checks
     */
    public function __construct(private readonly array $exclusions = [])
    {
    }

    /**
     * Middleware invokable class
     *
     * @param Request        $request PSR7 request
     * @param RequestHandler $handler PSR7 request handler
     *
     * @throws HttpForbiddenException
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        if (in_array(strtoupper($request->getMethod()), self::BODYLESS_METHODS, true)) {
            //no CSRF check on methods that are not supposed to change anything
            return $handler->handle($request);
        }

        if ($this->isSafeFromCsrf($request) || $this->isExcluded($request)) {
            return $handler->handle($request);
        }

        Analog::log(
            $this->getFailureMessage($request),
            Analog::CRITICAL
        );

        throw new HttpForbiddenException($request, _T('Failed CSRF check!'));
    }

    /**
     * Can the request be trusted, from a CSRF point of view?
     *
     * @param Request $request PSR7 request
     */
    private function isSafeFromCsrf(Request $request): bool
    {
        //`Sec-Fetch-Site` tells where the request comes from. It is a forbidden header
        //name; browsers always send a legitimate value, scripts cannot alter it.
        $fetch_site = $request->getHeaderLine('Sec-Fetch-Site');
        if ($fetch_site !== '') {
            return in_array($fetch_site, self::TRUSTED_FETCH_SITES, true);
        }

        //fallback on `Origin` (also a forbidden header name) for older browsers
        $origin = $request->getHeaderLine('Origin');
        if ($origin !== '') {
            return $this->isKnownOrigin($origin, $request);
        }

        //request does not come from a browser
        return true;
    }

    /**
     * Does provided origin match the host Galette is reached at?
     *
     * @param string  $origin  Contents of the `Origin` header
     * @param Request $request PSR7 request
     */
    private function isKnownOrigin(string $origin, Request $request): bool
    {
        $parsed = parse_url($origin);
        if (!is_array($parsed) || !isset($parsed['host']) || $parsed['host'] === '') {
            //malformed origin
            return false;
        }

        $origin_host = strtolower((string)$parsed['host']);
        $origin_port = $parsed['port'] ?? null;

        foreach ($this->getExpectedHosts($request) as [$host, $port]) {
            if ($origin_host !== $host) {
                continue;
            }
            //ports are compared only when both are known; a missing one means default
            if ($origin_port === null || $port === null || $origin_port === $port) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get hosts Galette can legitimately be reached at
     *
     * `X-Forwarded-Host` is taken into account, as reverse proxies do rewrite the
     * `Host` header with the name of the backend they target.
     *
     * @param Request $request PSR7 request
     *
     * @return array<int, array{0: string, 1: ?int}> List of host and port couples
     */
    private function getExpectedHosts(Request $request): array
    {
        $hosts = [];

        $forwarded_host = $request->getHeaderLine('X-Forwarded-Host');
        if ($forwarded_host !== '') {
            //may hold a comma separated list, when several proxies are chained
            $forwarded_port = $request->getHeaderLine('X-Forwarded-Port');
            foreach (explode(',', $forwarded_host) as $entry) {
                $host = $this->splitAuthority($entry);
                if ($host === null) {
                    continue;
                }
                if ($host[1] === null && $forwarded_port !== '') {
                    $host[1] = (int)$forwarded_port;
                }
                $hosts[] = $host;
            }
        }

        //the request URI is built from the `Host` header, but keeps the port
        $uri = $request->getUri();
        if ($uri->getHost() !== '') {
            $hosts[] = [strtolower($uri->getHost()), $uri->getPort()];
        } else {
            $host = $this->splitAuthority($request->getHeaderLine('Host'));
            if ($host !== null) {
                $hosts[] = $host;
            }
        }

        return $hosts;
    }

    /**
     * Split an authority (`host` or `host:port`) into its components
     *
     * Hosts are returned bracketed for IPv6 addresses, just like `parse_url()` and
     * `UriInterface::getHost()` do, so both sides of a comparison always match.
     *
     * @param string $authority Authority to split
     *
     * @return ?array{0: string, 1: ?int} Null if no host could be found
     */
    private function splitAuthority(string $authority): ?array
    {
        $authority = strtolower(trim($authority));
        if ($authority === '') {
            return null;
        }

        $bracket = strpos($authority, ']');
        if ($bracket !== false) {
            //bracketed IPv6 address; only a colon past the closing bracket is a port
            $position = strpos($authority, ':', $bracket);
        } elseif (substr_count($authority, ':') > 1) {
            //bare IPv6 address: it should have been bracketed, some proxies do not
            $authority = '[' . $authority . ']';
            $position = false;
        } else {
            $position = strrpos($authority, ':');
        }

        $port = null;
        if ($position !== false) {
            $port = (int)substr($authority, $position + 1);
            $authority = substr($authority, 0, $position);
        }

        if ($authority === '' || $authority === '[]') {
            return null;
        }

        return [$authority, $port];
    }

    /**
     * Is current route excluded from CSRF checks?
     *
     * Exclusions are meant for routes third party services send browsers to with a
     * POST request, such as payment gateways return URLs.
     *
     * @param Request $request PSR7 request
     */
    private function isExcluded(Request $request): bool
    {
        $route_name = $this->getRouteName($request);
        if ($route_name === null) {
            return false;
        }

        foreach ($this->exclusions as $exclusion) {
            if (preg_match($exclusion, $route_name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get current route name, if any
     *
     * @param Request $request PSR7 request
     */
    private function getRouteName(Request $request): ?string
    {
        $route = $request->getAttribute(RouteContext::ROUTE);
        if (!$route instanceof RouteInterface) {
            return null;
        }

        return $route->getName();
    }

    /**
     * Build the message logged when a request has been refused
     *
     * @param Request $request PSR7 request
     */
    private function getFailureMessage(Request $request): string
    {
        $expected = [];
        foreach ($this->getExpectedHosts($request) as [$host, $port]) {
            $expected[] = $host . ($port === null ? '' : ':' . $port);
        }

        return sprintf(
            'CSRF check has failed for route "%s" (%s %s) - Sec-Fetch-Site: %s; Origin: %s; expected host: %s',
            $this->getRouteName($request) ?? 'unknown',
            $request->getMethod(),
            $request->getUri()->getPath(),
            $request->getHeaderLine('Sec-Fetch-Site') ?: 'not set',
            $request->getHeaderLine('Origin') ?: 'not set',
            count($expected) ? implode(', ', $expected) : 'unknown'
        );
    }
}
