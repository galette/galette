<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Analog\Analog;
use Galette\Controllers\CspReportController;
use Galette\Core\AuthThrottle;
use Galette\Tests\GaletteTestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

use function Safe\json_encode;

/**
 * Content Security Policy violation reports controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CspReportControllerTest extends GaletteTestCase
{
    private const string ADDRESS = '192.0.2.42';

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $_SERVER['REMOTE_ADDR'] = self::ADDRESS;
    }

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        $this->getThrottle()->clearEvent(AuthThrottle::SCOPE_CSP_REPORT_IP, self::ADDRESS);
        unset($_SERVER['REMOTE_ADDR']);
        parent::tearDown();
    }

    /**
     * Get throttle instance
     */
    private function getThrottle(): AuthThrottle
    {
        return new AuthThrottle($this->zdb, $this->preferences, clean: false);
    }

    /**
     * Post a body to the report route
     *
     * @param string $body         Request body
     * @param string $content_type Content type
     */
    private function post(string $body, string $content_type = 'application/csp-report'): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', $this->routeparser->urlFor(CspReportController::ROUTE))
            ->withHeader('Content-Type', $content_type)
            ->withBody((new StreamFactory())->createStream($body));
        return $this->app->handle($request);
    }

    /**
     * Get a report-uri format report
     *
     * @param array<string, mixed> $fields Fields to override
     */
    private function legacyReport(array $fields = []): string
    {
        return json_encode(['csp-report' => $fields + [
            'document-uri' => 'https://galette.example.org/members?token=secret#top',
            'referrer' => '',
            'violated-directive' => 'script-src-elem',
            'effective-directive' => 'script-src-elem',
            'original-policy' => "default-src 'self'",
            'disposition' => 'report',
            'blocked-uri' => 'inline',
            'line-number' => 12,
            'source-file' => 'https://galette.example.org/members',
            'status-code' => 200,
            'script-sample' => 'alert(1)',
        ]]);
    }

    /**
     * Test report-uri format
     */
    public function testLegacyReport(): void
    {
        $response = $this->post($this->legacyReport());

        $this->assertSame(204, $response->getStatusCode());
        $this->expectLogEntry(
            Analog::WARNING,
            'CSP violation (report only): script-src-elem blocked inline on https://galette.example.org/members'
            . ' (from https://galette.example.org/members:12) sample: alert(1)'
        );
        $this->expectNoLogEntry();
    }

    /**
     * Test Reporting API format
     */
    public function testReportingApi(): void
    {
        $reports = [
            [
                'type' => 'csp-violation',
                'age' => 10,
                'url' => 'https://galette.example.org/dashboard',
                'user_agent' => 'Test',
                'body' => [
                    'documentURL' => 'https://galette.example.org/dashboard',
                    'blockedURL' => 'https://tracker.example.com/t.js',
                    'effectiveDirective' => 'script-src-elem',
                    'disposition' => 'enforce',
                    'sourceFile' => '',
                    'lineNumber' => 0,
                ],
            ],
            //not a CSP report: ignored
            ['type' => 'deprecation', 'body' => ['id' => 'foo']],
            //malformed: ignored
            ['type' => 'csp-violation', 'body' => 'nope'],
        ];

        $response = $this->post(json_encode($reports), 'application/reports+json');

        $this->assertSame(204, $response->getStatusCode());
        $this->expectLogEntry(
            Analog::WARNING,
            'CSP violation: script-src-elem blocked https://tracker.example.com/t.js on https://galette.example.org/dashboard'
        );
        $this->expectNoLogEntry();
    }

    /**
     * Test forged reports cannot inject log lines, and are bounded
     */
    public function testSanitize(): void
    {
        $controller = new CspReportController($this->getThrottle(), $this->preferences);
        $reports = $controller->parse($this->legacyReport([
            'blocked-uri' => "inline\n2026-01-01 - CRITICAL - forged entry",
            'script-sample' => str_repeat('a', 1000),
            'line-number' => ['not', 'scalar'],
        ]));

        $this->assertCount(1, $reports);
        $this->assertSame('inline 2026-01-01 - CRITICAL - forged entry', $reports[0]['blocked']);
        $this->assertSame(255, mb_strlen($reports[0]['sample']));
        $this->assertSame('', $reports[0]['line']);
        //query strings and fragments may carry tokens
        $this->assertSame('https://galette.example.org/members', $reports[0]['document']);

        //as well as credentials, and paths of links sent by mail
        $token = str_repeat('0123456789abcdef', 4);
        $reports = $controller->parse($this->legacyReport([
            'document-uri' => 'https://galette.example.org/password-recovery/' . $token,
            'blocked-uri' => 'https://user:SECRET@evil.example.org/collect',
        ]));
        $this->assertSame('https://galette.example.org/password-recovery/…', $reports[0]['document']);
        $this->assertSame('https://evil.example.org/collect', $reports[0]['blocked']);
    }

    /**
     * Test invalid bodies are ignored
     */
    public function testInvalidBodies(): void
    {
        foreach (['', 'not json', '"a string"', '{"foo": "bar"}', str_repeat(' ', CspReportController::MAX_BODY + 1)] as $body) {
            $this->assertSame(204, $this->post($body)->getStatusCode());
        }
        $this->expectNoLogEntry();
    }

    /**
     * Test the number of reports logged is limited
     */
    public function testThrottle(): void
    {
        //a single request cannot log more than a few reports
        $reports = array_fill(0, CspReportController::MAX_REPORTS + 5, [
            'type' => 'csp-violation',
            'body' => ['effectiveDirective' => 'img-src', 'blockedURL' => 'https://img.example.com/a.png'],
        ]);
        $this->post(json_encode($reports), 'application/reports+json');
        global $galette_log_var;
        $this->assertSame(
            CspReportController::MAX_REPORTS,
            substr_count((string)$galette_log_var, 'img-src blocked https://img.example.com/a.png')
        );
        //removes them all
        $this->expectLogEntry(Analog::WARNING, 'img-src blocked https://img.example.com/a.png');
        $this->expectNoLogEntry();

        //nor can an address send requests forever
        for ($i = 1; $i < CspReportController::MAX_REQUESTS; ++$i) {
            $this->post($this->legacyReport());
        }
        $this->assertGreaterThan(0, $this->getThrottle()->getDelayForEvent(AuthThrottle::SCOPE_CSP_REPORT_IP, self::ADDRESS));

        //drop what has been logged so far
        $galette_log_var = '';

        $this->assertSame(204, $this->post($this->legacyReport())->getStatusCode());
        $this->expectNoLogEntry();
    }

    /**
     * Test a report of the largest allowed size is read in full
     */
    public function testLargestBody(): void
    {
        $body = $this->legacyReport();
        $body .= str_repeat(' ', CspReportController::MAX_BODY - strlen($body));
        $this->assertSame(CspReportController::MAX_BODY, strlen($body));

        $this->assertSame(204, $this->post($body)->getStatusCode());
        $this->expectLogEntry(Analog::WARNING, 'script-src-elem blocked inline');

        //one byte more is ignored
        $this->assertSame(204, $this->post($body . ' ')->getStatusCode());
        $this->expectNoLogEntry();
    }

    /**
     * Test IPv6 locks are listed with their address
     */
    public function testIpV6Lock(): void
    {
        $_SERVER['REMOTE_ADDR'] = '2001:db8:1:2:3:4:5:6';
        $identifier = AuthThrottle::normalizeAddress($_SERVER['REMOTE_ADDR']);
        $this->assertSame('2001:db8:1:2::/64', $identifier);

        try {
            for ($i = 0; $i < CspReportController::MAX_REQUESTS; ++$i) {
                $this->post('{}');
            }

            $locks = array_values(array_filter(
                $this->getThrottle()->getLocks(),
                fn(array $lock): bool => $lock['scope'] === AuthThrottle::SCOPE_CSP_REPORT_IP
            ));
            $this->assertCount(1, $locks);
            $this->assertSame($identifier, $locks[0]['address']);
            $this->assertNull($locks[0]['login']);
        } finally {
            $this->getThrottle()->clearEvent(AuthThrottle::SCOPE_CSP_REPORT_IP, $identifier);
        }
    }
}
