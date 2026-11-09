<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers;

use Analog\Analog;
use Galette\Controllers\Attributes\Route;
use Galette\Core\AuthThrottle;
use Galette\Core\History;
use Galette\Core\Preferences;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

use function Safe\json_decode;
use function Safe\preg_replace;

/**
 * Content Security Policy violation reports
 *
 * Browsers send a report for each violation of the policy; they are written
 * to the logs, so administrators can see what would break once the policy is
 * enforced, without having to open a browser console.
 *
 * Anyone can post here, so what is logged is limited: a few reports per
 * request, a few requests per address, every field shortened and stripped of
 * control characters.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CspReportController
{
    public const string ROUTE = 'cspReport';

    /** Throttle scope, counting reports per address */
    public const string SCOPE = 'csp-report-ip';

    /** Requests accepted per address in WINDOW seconds */
    public const int MAX_REQUESTS = 30;
    public const int WINDOW = 600;

    /** Reports logged per request */
    public const int MAX_REPORTS = 10;

    /** Body size above which a request is ignored */
    public const int MAX_BODY = 65536;

    private const int MAX_FIELD = 255;

    /**
     * @param AuthThrottle $throttle    Throttle instance
     * @param Preferences  $preferences Preferences instance
     */
    public function __construct(
        private readonly AuthThrottle $throttle,
        private readonly Preferences $preferences
    ) {
    }

    /**
     * Receive violation reports
     *
     * Both formats are accepted: `report-uri` (application/csp-report, the only
     * one Firefox sends) and the Reporting API (application/reports+json).
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    #[Route(
        name: self::ROUTE,
        pattern: '/csp-report',
        methods: ['POST'],
        description: 'Content Security Policy violation reports, sent by browsers',
        requiresAuth: false
    )]
    public function report(Request $request, Response $response): Response
    {
        //the browser does not care about the answer: always the same, whatever happened
        $response = $response->withStatus(204);

        $body = (string)$request->getBody();
        if ($body === '' || strlen($body) > self::MAX_BODY) {
            return $response;
        }

        $address = AuthThrottle::normalizeAddress(History::findUserIPAddress($this->preferences));
        //an unknown address is still counted, all together
        $address = $address !== '' ? $address : 'unknown';
        try {
            if ($this->throttle->getDelayForEvent(self::SCOPE, $address) > 0) {
                return $response;
            }
            $this->throttle->recordEvent(
                scope: self::SCOPE,
                identifier: $address,
                attempts: self::MAX_REQUESTS,
                window: self::WINDOW
            );
        } catch (Throwable) {
            // no log: throttle table may not exist yet, when database needs an update
            return $response;
        }

        foreach (array_slice($this->parse($body), 0, self::MAX_REPORTS) as $report) {
            Analog::log($this->format($report), Analog::WARNING);
        }

        return $response;
    }

    /**
     * Extract reports from a request body
     *
     * @param string $body Request body
     *
     * @return array<int, array<string, string>> Normalized reports
     */
    public function parse(string $body): array
    {
        try {
            $data = json_decode($body, associative: true);
        } catch (Throwable) {
            // no log: anyone can post anything here
            return [];
        }
        if (!is_array($data)) {
            return [];
        }

        //report-uri: a single report
        if (isset($data['csp-report']) && is_array($data['csp-report'])) {
            $report = $data['csp-report'];
            return [$this->normalize([
                'disposition' => $report['disposition'] ?? '',
                'directive' => $report['effective-directive'] ?? $report['violated-directive'] ?? '',
                'blocked' => $report['blocked-uri'] ?? '',
                'document' => $report['document-uri'] ?? '',
                'source' => $report['source-file'] ?? '',
                'line' => $report['line-number'] ?? '',
                'sample' => $report['script-sample'] ?? '',
            ])];
        }

        //Reporting API: a list of reports, of various types
        $reports = [];
        foreach ($data as $entry) {
            if (!is_array($entry) || ($entry['type'] ?? null) !== 'csp-violation' || !is_array($entry['body'] ?? null)) {
                continue;
            }
            $report = $entry['body'];
            $reports[] = $this->normalize([
                'disposition' => $report['disposition'] ?? '',
                'directive' => $report['effectiveDirective'] ?? '',
                'blocked' => $report['blockedURL'] ?? '',
                'document' => $report['documentURL'] ?? $entry['url'] ?? '',
                'source' => $report['sourceFile'] ?? '',
                'line' => $report['lineNumber'] ?? '',
                'sample' => $report['sample'] ?? '',
            ]);
        }
        return $reports;
    }

    /**
     * Make report fields safe to log
     *
     * @param array<string, mixed> $report Raw fields
     *
     * @return array<string, string>
     */
    private function normalize(array $report): array
    {
        $normalized = [];
        foreach ($report as $key => $value) {
            $value = is_scalar($value) ? (string)$value : '';
            if (in_array($key, ['document', 'blocked', 'source'], strict: true)) {
                $value = $this->stripQuery($value);
            }
            //no control characters: a forged report must not be able to fake log lines
            $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
            $normalized[$key] = mb_substr($value, 0, self::MAX_FIELD);
        }
        return $normalized;
    }

    /**
     * Drop query string and fragment of an URL: they may carry tokens
     *
     * @param string $url URL
     */
    private function stripQuery(string $url): string
    {
        return preg_replace('/[?#].*$/s', '', $url);
    }

    /**
     * Format a report for the logs
     *
     * @param array<string, string> $report Normalized report
     */
    private function format(array $report): string
    {
        $message = sprintf(
            'CSP violation%1$s: %2$s blocked %3$s on %4$s',
            $report['disposition'] === 'report' ? ' (report only)' : '',
            $report['directive'] !== '' ? $report['directive'] : 'unknown directive',
            $report['blocked'] !== '' ? $report['blocked'] : 'unknown resource',
            $report['document'] !== '' ? $report['document'] : 'unknown page'
        );
        if ($report['source'] !== '') {
            $message .= sprintf(' (from %1$s:%2$s)', $report['source'], $report['line']);
        }
        if ($report['sample'] !== '') {
            $message .= sprintf(' sample: %1$s', $report['sample']);
        }
        return $message;
    }
}
