<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Tests\GaletteRoutingTestCase;

/**
 * Removal confirmation page tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ConfirmRemovalTest extends GaletteRoutingTestCase
{
    protected int $seed = 20260927181512;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $this->login->logout();
        parent::tearDown();
    }

    /**
     * Render removal confirmation through the application, as controllers do
     *
     * @param array<string,mixed> $params Template parameters
     */
    private function renderConfirmation(array $params): string
    {
        $view = $this->view;
        $this->app->get(
            '/test/confirm-removal',
            fn($request, $response) => $view->render($response, 'modals/confirm_removal.html.twig', $params)
        )->setName('testConfirmRemoval');

        $test_response = $this->app->handle($this->createRequest('testConfirmRemoval'));
        $this->assertSame(200, $test_response->getStatusCode());
        return (string)$test_response->getBody();
    }

    /**
     * Get confirmation parameters
     *
     * @param string $mode    Either 'ajax' or '' for a full page
     * @param bool   $message With an extra message
     *
     * @return array<string,mixed>
     */
    private function getParams(string $mode, bool $message = true): array
    {
        $params = [
            'mode'          => $mode,
            'page_title'    => 'Remove something',
            'form_url'      => '/remove',
            'cancel_uri'    => '/list',
            'data'          => ['id' => 1]
        ];
        if ($message) {
            $params['message'] = 'Related data will be removed as well.';
        }
        return $params;
    }

    /**
     * Extra message is displayed on full pages, where Fomantic hides warning messages of forms not in warning state
     */
    public function testMessageIsDisplayed(): void
    {
        $this->logSuperAdmin();

        $html = $this->renderConfirmation($this->getParams(''));
        $this->assertMatchesRegularExpression(
            '/<form [^>]*class="ui form warning">.*<div class="ui warning message">\s*<p>Related data will be removed as well\.<\/p>/s',
            $html
        );
    }

    /**
     * Without message, form is not in warning state; modals display message as a paragraph
     */
    public function testNoWarningState(): void
    {
        $this->logSuperAdmin();

        $html = $this->renderConfirmation($this->getParams('', message: false));
        $this->assertStringContainsString('<form action="/remove" method="post" class="ui form">', $html);
    }

    /**
     * Modals display message as a paragraph
     */
    public function testModalMessage(): void
    {
        $this->logSuperAdmin();

        $html = $this->renderConfirmation($this->getParams('ajax'));
        $this->assertStringContainsString('<p>Related data will be removed as well.</p>', $html);
        $this->assertStringNotContainsString('warning', $html);
    }
}
