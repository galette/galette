<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Core\GaletteMail;
use Galette\Tests\GaletteRoutingTestCase;

/**
 * Mailings controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MailingsController extends GaletteRoutingTestCase
{
    protected int $seed = 20260915103000;

    /**
     * Test the mailing form is still reachable once a mailing has been sent
     *
     * Sending a mailing used to leave the members filters at null in the
     * session; the key was still there, so the form took the null for a
     * filters instance.
     */
    public function testAddAfterSentMailing(): void
    {
        $mail_method = $this->preferences->pref_mail_method;
        $this->preferences->pref_mail_method = GaletteMail::METHOD_PHPMAIL;

        $controller = new \Galette\Controllers\Crud\MailingsController($this->container);
        $filter_name = $controller->getFilterName($controller->getDefaultFilterName());

        $this->logSuperAdmin();
        $this->session->$filter_name = null;

        $request = $this->createRequest('mailing', query_params: ['mailing_new' => '1']);
        $test_response = $this->app->handle($request);

        $this->preferences->pref_mail_method = $mail_method;
        $this->expectOK($test_response);
        $this->assertStringContainsString('name="mailing_objet"', (string)$test_response->getBody());
    }
}
