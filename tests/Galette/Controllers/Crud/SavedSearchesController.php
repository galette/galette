<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Entity\SavedSearch;
use Galette\Filters\AdvancedMembersList;
use Galette\Filters\MembersList;
use Galette\Filters\SavedSearchesList;
use Galette\Tests\GaletteRoutingTestCase;

use function Safe\json_decode;

/**
 * Saved searches controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SavedSearchesController extends GaletteRoutingTestCase
{
    protected int $seed = 20261031160204;

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        unset($this->session->members_filter, $this->session->savedsearch_filter);
        $this->zdb->execute($this->zdb->delete(SavedSearch::TABLE));
        $this->cleanMembers();
        parent::tearDown();
    }

    /**
     * Log in as member one
     */
    private function logMemberOne(): void
    {
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
    }

    /**
     * Get saved searches, by name
     *
     * @return array<string, \ArrayObject<string, mixed>>
     */
    private function getSearches(): array
    {
        $searches = [];
        foreach ($this->zdb->execute($this->zdb->select(SavedSearch::TABLE)) as $row) {
            $searches[(string)$row->name] = $row;
        }
        return $searches;
    }

    /**
     * Saved searches require a logged-in user
     */
    public function testAccess(): void
    {
        $requests = [
            $this->createRequest('searches'),
            $this->createRequest(
                route_name: 'saveSearch',
                route_args: [],
                method: 'GET',
                content_type: 'text/html',
                query_params: ['search_title' => 'Test search']
            ),
            $this->createRequest('editSearch', ['id' => '1']),
            $this->createRequest('loadSearch', ['id' => '1']),
        ];
        foreach ($requests as $request) {
            $this->expectLogin($this->app->handle($request));
        }
        $this->assertSame([], $this->getSearches());
    }

    /**
     * Save, list, edit and remove searches
     */
    public function testCrud(): void
    {
        $member = $this->getMemberOne();
        $this->logMemberOne();

        //save from members list
        $request = $this->createRequest(
            route_name: 'saveSearch',
            route_args: [],
            method: 'GET',
            content_type: 'text/html',
            query_params: ['search_title' => 'Test search', 'filter_str' => 'Test', 'nbshow' => '10', 'page' => '2']
        );
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('members'),
            ['success_detected' => ['Search has been saved.']]
        );

        //save current advanced search, from ajax
        $filters = new AdvancedMembersList();
        $filters->filter_str = 'Advanced';
        $this->session->members_filter = $filters;
        $request = $this->createRequest('saveSearch', [], 'POST')
            ->withParsedBody(['search_title' => 'Test advanced search', 'advanced_search' => '1']);
        $test_response = $this->app->handle($request);
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertSame(['success' => true], json_decode((string)$test_response->getBody(), associative: true));
        $this->expectFlashData(['success_detected' => ['Search has been saved.']]);

        $searches = $this->getSearches();
        $this->assertCount(2, $searches);
        $this->assertSame($member->id, (int)$searches['Test search']->id_adh);
        //pagination is not stored
        $this->assertSame(['filter_str' => 'Test'], json_decode($searches['Test search']->parameters, associative: true));
        $advanced = json_decode($searches['Test advanced search']->parameters, associative: true);
        $this->assertSame('Advanced', $advanced['filter_str']);
        $this->assertArrayHasKey('free_search', $advanced);
        $id = (int)$searches['Test search']->{SavedSearch::PK};
        $advanced_id = (int)$searches['Test advanced search']->{SavedSearch::PK};

        //list
        $test_response = $this->app->handle($this->createRequest('searches'));
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Test search', $body);
        $this->assertStringContainsString('Test advanced search', $body);
        //ordering again inverts direction
        $order_request = $this->createRequest(
            'searches',
            ['option' => 'order', 'value' => (string)SavedSearchesList::ORDERBY_DATE]
        );
        $test_response = $this->app->handle($order_request);
        $this->expectOK($test_response);
        $this->assertSame('ASC', $this->session->savedsearch_filter->getDirection());
        $this->expectOK($this->app->handle($order_request));
        $this->assertSame('DESC', $this->session->savedsearch_filter->getDirection());
        $test_response = $this->app->handle($this->createRequest('searches', ['option' => 'page', 'value' => '1']));
        $this->expectOK($test_response);

        //edit: name, then parameters from current search
        $test_response = $this->app->handle($this->createRequest('editSearch', ['id' => (string)$id]));
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('value="Test search"', $body);
        $this->assertStringContainsString('name="replace_parameters"', $body);

        $request = $this->createRequest('doEditSearch', ['id' => (string)$id], 'POST')
            ->withParsedBody(['name' => 'Test search renamed', 'replace_parameters' => '1']);
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('searches'),
            ['success_detected' => ['Search has been saved.']]
        );
        $searches = $this->getSearches();
        $this->assertArrayHasKey('Test search renamed', $searches);
        $this->assertSame(
            'Advanced',
            json_decode($searches['Test search renamed']->parameters, associative: true)['filter_str']
        );

        $request = $this->createRequest('doEditSearch', ['id' => (string)$id], 'POST')
            ->withParsedBody(['cancel' => '1']);
        $this->expectRedirect($this->app->handle($request), $this->routeparser->urlFor('searches'));

        //remove
        $test_response = $this->app->handle($this->createRequest('removeSearch', ['id' => (string)$id]));
        $this->expectOK($test_response);
        $this->assertStringContainsString('Remove saved search', (string)$test_response->getBody());

        $request = $this->createRequest('doRemoveSearch', ['id' => (string)$id], 'POST');
        $this->expectRedirect(
            $this->app->handle($request->withParsedBody([])),
            $this->routeparser->urlFor('searches'),
            ['error_detected' => ['Removal has not been confirmed!']]
        );
        $this->assertCount(2, $this->getSearches());

        $request = $this->createRequest('doRemoveSearch', [], 'POST')
            ->withParsedBody(['id' => [$id, $advanced_id], 'confirm' => '1']);
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('searches'),
            ['success_detected' => ['Successfully deleted!']]
        );
        $this->assertSame([], $this->getSearches());
    }

    /**
     * Members cannot edit nor remove searches of others
     */
    public function testOwnership(): void
    {
        $this->logSuperAdmin();
        $sco = new SavedSearch($this->zdb, $this->login);
        $this->assertTrue($sco->check(['form' => 'Adherent', 'name' => 'Test admin search', 'parameters' => []]));
        $this->assertTrue($sco->store());
        $this->login->logOut();

        $this->getMemberOne();
        $this->logMemberOne();

        //confirmation modal sends the identifier
        $request = $this->createRequest('doRemoveSearch', ['id' => (string)$sco->id], 'POST')
            ->withParsedBody(['id' => (string)$sco->id, 'confirm' => '1']);
        $this->expectRedirect($this->app->handle($request), $this->routeparser->urlFor('searches'));
        $this->expectLogEntry(\Analog\Analog::WARNING, 'not owned by current user');
        $this->assertArrayHasKey('Test admin search', $this->getSearches());

        $test_response = $this->app->handle($this->createRequest('editSearch', ['id' => (string)$sco->id]));
        $this->expectRedirect(
            $test_response,
            $this->routeparser->urlFor('searches'),
            ['error_detected' => ['Saved search does not exist.']]
        );
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Unable to load saved search #' . $sco->id);
    }

    /**
     * Saved search is loaded in members list
     */
    public function testLoad(): void
    {
        $this->getMemberOne();
        $this->logMemberOne();

        $sco = new SavedSearch($this->zdb, $this->login);
        $this->assertTrue($sco->check([
            'form' => 'Adherent',
            'name' => 'Test search',
            'parameters' => ['filter_str' => 'Test']
        ]));
        $this->assertTrue($sco->store());

        $this->expectRedirect(
            $this->app->handle($this->createRequest('loadSearch', ['id' => (string)$sco->id])),
            $this->routeparser->urlFor('members'),
            ['success_detected' => ['Saved search loaded']]
        );
        $filters = $this->session->members_filter;
        $this->assertInstanceOf(MembersList::class, $filters);
        $this->assertNotInstanceOf(AdvancedMembersList::class, $filters);
        $this->assertSame('Test', $filters->filter_str);

        //pagination stored by previous versions is ignored
        $update = $this->zdb->update(SavedSearch::TABLE);
        $update->set(['parameters' => '{"filter_str":"Test","nbshow":"10","page":"2"}'])
            ->where([SavedSearch::PK => $sco->id]);
        $this->zdb->execute($update);
        $this->expectRedirect(
            $this->app->handle($this->createRequest('loadSearch', ['id' => (string)$sco->id])),
            $this->routeparser->urlFor('members'),
            ['success_detected' => ['Saved search loaded']]
        );
        $this->assertSame('Test', $this->session->members_filter->filter_str);

        //removed group is reported
        $sco = new SavedSearch($this->zdb, $this->login);
        $this->assertTrue($sco->check([
            'form' => 'Adherent',
            'name' => 'Test search with group',
            'parameters' => ['filter_str' => 'Test', 'group_filter' => 999999]
        ]));
        $this->assertTrue($sco->store());

        $this->expectRedirect(
            $this->app->handle($this->createRequest('loadSearch', ['id' => (string)$sco->id])),
            $this->routeparser->urlFor('members'),
            [
                'warning_detected' => [
                    'Saved search loaded, but some of its criteria have been ignored: group #999999 no longer exists'
                ]
            ]
        );
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Group #999999 does not exists!');

        //unknown search
        $this->expectRedirect(
            $this->app->handle($this->createRequest('loadSearch', ['id' => '999999'])),
            $this->routeparser->urlFor('searches'),
            ['error_detected' => ['Saved search does not exist.']]
        );
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Unable to load saved search #999999');
    }
}
