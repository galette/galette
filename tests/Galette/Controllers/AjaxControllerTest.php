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
* Galette ajax controller tests
*
* @author Johan Cwiklinski <johan@x-tnd.be>
*/
class AjaxControllerTest extends GaletteRoutingTestCase
{
    protected int $seed = 20250802103040;

    /**
     * Test news route
     */
    public function testNews(): void
    {
        $request = $this->createRequest('ajaxNews');

        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //superadmin gets Galette news, from the test feed
        $this->logSuperAdmin();

        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Galette news', $body);
        $this->assertStringContainsString('Galette 1.0.0rc1', $body);

        //the fragment is not a full page
        $this->assertStringNotContainsString('<body', $body);
    }
}
