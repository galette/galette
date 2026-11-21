<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Util;

use Analog\Analog;
use Closure;
use DateInterval;
use Exception;
use Galette\Core\Db;
use Galette\Core\FeatureFlagManager;
use Galette\Core\Logs;
use Galette\Core\GaletteMail;
use Galette\Core\MailingHistory;
use Galette\Core\Preferences;
use Galette\Core\Plugins;
use Galette\Core\TwoFactorAuth;
use Galette\DynamicFields\DynamicField;
use Galette\Entity\Group;
use Galette\Entity\SavedSearch;
use Galette\Enums\PasswordStrength;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Where;
use RuntimeException;
use Safe\DateTime;
use Throwable;

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

    /** Mail methods, by GaletteMail::METHOD_* value */
    private const array MAIL_METHODS = [
        GaletteMail::METHOD_DISABLED => 'disabled',
        GaletteMail::METHOD_PHPMAIL  => 'php',
        GaletteMail::METHOD_SMTP     => 'smtp',
        GaletteMail::METHOD_SENDMAIL => 'sendmail',
    ];

    /** Two-factor authentication policies, by TwoFactorAuth::MODE_* value */
    private const array TWO_FACTOR_MODES = [
        TwoFactorAuth::MODE_DISABLED       => 'disabled',
        TwoFactorAuth::MODE_OPTIONAL       => 'optional',
        TwoFactorAuth::MODE_REQUIRED_STAFF => 'required_staff',
        TwoFactorAuth::MODE_REQUIRED_ALL   => 'required_all',
    ];

    /** Dynamic fields forms */
    private const array DYNAMIC_FORMS = ['adh', 'contrib', 'trans'];

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
     * @return array<string, array<string, mixed>>
     */
    public function getTelemetryInfos(): array
    {
        $system = [
            'db'           => $this->grabDbInfos(),
            'web_server'   => $this->grabWebserverInfos(),
            'php'          => $this->grabPhpInfos(),
            'os'           => $this->grabOsInfos(),
            'timezone'     => date_default_timezone_get(),
        ];
        $https = $this->isHttps();
        if ($https !== null) {
            $system['https'] = $https;
        }

        return [
            'galette'  => $this->grabGaletteInfos(),
            'system'   => $system
        ];
    }

    /**
     * Grab Galette part information
     *
     * @return array<string, mixed>
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
                'avg_transactions'      => $this->getAverage(\Galette\Entity\Transaction::TABLE),
                'groups'                => $this->getRange($this->getCount(Group::TABLE)),
                'dynamic_fields'        => $this->getDynamicFieldsRanges(),
                'saved_searches'        => $this->getRange($this->getCount(SavedSearch::TABLE)),
                'mailings'              => $this->getRange($this->getCount(
                    MailingHistory::TABLE,
                    function (Where $where): void {
                        $where->equalTo('mailing_sent', right: true);
                        $where->greaterThanOrEqualTo(
                            'mailing_date',
                            (new DateTime())->sub(new DateInterval('P1Y'))->format('Y-m-d H:i:s')
                        );
                    }
                )),
            ],
            'features'           => $this->grabFeaturesInfos(),
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
     * Grab which features are used
     *
     * @return array<string, bool|string|list<string>>
     */
    public function grabFeaturesInfos(): array
    {
        $strength = PasswordStrength::tryFrom((int)$this->prefs->pref_password_strength);

        return [
            'public_pages'      => (bool)$this->prefs->pref_bool_publicpages,
            'self_subscription' => (bool)$this->prefs->pref_bool_selfsubscribe,
            'html_editor'       => (bool)$this->prefs->pref_editor_enabled,
            'custom_colors'     => (bool)$this->prefs->pref_enable_custom_colors,
            'maps'              => (bool)$this->prefs->pref_maps_enabled,
            'mail_method'       => self::MAIL_METHODS[(int)$this->prefs->pref_mail_method] ?? 'unknown',
            'two_factor'        => self::TWO_FACTOR_MODES[TwoFactorAuth::modeFrom($this->prefs)] ?? 'unknown',
            'password_strength' => $strength === null
                ? 'unknown'
                : strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $strength->name)),
            'feature_flags'     => $this->getFeatureFlags(),
        ];
    }

    /**
     * Experimental features turned on; only flags Galette knows are sent, and
     * a flag whose dependencies are not turned on is not
     *
     * @return list<string>
     */
    public function getFeatureFlags(): array
    {
        try {
            $flags = $this->getFeatureFlagManager();
        } catch (Throwable $e) {
            Logs::exception($e, 'Unable to load feature flags', Analog::INFO);
            return [];
        }

        $registry = $flags->getRegistryFlags();
        $enabled = [];
        foreach ($flags->getAllFlagsWithStatus() as $flag => $status) {
            if ($status['enabled'] && isset($registry[$flag])) {
                $enabled[] = $flag;
            }
        }
        sort($enabled);
        return $enabled;
    }

    /**
     * Feature flags manager
     */
    protected function getFeatureFlagManager(): FeatureFlagManager
    {
        return new FeatureFlagManager($this->prefs);
    }

    /**
     * Range of dynamic fields of each form
     *
     * @return array<string, string>
     */
    private function getDynamicFieldsRanges(): array
    {
        $ranges = [];
        foreach (self::DYNAMIC_FORMS as $form) {
            $ranges[$form] = $this->getRange($this->getCount(
                DynamicField::TABLE,
                function (Where $where) use ($form): void {
                    $where->equalTo('field_form', $form);
                }
            ));
        }
        return $ranges;
    }

    /**
     * Is Galette served over HTTPS?
     *
     * @return ?bool Null when unknown, ie. without URL set nor request
     */
    public function isHttps(): ?bool
    {
        if (empty($_SERVER['HTTP_HOST']) && !defined('GALETTE_URI') && empty($this->prefs->pref_galette_url)) {
            //no request nor URL to tell
            return null;
        }

        try {
            return str_starts_with(strtolower($this->prefs->getURL()), 'https://');
        } catch (Throwable) { // no log: the URL is unknown, telemetry is sent without it
            return null;
        }
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
     * @return array<string, array<int<0, max>|string, string|false>|string|bool>
     */
    public function grabPhpInfos(): array
    {
        return [
            'version'   => str_replace(PHP_EXTRA_VERSION, '', PHP_VERSION),
            'sapi'      => PHP_SAPI,
            'opcache'   => extension_loaded('Zend OPcache')
                && (bool)ini_get(PHP_SAPI === 'cli' ? 'opcache.enable_cli' : 'opcache.enable'),
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
     * @param string               $table Table to query
     * @param ?Closure(Where):void $where Restricts counted rows
     */
    public function getCount(string $table, ?Closure $where = null): int
    {
        $select = $this->zdb->select($table);
        $select->columns([
            'cnt' => new Expression(
                'COUNT(1)'
            )
        ]);
        if ($where !== null) {
            $select->where($where);
        }
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
     * Range of a small count (groups, dynamic fields...)
     *
     * @param int $count Count
     */
    public function getRange(int $count): string
    {
        return match (true) {
            $count === 0 => '0',
            $count <= 5 => '1-5',
            $count <= 20 => '6-20',
            $count <= 50 => '21-50',
            default => '50+',
        };
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
     * Check with the telemetry server whether the registration form has been sent,
     * and record the registration if so
     */
    public function checkRegistration(): bool
    {
        if ($this->isRegistered()) {
            return true;
        }

        try {
            $registered = $this->requestRegistrationStatus();
        } catch (Throwable $e) {
            Logs::exception($e, 'Unable to check registration', Analog::INFO);
            return false;
        }

        //not recorded, not registered: the page keeps checking, and the next check writes it again
        return $registered && $this->prefs->updateRegistrationDate();
    }

    /**
     * Ask the telemetry server whether the registration UUID has been registered
     *
     * @throws RuntimeException When the answer is not the expected one
     */
    protected function requestRegistrationStatus(): bool
    {
        $uri = GALETTE_TELEMETRY_URI . 'reference/status';
        $ch = curl_init($uri);
        curl_setopt_array($ch, [
            CURLOPT_USERAGENT       => 'Galette/' . GALETTE_VERSION,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_CONNECTTIMEOUT  => 3,
            CURLOPT_TIMEOUT         => 5,
            CURLOPT_POSTFIELDS      => json_encode(['uuid' => $this->getRegistrationUuid()]),
            CURLOPT_HTTPHEADER      => ['Content-Type: application/json'],
        ]);

        $content = json_decode(json: (string)curl_exec($ch), associative: true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($content) || !is_bool($content['registered'] ?? null)) {
            throw new RuntimeException('Unexpected registration status answer');
        }
        return $content['registered'];
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
