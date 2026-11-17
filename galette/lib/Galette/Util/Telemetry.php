<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Util;

use Analog\Analog;
use DateInterval;
use Exception;
use Galette\Core\Db;
use Galette\Core\Preferences;
use Galette\Core\Plugins;
use Laminas\Db\Sql\Expression;
use RuntimeException;
use Safe\DateTime;

use function Safe\curl_exec;
use function Safe\curl_getinfo;
use function Safe\curl_init;
use function Safe\curl_setopt;
use function Safe\file_get_contents;
use function Safe\ini_get;
use function Safe\json_encode;
use function Safe\parse_url;
use function Safe\preg_match;
use function Safe\preg_replace;

/**
 * Handle Telemetry data
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017 GLPI and Contributors
 * @copyright 2017-2026 The Galette Team
 */
class Telemetry
{
    public const string TRIGGER_AUTO = 'auto';
    public const string TRIGGER_MANUAL = 'manual';

    public const string INSTALL_CONTAINER = 'container';
    public const string INSTALL_GIT = 'git';
    public const string INSTALL_ARCHIVE = 'archive';

    /**
     * Constructor
     *
     * @param Db          $zdb     Database instance
     * @param Preferences $prefs   Preferences instance
     * @param Plugins     $plugins Plugins instance
     */
    public function __construct(
        private readonly Db $zdb,
        private readonly Preferences $prefs,
        private readonly Plugins $plugins
    ) {
    }

    /**
     * Grab telemetry information
     *
     * @return array<string, array<string, string|array<string, string|array<string, string>>>>
     */
    public function getTelemetryInfos(): array
    {
        return [
            'galette'  => $this->grabGaletteInfos(),
            'system'   => [
                'db'           => $this->grabDbInfos(),
                'web_server'   => $this->grabWebserverInfos(),
                'php'          => $this->grabPhpInfos(),
                'os'           => $this->grabOsInfos()
            ]
        ];
    }

    /**
     * Grab Galette part information
     *
     * @return array<string, array<int<0, max>|string, array<string, mixed>|string>|string>
     */
    public function grabGaletteInfos(): array
    {
        $galette = [
            'uuid'               => $this->getInstanceUuid(),
            'version'            => GALETTE_VERSION,
            'plugins'            => [],
            'default_language'   => $this->prefs->pref_lang,
            'usage'              => [
                'avg_members'           => $this->getAverage(\Galette\Entity\Adherent::TABLE),
                'avg_contributions'     => $this->getAverage(\Galette\Entity\Contribution::TABLE),
                'avg_transactions'      => $this->getAverage(\Galette\Entity\Transaction::TABLE)
            ],
            'install_mode'       => $this->getInstallMode()
        ];

        $plugins = $this->plugins->getModules();
        foreach ($plugins as $plugin) {
            $galette['plugins'][] = [
                'key'       => $plugin['name'],
                'version'   => $plugin['version']
            ];
        }

        return $galette;
    }

    /**
     * Grab DB part information
     *
     * @return array<string, ?string>
     */
    public function grabDbInfos(): array
    {
        $infos = $this->zdb->getInfos();
        if (!$this->zdb->isPostgres()) {
            $infos['engine'] = self::getMysqlFamily(
                (string)$infos['engine'],
                (string)$infos['version']
            );
        }
        return $infos;
    }

    /**
     * Get MySQL server family
     *
     * MySQL engine is retrieved from @@version_comment, that is a free text
     * set by packagers (ie. "Debian 12", "Source distribution").
     *
     * @param string $comment Version comment
     * @param string $version Server version
     */
    public static function getMysqlFamily(string $comment, string $version): string
    {
        if (stripos($version, 'mariadb') !== false || stripos($comment, 'mariadb') !== false) {
            return 'MariaDB';
        }
        if (stripos($comment, 'percona') !== false) {
            return 'Percona Server';
        }
        return 'MySQL';
    }

    /**
     * Grab web server part information
     *
     * @return array<string, string>
     */
    public function grabWebserverInfos(): array
    {
        $server = [
            'engine'  => '',
            'version' => '',
        ];

        // set by the web server when running from a web request
        if (!empty($_SERVER['SERVER_SOFTWARE'])) {
            return self::parseServerSoftware((string)$_SERVER['SERVER_SOFTWARE']);
        }

        if (PHP_SAPI == 'cli' || !filter_var(gethostbyname(parse_url($this->prefs->getURL(), PHP_URL_HOST)), FILTER_VALIDATE_IP)) {
            // Do not try to get headers if hostname cannot be resolved
            return $server;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->prefs->getURL());
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, value: true);
        curl_setopt($ch, CURLOPT_HEADER, value: true);

