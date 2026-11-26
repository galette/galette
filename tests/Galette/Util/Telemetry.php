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
use function Safe\mkdir;
use function Safe\rmdir;
use function Safe\touch;
use function Safe\unlink;
use function Safe\ini_get;
use function Safe\preg_match;
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
                'avg_transactions'  => '0-50',
                'groups'            => '0',
                'dynamic_fields'    => ['adh' => '0', 'contrib' => '0', 'trans' => '0'],
                'saved_searches'    => '0',
                'mailings'          => '0',
            ],
            'features'           => 'TO BE SET',
            'install_mode'       => 'TO BE SET'
        ];

        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );
        $expected['features'] = $telemetry->grabFeaturesInfos();
        $expected['install_mode'] = $telemetry->getInstallMode();

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
                function (string $table, ?\Closure $where = null): int {
                    if ($table === \Galette\DynamicFields\DynamicField::TABLE) {
                        //each form is counted on its own
                        $this->assertNotNull($where);
                        $select = $this->zdb->select($table);
                        $select->where($where);
                        $sql = $this->zdb->sql->buildSqlString($select);
                        $form = preg_match('/field_form\W+\s*=\s*\'(\w+)\'/', $sql, $matches) ? $matches[1] : null;
                        return match ($form) {
                            'adh' => 3,
                            'contrib' => 7,
                            default => 0,
                        };
                    }
                    return match ($table) {
                        \Galette\Entity\Adherent::TABLE => 56,
                        \Galette\Entity\Contribution::TABLE => 402,
                        \Galette\Entity\Transaction::TABLE => 100,
                        \Galette\Entity\Group::TABLE => 3,
                        \Galette\Entity\SavedSearch::TABLE => 21,
                        \Galette\Core\MailingHistory::TABLE => 51,
                        default => 0,
                    };
                }
            );
        $result = $telemetry->grabGaletteInfos();

        $expected['usage']['avg_members'] = '50-250';
        $expected['usage']['avg_contributions'] = '250-500';
        $expected['usage']['avg_transactions'] = '50-250';
        $expected['usage']['groups'] = '1-5';
        $expected['usage']['dynamic_fields'] = ['adh' => '1-5', 'contrib' => '6-20', 'trans' => '0'];
        $expected['usage']['saved_searches'] = '21-50';
        $expected['usage']['mailings'] = '50+';
        $this->assertSame($expected, $result);
    }

    /**
     * Only mailings sent during the last year are counted
     */
    public function testMailingsUsage(): void
    {
        $this->zdb->execute($this->zdb->delete(\Galette\Core\MailingHistory::TABLE));

        $insert = function (bool $sent, string $interval): void {
            $insert = $this->zdb->insert(\Galette\Core\MailingHistory::TABLE);
            $insert->values([
                'mailing_subject'       => 'Telemetry test',
                'mailing_body'          => 'Telemetry test',
                'mailing_date'          => (new \Safe\DateTime())->sub(new \DateInterval($interval))->format('Y-m-d H:i:s'),
                'mailing_recipients'    => '[]',
                'mailing_sent'          => $sent ? 1 : 0,
            ]);
            $this->zdb->execute($insert);
        };

        try {
            $telemetry = new \Galette\Util\Telemetry(
                $this->zdb,
                $this->preferences,
                $this->plugins
            );

            //neither a draft nor a mailing sent more than a year ago is counted
            $insert(sent: false, interval: 'P1D');
            $insert(sent: true, interval: 'P2Y');
            $this->assertSame('0', $telemetry->grabGaletteInfos()['usage']['mailings']);

            $insert(sent: true, interval: 'P1D');
            $this->assertSame('1-5', $telemetry->grabGaletteInfos()['usage']['mailings']);
        } finally {
            $this->zdb->execute($this->zdb->delete(\Galette\Core\MailingHistory::TABLE));
        }
    }

    /**
     * Test small counts ranges
     */
    public function testGetRange(): void
    {
        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );

        $ranges = [];
        foreach ([0, 1, 5, 6, 20, 21, 50, 51] as $count) {
            $ranges[$count] = $telemetry->getRange($count);
        }
        $this->assertSame(
            [0 => '0', 1 => '1-5', 5 => '1-5', 6 => '6-20', 20 => '6-20', 21 => '21-50', 50 => '21-50', 51 => '50+'],
            $ranges
        );
    }

    /**
     * Test features infos
     */
    public function testGrabFeaturesInfos(): void
    {
        $this->preferences->pref_bool_publicpages = false;
        $this->preferences->pref_bool_selfsubscribe = true;
        $this->preferences->pref_editor_enabled = true;
        $this->preferences->pref_enable_custom_colors = false;
        $this->preferences->pref_maps_enabled = true;
        $this->preferences->pref_mail_method = \Galette\Core\GaletteMail::METHOD_SMTP;
        $this->preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;
        $this->preferences->pref_password_strength = \Galette\Enums\PasswordStrength::VeryStrong->value;

        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );

        $expected = [
            'public_pages'      => false,
            'self_subscription' => true,
            'html_editor'       => true,
            'custom_colors'     => false,
            'maps'              => true,
            'mail_method'       => 'smtp',
            'two_factor'        => 'optional',
            'password_strength' => 'very_strong',
            'feature_flags'     => [],
        ];
        $this->assertSame($expected, $telemetry->grabFeaturesInfos());

        //only flags Galette knows are sent
        $this->assertTrue($this->preferences->storeFeatureFlags(['two-factor-required', 'not-a-flag']));
        try {
            $this->assertSame(['two-factor-required'], $telemetry->getFeatureFlags());
            $this->expectLogEntry(
                \Analog\Analog::WARNING,
                'Feature flag "not-a-flag" is stored as enabled but is not registered; it is ignored.'
            );
        } finally {
            $this->preferences->storeFeatureFlags([]);
        }
    }

    /**
     * A flag whose dependencies are not turned on is not sent
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testFeatureFlagsDependencies(): void
    {
        $manager = $this->getMockBuilder(\Galette\Core\FeatureFlagManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConfiguration', 'getDeclarations', 'getStoredDeclarations', 'isLockedByConstant'])
            ->getMock();
        $manager->method('getConfiguration')->willReturn(include GALETTE_TESTS_PATH . '/fixtures/feature_flags.inc.php');
        //api-v2 requires oauth2, which is not turned on
        $manager->method('getDeclarations')->willReturn(['acls', 'api-v2']);
        $manager->method('getStoredDeclarations')->willReturn([]);
        $manager->method('isLockedByConstant')->willReturn(true);
        $manager->load();
        $this->assertSame(['acls', 'api-v2'], $manager->getDeclaredFlags());

        $telemetry = $this->getMockBuilder(\Galette\Util\Telemetry::class)
            ->setConstructorArgs([$this->zdb, $this->preferences, $this->plugins])
            ->onlyMethods(['getFeatureFlagManager'])
            ->getMock();
        $telemetry->method('getFeatureFlagManager')->willReturn($manager);

        $this->assertSame(['acls'], $telemetry->getFeatureFlags());
    }

    /**
     * Test HTTPS detection
     */
    public function testIsHttps(): void
    {
        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );

        //no request nor URL
        $this->assertNull($telemetry->isHttps());

        $_SERVER['HTTP_HOST'] = 'galette.test';
        try {
            $this->assertFalse($telemetry->isHttps());
            $_SERVER['HTTPS'] = 'off';
            $this->assertFalse($telemetry->isHttps());
            $_SERVER['HTTPS'] = 'on';
            $this->assertTrue($telemetry->isHttps());
        } finally {
            $_SERVER['HTTP_HOST'] = '';
            unset($_SERVER['HTTPS']);
        }
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
            'sapi'      => 'cli',
            'opcache'   => extension_loaded('Zend OPcache') && (bool)ini_get('opcache.enable_cli'),
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
                'usage',
                'features',
                'install_mode'
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
                'os',
                'timezone'
            ]
        );
        $this->assertSame(date_default_timezone_get(), $result['system']['timezone']);
    }

    /**
     * Test install mode
     */
    public function testGetInstallMode(): void
    {
        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );

        $root = sys_get_temp_dir() . '/galette-install-mode-' . uniqid() . '/';
        mkdir($root);
        $container_file = $root . '.dockerenv';
        try {
            $this->assertSame(
                \Galette\Util\Telemetry::INSTALL_ARCHIVE,
                $telemetry->getInstallMode($root, [$container_file])
            );

            mkdir($root . '.git');
            $this->assertSame(
                \Galette\Util\Telemetry::INSTALL_GIT,
                $telemetry->getInstallMode($root, [$container_file])
            );

            touch($container_file);
            $this->assertSame(
                \Galette\Util\Telemetry::INSTALL_CONTAINER,
                $telemetry->getInstallMode($root, [$container_file])
            );
        } finally {
            if (file_exists($container_file)) {
                unlink($container_file);
            }
            if (is_dir($root . '.git')) {
                rmdir($root . '.git');
            }
            rmdir($root);
        }
    }

    /**
     * Test sent data
     */
    public function testGetPayload(): void
    {
        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );

        $payload = $telemetry->getPayload(\Galette\Util\Telemetry::TRIGGER_AUTO);
        $this->assertSame(['data'], array_keys($payload));
        $this->assertSame(['galette', 'system'], array_keys($payload['data']));
        $this->assertSame(\Galette\Util\Telemetry::TRIGGER_AUTO, $payload['data']['galette']['trigger']);
        $this->assertSame($telemetry->getInstallMode(), $payload['data']['galette']['install_mode']);

        $payload = $telemetry->getPayload(\Galette\Util\Telemetry::TRIGGER_MANUAL);
        $this->assertSame(\Galette\Util\Telemetry::TRIGGER_MANUAL, $payload['data']['galette']['trigger']);

        $this->expectException(\InvalidArgumentException::class);
        $telemetry->getPayload('unknown');
    }

    /**
     * Test registration check with the telemetry server
     */
    public function testCheckRegistration(): void
    {
        try {
            //form not sent on the telemetry server
            $telemetry = $this->registrationTelemetry(answer: false);
            $this->assertFalse($telemetry->checkRegistration());
            $this->assertFalse($telemetry->isRegistered());

            //telemetry server unreachable or not up to date
            $telemetry = $this->registrationTelemetry(new \RuntimeException('Unexpected registration status answer'));
            $this->assertFalse($telemetry->checkRegistration());
            $this->assertFalse($telemetry->isRegistered());

            //form sent, but the date cannot be stored: not registered, the next check retries
            $preferences = $this->getMockBuilder(\Galette\Core\Preferences::class)
                ->setConstructorArgs([$this->zdb])
                ->onlyMethods(['updateRegistrationDate'])
                ->getMock();
            $preferences->expects($this->once())->method('updateRegistrationDate')->willReturn(false);
            $telemetry = $this->registrationTelemetry(answer: true, preferences: $preferences);
            $this->assertFalse($telemetry->checkRegistration());
            $this->assertFalse($telemetry->isRegistered());

            //form sent: registration is recorded
            $telemetry = $this->registrationTelemetry(answer: true);
            $this->assertTrue($telemetry->checkRegistration());
            $this->assertTrue($telemetry->isRegistered());
            $this->assertStringStartsWith(date('Y-m-d'), $this->preferences->pref_registration_date);

            //already registered: the server is not asked anymore
            $telemetry = $this->registrationTelemetry(answer: null);
            $this->assertTrue($telemetry->checkRegistration());
        } finally {
            $this->resetRegistration();
        }
    }

    /**
     * Telemetry, with a mocked answer of the telemetry server
     *
     * @param bool|\Throwable|null       $answer      Answer, exception thrown, or null if the server must not be asked
     * @param ?\Galette\Core\Preferences $preferences Preferences, the ones of the test case if null
     */
    private function registrationTelemetry(
        bool|\Throwable|null $answer,
        ?\Galette\Core\Preferences $preferences = null
    ): \Galette\Util\Telemetry {
        $telemetry = $this->getMockBuilder(\Galette\Util\Telemetry::class)
            ->setConstructorArgs([$this->zdb, $preferences ?? $this->preferences, $this->plugins])
            ->onlyMethods(['requestRegistrationStatus'])
            ->getMock();

        $expects = $telemetry->expects($answer === null ? $this->never() : $this->once())
            ->method('requestRegistrationStatus');
        if ($answer instanceof \Throwable) {
            $expects->willThrowException($answer);
        } elseif ($answer !== null) {
            $expects->willReturn($answer);
        }

        return $telemetry;
    }

    /**
     * Remove registration date
     */
    private function resetRegistration(): void
    {
        $update = $this->zdb->update(\Galette\Core\Preferences::TABLE);
        $update->set(['val_pref' => ''])->where(['nom_pref' => 'pref_registration_date']);
        $this->zdb->execute($update);
        $this->preferences->load();
    }
}
