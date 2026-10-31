<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Filters\HistoryList;
use Galette\Tests\GaletteRoutingTestCase;

use function Safe\json_decode;
use function Safe\strtotime;

/**
 * History controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class HistoryControllerTest extends GaletteRoutingTestCase
{
    protected int $seed = 20261031154517;

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        unset($this->session->history_filter);
        $this->cleanMembers();
        parent::tearDown();
    }

    /**
     * Get history entries count
     */
    private function countEntries(): int
    {
        return count($this->getActions());
    }

    /**
     * Get history entries actions
     *
     * @return array<int, string>
     */
    private function getActions(): array
    {
        $actions = [];
        foreach ($this->zdb->execute($this->zdb->select(\Galette\Core\History::TABLE)) as $row) {
            $actions[] = (string)$row->action_log;
        }
        return $actions;
    }

    /**
     * History is for staff only
     */
    public function testAccess(): void
    {
        $request = $this->createRequest('history');
        $this->expectLogin($this->app->handle($request));

        $this->getMemberOne();
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->history->add('Test entry');
        $requests = [
            $request,
            $this->createRequest('flushHistory'),
            $this->createRequest('doFlushHistory', [], 'POST')->withParsedBody(['confirm' => '1']),
            $this->createRequest('history_filter', [], 'POST')->withParsedBody(['nbshow' => '10']),
        ];
        foreach ($requests as $request) {
            $this->expectAuthMiddlewareRefused($this->app->handle($request));
        }
        $this->assertGreaterThan(0, $this->countEntries());
    }

    /**
     * List, filter and paginate
     */
    public function testList(): void
    {
        $this->logSuperAdmin();
        for ($i = 0; $i < 12; ++$i) {
            $this->history->add('Test history entry ' . $i, 'Test argument');
        }

        $test_response = $this->app->handle($this->createRequest('history'));
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Test history entry 11', $body);

        //filter on action, with a smaller page size
        $request = $this->createRequest('history_filter', [], 'POST')
            ->withParsedBody([
                'nbshow' => '10',
                'start_date_filter' => date('Y-m-d', strtotime('-1 day')),
                'end_date_filter' => date('Y-m-d', strtotime('+1 day')),
                'user_filter' => '0',
                'action_filter' => 'Test history entry 3'
            ]);
        $this->expectRedirect($this->app->handle($request), $this->routeparser->urlFor('history'));
        $filters = $this->session->history_filter;
        $this->assertInstanceOf(HistoryList::class, $filters);
        $this->assertSame(10, $filters->show);
        $this->assertSame('Test history entry 3', $filters->action_filter);

        $test_response = $this->app->handle($this->createRequest('history'));
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Test history entry 3', $body);
        $this->assertStringNotContainsString('Test history entry 11', $body);

        //clear filter, then paginate and order
        $request = $this->createRequest('history_filter', [], 'POST')
            ->withParsedBody(['clear_filter' => '1']);
        $this->expectRedirect($this->app->handle($request), $this->routeparser->urlFor('history'));
        $this->assertNull($this->session->history_filter->action_filter);

        $request = $this->createRequest(
            route_name: 'history',
            route_args: ['option' => 'page', 'value' => '2'],
            method: 'GET',
            content_type: 'text/html',
            query_params: ['nbshow' => '10']
        );
        $this->expectOK($this->app->handle($request));
        $this->assertSame(2, $this->session->history_filter->current_page);
        $this->assertSame(10, $this->session->history_filter->show);

        $request = $this->createRequest('history', ['option' => 'order', 'value' => (string)HistoryList::ORDERBY_ACTION]);
        $this->expectOK($this->app->handle($request));
        $this->assertEquals(HistoryList::ORDERBY_ACTION, $this->session->history_filter->orderby);
        $this->assertSame('DESC', $this->session->history_filter->getDirection());
        //ordering again inverts direction
        $this->expectOK($this->app->handle($request));
        $this->assertSame('ASC', $this->session->history_filter->getDirection());
    }

    /**
     * Flush logs
     */
    public function testFlush(): void
    {
        $this->logSuperAdmin();
        $this->history->add('Test history entry');

        $test_response = $this->app->handle($this->createRequest('flushHistory'));
        $this->expectOK($test_response);
        $this->assertStringContainsString('Flush the logs', (string)$test_response->getBody());

        $request = $this->createRequest('doFlushHistory', [], 'POST');
        $this->expectRedirect(
            $this->app->handle($request->withParsedBody([])),
            $this->routeparser->urlFor('slash'),
            ['error_detected' => ['Removal has not been confirmed!']]
        );
        $this->assertContains('Test history entry', $this->getActions());

        $this->expectRedirect(
            $this->app->handle($request->withParsedBody([
                'confirm' => '1',
                'redirect_uri' => $this->routeparser->urlFor('history')
            ])),
            $this->routeparser->urlFor('history'),
            ['success_detected' => ['Logs have been flushed!']]
        );
        //only the flush is logged
        $this->assertSame(['Logs flushed'], $this->getActions());

        //ajax call
        $test_response = $this->app->handle($request->withParsedBody(['confirm' => '1', 'ajax' => 'true']));
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertSame(['success' => true], json_decode((string)$test_response->getBody(), associative: true));
        $this->expectFlashData(['success_detected' => ['Logs have been flushed!']]);
    }
}