        // disable SSL certificate validation (wildcard, self-signed)
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, value: false);

        if ($response = curl_exec($ch)) {
            $headers = substr($response, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
            $header_matches = [];
            if (preg_match('/^Server: *(?<software>.+)$/im', $headers, $header_matches)) {
                $server = self::parseServerSoftware($header_matches['software']);
            }
        }

        return $server;
    }

    /**
     * Parse web server software string
     *
     * @param string $software Software string (ie. "Apache/2.4.62 (Fedora Linux)", "nginx")
     *
     * @return array<string, string>
     */
    public static function parseServerSoftware(string $software): array
    {
        $server = [
            'engine'  => '',
            'version' => '',
        ];

        $matches = [];
        if (preg_match('/^(?<engine>[^\/\s]+)(?:\/(?<version>\S+))?/', trim($software), $matches)) {
            $server['engine']  = $matches['engine'];
            $server['version'] = $matches['version'] ?? '';
        }

        return $server;
    }

    /**
     * Grab PHP part information
     *
     * @return array<string, array<int<0, max>|string, string|false>|string>
     */
    public function grabPhpInfos(): array
    {
        return [
            'version'   => str_replace(PHP_EXTRA_VERSION, '', PHP_VERSION),
            'modules'   => get_loaded_extensions(),
            'setup'     => [
                'max_execution_time'    => ini_get('max_execution_time'),
                'memory_limit'          => ini_get('memory_limit'),
                'post_max_size'         => ini_get('post_max_size'),
                'session'               => ini_get('session.save_handler'),
                'upload_max_filesize'   => ini_get('upload_max_filesize'),
                'max_input_vars'        => ini_get('max_input_vars'),
            ]
        ];
    }

    /**
     * Grab OS part information
     *
     * @return array<string, string>
     */
    public function grabOsInfos(): array
    {
        $distro = false;
        if (@file_exists('/etc/redhat-release')) {
            $distro = preg_replace('/\s+$/S', '', file_get_contents('/etc/redhat-release'));
        }
        if (@file_exists('/etc/fedora-release')) {
            $distro = preg_replace('/\s+$/S', '', file_get_contents('/etc/fedora-release'));
        }
        if (!$distro) {
            foreach (['/etc/os-release', '/usr/lib/os-release'] as $os_release) {
                if (@is_readable($os_release)) {
                    $distro = self::parseOsRelease(file_get_contents($os_release));
                    break;
                }
            }
        }

        return [
            'family'       => php_uname('s'),
            'distribution' => ($distro ?: ''),
            'version'      => php_uname('r')
        ];
    }

    /**
     * Get distribution name from os-release file contents
     *
     * @param string $contents os-release file contents
     */
    public static function parseOsRelease(string $contents): string
    {
        $values = [];
        foreach (explode("\n", $contents) as $line) {
            $matches = [];
            if (preg_match('/^\s*(?<key>[A-Z_]+)=(?<value>.*)$/', $line, $matches)) {
                $values[$matches['key']] = trim(trim($matches['value']), '"\'');
            }
        }

        if (!empty($values['PRETTY_NAME'])) {
            return $values['PRETTY_NAME'];
        }
        return trim(($values['NAME'] ?? '') . ' ' . ($values['VERSION'] ?? ''));
    }

    /**
     * Count
     *
     * @param string $table Table to query
     */
    public function getCount(string $table): int
    {
        $select = $this->zdb->select($table);
        $select->columns([
            'cnt' => new Expression(
                'COUNT(1)'
            )
        ]);
        $results = $this->zdb->execute($select);
        $result = $results->current();
        return (int)$result->cnt;
    }

    /**
     * Calculate average parts
     *
     * @param string $table Table to query
     */
    private function getAverage(string $table): string
    {
        $count = $this->getCount($table);

        if ($count <= 50) {
            return '0-50';
        } elseif ($count <= 250) {
            return '50-250';
        } elseif ($count <= 500) {
            return '250-500';
        } elseif ($count <= 1000) {
            return '500-1000';
        } elseif ($count <= 5000) {
            return '1000-5000';
        }
        return '5000+';
    }

    /**
     * Get how Galette has been installed
     *
     * @param string       $root            Galette root directory, parent of the galette directory
     * @param list<string> $container_files Files present in containers (Docker, Podman)
     *
     * @return string One of INSTALL_* constants
     */
    public function getInstallMode(
        string $root = GALETTE_ROOT . '../',
        array $container_files = ['/.dockerenv', '/run/.containerenv']
    ): string {
        foreach ($container_files as $container_file) {
            if (@file_exists($container_file)) {
                return self::INSTALL_CONTAINER;
            }
        }
        if (@file_exists($root . '.git')) {
            return self::INSTALL_GIT;
        }
        return self::INSTALL_ARCHIVE;
    }

    /**
     * Get data to send
     *
     * @param string $trigger What triggered the sending, one of TRIGGER_* constants
     *
     * @return array<string, array<string, mixed>>
     */
    public function getPayload(string $trigger): array
    {
        if (!in_array($trigger, [self::TRIGGER_AUTO, self::TRIGGER_MANUAL], strict: true)) {
            throw new \InvalidArgumentException(sprintf('Unknown telemetry trigger "%s"', $trigger));
        }

        $data = $this->getTelemetryInfos();
        $data['galette']['trigger'] = $trigger;
        return ['data' => $data];
    }

    /**
     * Send telemetry information
     *
     * @param string $trigger What triggered the sending, one of TRIGGER_* constants
     */
    public function send(string $trigger = self::TRIGGER_MANUAL): void
    {
        $infos = json_encode($this->getPayload($trigger));

        $uri = GALETTE_TELEMETRY_URI . 'telemetry';
        $ch = curl_init($uri);
        $opts = [
            CURLOPT_URL             => $uri,
            CURLOPT_USERAGENT       => 'Galette/' . GALETTE_VERSION,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POSTFIELDS      => $infos,
            CURLOPT_HTTPHEADER      => ['Content-Type:application/json']
        ];

        curl_setopt_array($ch, $opts);
        $content = json_decode(json: curl_exec($ch), associative: null, depth: 512, flags: JSON_THROW_ON_ERROR);
        $errstr = curl_error($ch);

        if ($content && property_exists($content, 'message')) {
            if (property_exists($content, 'errors')) {
                $errors = '';
                foreach ($content->errors as $error) {
                    $errors .= "\n" . $error->property . ': ' . $error->message;
                }
                throw new RuntimeException($errors);
            }

            $this->prefs->updateTelemetryDate();
        } else {
            $message = 'Something went wrong sending telemetry information';
            if ($errstr != '') {
                $message .= ": $errstr";
            }
            Analog::log(
                $message,
                Analog::ERROR
            );
            throw new RuntimeException($message);
        }
    }

    /**
     * Get UUID
     *
     * @param string $type UUID type (either instance or registration)
     */
    private function getUuid(string $type): string
    {
        $param = 'pref_' . $type . '_uuid';
        $uuid = $this->prefs->$param;
        if (empty($uuid)) {
            $uuid = $this->prefs->generateUUID($type);
        }
        return $uuid;
    }

    /**
     * Get instance UUID
     */
    private function getInstanceUuid(): string
    {
        return $this->getUuid('instance');
    }

    /**
     * Get registration UUID
     */
    final public function getRegistrationUuid(): string
    {
        return $this->getUuid('registration');
    }

    /**
     * Get date telemetry has been sent
     */
    public function getSentDate(): string
    {
        return $this->prefs->pref_telemetry_date;
    }

    /**
     * Get date of registration
     */
    public function getRegistrationDate(): string
    {
        return $this->prefs->pref_registration_date;
    }

    /**
     * Does telemetry infos has been sent already?
     */
    public function isSent(): bool
    {
        return $this->getSentDate() != false;
    }

    /**
     * Is instance registered?
     */
    public function isRegistered(): bool
    {
        return $this->getRegistrationDate() != false;
    }

    /**
     * Should telemetry information sent again?
     *
     * @throws Exception
     */
    public function shouldRenew(): bool
    {
        $now = new DateTime();
        $sent = new DateTime($this->prefs->pref_telemetry_date);
        $sent->add(new DateInterval('P1Y'));
        // ask to resend telemetry after one year
        return $now > $sent && !isset($_COOKIE['renew_telemetry']);
    }
}
