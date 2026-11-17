<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Util;

use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;

use function Safe\file_get_contents;
use function Safe\ini_get;
use function Safe\preg_replace;

/**
 * Telemetry tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Telemetry extends GaletteTestCase
{
    /**
     * Test Galette infos
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGrabGaletteInfos(): void
    {
        $this->plugins = new \Galette\Core\Plugins();
        $expected = [
            'uuid'               => 'TO BE SET',
            'version'            => GALETTE_VERSION,
            'plugins'            => [],
            'default_language'   => \Galette\Core\I18n::DEFAULT_LANG,
            'usage'              => [
                'avg_members'       => '0-50',
                'avg_contributions' => '0-50',
                'avg_transactions'  => '0-50'
            ]
        ];

        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );

        $result = $telemetry->grabGaletteInfos();
        $this->assertSame(40, strlen($result['uuid']));
        $expected['uuid'] = $result['uuid'];
        $this->assertSame($expected, $result);

        $this->plugins
            ->setContainer($this->container)
            ->loadModules($this->preferences, GALETTE_PLUGINS_PATH);
        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );

        $plugins_list = $this->plugins->getModules();
        foreach ($plugins_list as $plugin) {
            $expected['plugins'][] = [
                'key'       => $plugin['name'],
                'version'   => $plugin['version']
            ];
        }

        $result = $telemetry->grabGaletteInfos();
        $this->assertSame($expected, $result);

        $telemetry = $this->getMockBuilder(\Galette\Util\Telemetry::class)
            ->setConstructorArgs([$this->zdb, $this->preferences, $this->plugins])
            ->onlyMethods(['getCount'])
            ->getMock();
        $telemetry->method('getCount')
            ->willReturnCallback(
                fn($table) => match ($table) {
                    \Galette\Entity\Adherent::TABLE => 56,
                    \Galette\Entity\Contribution::TABLE => 402,
                    \Galette\Entity\Transaction::TABLE => 100,
                    default => 0,
                }
            );
        $result = $telemetry->grabGaletteInfos();

        $expected['usage']['avg_members'] = '50-250';
        $expected['usage']['avg_contributions'] = '250-500';
        $expected['usage']['avg_transactions'] = '50-250';
        $this->assertSame($expected, $result);
    }

    /**
     * Test DB infos
     */
    public function testGrabDbInfos(): void
    {
        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );

        $infos  = $telemetry->grabDbInfos();

        $this->assertNotEmpty($infos['engine']);
        $this->assertNotEmpty($infos['version']);
        $this->assertNotNull($infos['size']);
        if ($this->zdb->isPostgres()) {
            $this->assertSame('PostgreSQL', $infos['engine']);
        } else {
            $this->assertContains($infos['engine'], ['MariaDB', 'MySQL', 'Percona Server']);
            //no sql mode for postgres databases
            $this->assertNotEmpty($infos['sql_mode']);
        }
    }

    /**
     * MySQL families provider
     *
     * @return array<string, array<int, string>>
     */
    public static function mysqlFamilyProvider(): array
    {
        return [
            'MariaDB, packaged' => ['Debian 12', '10.11.6-MariaDB-0+deb12u1', 'MariaDB'],
            'MariaDB, upstream' => ['mariadb.org binary distribution', '11.4.3-MariaDB', 'MariaDB'],
            'MariaDB, official' => ['MariaDB Server', '10.6.18-MariaDB', 'MariaDB'],
            'Percona' => ["Percona Server (GPL), Release '37', Revision '39e2'", '8.0.37-29', 'Percona Server'],
            'MySQL, community' => ['MySQL Community Server - GPL', '8.0.39', 'MySQL'],
            'MySQL, packaged' => ['(Ubuntu)', '8.0.39-0ubuntu0.22.04.1', 'MySQL'],
            'MariaDB 11.8' => [
                '-- Please help get to 10k stars at https://github.',
                '11.8.6-MariaDB-0+deb13u1 from Debian',
                'MariaDB'
            ],
        ];
    }

    /**
     * Test MySQL family
     */
    #[DataProvider('mysqlFamilyProvider')]
    public function testGetMysqlFamily(string $comment, string $version, string $expected): void
    {
        $this->assertSame($expected, \Galette\Util\Telemetry::getMysqlFamily($comment, $version));
    }

    /**
     * Test web server infos
     */
    public function testGrabWebserverInfos(): void
    {
        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );
        $result = $telemetry->grabWebserverInfos();

        $this->assertSame(['engine', 'version'], array_keys($result));
        //no webserver infos from CLI
        $this->assertEmpty($result['engine']);
        $this->assertEmpty($result['version']);

        //web server information from a web request
        $_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4.62 (Fedora Linux) OpenSSL/3.2.2';
        try {
            $result = $telemetry->grabWebserverInfos();
        } finally {
            unset($_SERVER['SERVER_SOFTWARE']);
        }
        $this->assertSame(['engine' => 'Apache', 'version' => '2.4.62'], $result);
    }

    /**
     * Server software provider
     *
     * @return array<string, array<int, string|array<string, string>>>
     */
    public static function serverSoftwareProvider(): array
    {
        return [
            'Apache' => ['Apache/2.4.62 (Fedora Linux) OpenSSL/3.2.2', ['engine' => 'Apache', 'version' => '2.4.62']],
            'nginx' => ['nginx/1.26.2', ['engine' => 'nginx', 'version' => '1.26.2']],
            'No version' => ['Apache', ['engine' => 'Apache', 'version' => '']],
            'Built-in server' => ['PHP 8.3.0 Development Server', ['engine' => 'PHP', 'version' => '']],
            'Empty' => ['', ['engine' => '', 'version' => '']],
        ];
    }

    /**
     * Test server software parsing
     *
     * @param string                $software Server software string
     * @param array<string, string> $expected Expected result
     */
    #[DataProvider('serverSoftwareProvider')]
    public function testParseServerSoftware(string $software, array $expected): void
    {
        $this->assertSame($expected, \Galette\Util\Telemetry::parseServerSoftware($software));
    }

    /**
     * Test PHP infos
     */
    public function testGrabPhpInfos(): void
    {
        $expected = [
            'version'   => str_replace(PHP_EXTRA_VERSION, '', PHP_VERSION),
            'modules'   => get_loaded_extensions(),
            'setup'     => [
                'max_execution_time'    => ini_get('max_execution_time'),
                'memory_limit'          => ini_get('memory_limit'),
                'post_max_size'         => ini_get('post_max_size'),
                'session'               => ini_get('session.save_handler'),
                'upload_max_filesize'   => ini_get('upload_max_filesize'),
                'max_input_vars'        => ini_get('max_input_vars')
            ]
        ];

        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );
        $result = $telemetry->grabPhpInfos();
        $this->assertSame($expected, $result);
    }

    /**
     * Test OS infos
     */
    public function testGrabOsInfos(): void
    {
        $distro = '';
        if (file_exists('/etc/redhat-release')) {
            $distro = preg_replace('/\s+$/S', '', file_get_contents('/etc/redhat-release'));
        }
        if (file_exists('/etc/fedora-release')) {
            $distro = preg_replace('/\s+$/S', '', file_get_contents('/etc/fedora-release'));
        }
        if ($distro === '') {
            foreach (['/etc/os-release', '/usr/lib/os-release'] as $os_release) {
                if (is_readable($os_release)) {
                    $distro = \Galette\Util\Telemetry::parseOsRelease(file_get_contents($os_release));
                    break;
                }
            }
        }

        $expected = [
            'family'       => php_uname('s'),
            'distribution' => $distro,
            'version'      => php_uname('r')
        ];

        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );
        $result = $telemetry->grabOsInfos();
        $this->assertSame($expected, $result);
    }

    /**
     * os-release contents provider
     *
     * @return array<string, array<int, string>>
     */
    public static function osReleaseProvider(): array
    {
        return [
            'Debian' => [
                "PRETTY_NAME=\"Debian GNU/Linux 12 (bookworm)\"\nNAME=\"Debian GNU/Linux\"\nVERSION_ID=\"12\"\nID=debian\n",
                'Debian GNU/Linux 12 (bookworm)'
            ],
            'Alpine, no quotes' => [
                "NAME=\"Alpine Linux\"\nID=alpine\nVERSION_ID=3.20.3\nPRETTY_NAME=Alpine Linux v3.20\n",
                'Alpine Linux v3.20'
            ],
            'Single quotes' => ["PRETTY_NAME='Ubuntu 24.04.1 LTS'\n", 'Ubuntu 24.04.1 LTS'],
            'No pretty name' => ["NAME=\"Ubuntu\"\nVERSION=\"24.04.1 LTS (Noble Numbat)\"\n", 'Ubuntu 24.04.1 LTS (Noble Numbat)'],
            'Empty' => ['', ''],
        ];
    }

    /**
     * Test os-release parsing
     */
    #[DataProvider('osReleaseProvider')]
    public function testParseOsRelease(string $contents, string $expected): void
    {
        $this->assertSame($expected, \Galette\Util\Telemetry::parseOsRelease($contents));
    }

    /**
     * Test whole Telemetry infos
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetTelemetryInfos(): void
    {
        $this->plugins = $this->getMockBuilder(\Galette\Core\Plugins::class)
            ->onlyMethods(['getModules'])
            ->getMock();

        $this->plugins->method('getModules')->willReturn([
            'test_plugin' => [
                'name' => 'A test plugin',
                'version' => '1.0.0',
                'description' => 'A test plugin description',
                'author' => 'Test Author'
            ]
        ]);

        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );
        $result = $telemetry->getTelemetryInfos();

        $this->assertSame(
            array_keys($result),
            [
                'galette',
                'system'
            ]
        );

        $this->assertSame(
            array_keys($result['galette']),
            [
                'uuid',
                'version',
                'plugins',
                'default_language',
                'usage'
            ]
        );

        $this->assertSame(
            $result['galette']['plugins'][0],
            [
                'key' => 'A test plugin',
                'version' => '1.0.0'
            ]
        );

        $this->assertSame(
            array_keys($result['system']),
            [
                'db',
                'web_server',
                'php',
                'os'
            ]
        );
    }
}
