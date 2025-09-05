<?php

/**
 * Copyright © 2003-2025 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Galette\Core\test\units;

use atoum;
use PHPUnit\Framework\TestCase;

/**
 * DB fail tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Install extends TestCase
{
    private \Galette\Core\Db $zdb;
    /** @var array<string> */
    private readonly array $flash_data;
    private \Slim\Flash\Messages $flash;
    private \DI\Container $container;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        setlocale(LC_ALL, 'en_US');

        $flash_data = [];
        $this->flash_data = &$flash_data;
        $this->flash = new \Slim\Flash\Messages($flash_data);

        $gapp =  new \Galette\Core\SlimApp();
        $app = $gapp->getApp();
        $plugins = new \Galette\Core\Plugins();
        require GALETTE_BASE_PATH . '/includes/dependencies.php';
        $container = $app->getContainer();
        $_SERVER['HTTP_HOST'] = '';

        $container->set('flash', $this->flash);
        $container->set(Slim\Flash\Messages::class, $this->flash);

        $this->container = $container;

        $this->zdb = $container->get('zdb');
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        if (TYPE_DB === 'mysql') {
            $this->assertSame([], $this->zdb->getWarnings());
        }
    }

    /**
     * Test if current database version is supported
     *
     * @return void
     */
    public function testDbSupport(): void
    {
        $this->assertFalse($this->zdb->isEngineSUpported());
    }

    /**
     * Test if current database version is supported
     *
     * @return void
     */
    public function testGetUnsupportedMessage(): void
    {
        $this->assertMatchesRegularExpression(
            '/Minimum version for .+ engine is .+, .+ .+ found!/',
            $this->zdb->getUnsupportedMessage()
        );
    }
}
