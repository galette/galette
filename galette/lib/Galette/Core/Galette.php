<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Analog\Analog;
use Safe\DateTime;
use Galette\Util\Release;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Galette\Core\Routing\RouteLoader;
use Slim\App;

use function Safe\exec;

/**
 * Galette application instance
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Galette
{
    public const string MODE_PROD = 'PROD';
    public const string MODE_DEV = 'DEV';
    public const string MODE_MAINT = 'MAINT';
    public const string MODE_DEMO = 'DEMO';

    public const string RSS_URL = 'https://galette.eu/dc/index.php/feed/atom';

    /**
     * Retrieve Galette version from git, if present.
     *
     * @param bool $time Include time and timezone. Defaults to false.
     */
    public static function gitVersion(bool $time = false): string
    {
        $galette_version = GALETTE_VERSION;

        //used for both git and nightly installs
        $version = str_replace('-dev', '-git', GALETTE_VERSION);
        if (!str_contains($version, '-git')) {
            $version .= '-git';
        }

        if (file_exists(GALETTE_ROOT . '../.git')) {
            $git = 'git -C ' . escapeshellarg(GALETTE_ROOT . '..');

            //a release tag checkout is the release itself
            $output = [];
            $result = 1;
            exec($git . ' describe --tags --exact-match HEAD 2>/dev/null', $output, $result);
            if ($result === 0 && trim($output[0] ?? '') === GALETTE_VERSION) {
                return GALETTE_VERSION;
            }

            $output = [];
            $result = 1;
            exec($git . ' log -n1 --pretty="%h %ci" HEAD 2>/dev/null', $output, $result);
            if ($result !== 0 || !isset($output[0]) || !str_contains($output[0], ' ')) {
                //git cannot be run (missing binary, repository not marked as safe.directory for the web server user, ...)
                Analog::log(
                    'Unable to retrieve git version information (exit code ' . $result . ')',
                    Analog::WARNING
                );
                return $version;
            }

            [$commitHash, $commitDate] = explode(' ', trim($output[0]), 2);
            $commitDate = new DateTime($commitDate);

            $galette_version = sprintf(
                '%s-%s (%s)',
                $version,
                $commitHash,
                $commitDate->format(($time ? 'Y-m-d H:i:s T' : 'Y-m-d'))
            );
        } elseif (static::isNightly()) {
            $galette_version = $version . '-' . GALETTE_NIGHTLY;
        }
        return $galette_version;
    }

    /**
     * Is demonstration mode enabled
     */
    public static function isDemo(): bool
    {
        return GALETTE_MODE === static::MODE_DEMO;
    }

    /**
     * Accounts offered on the login page of a demonstration instance
     *
     * Read from GALETTE_DEMO_ACCOUNTS, only in demonstration mode: the
     * super administrator password is only stored hashed, the instance
     * owner is the only one who can give it.
     *
     * @return array<int, array{login: string, password: string, label: string}>
     */
    public static function getDemoAccounts(): array
    {
        if (!static::isDemo() || !defined('GALETTE_DEMO_ACCOUNTS')) {
            return [];
        }

        return static::filterDemoAccounts(constant('GALETTE_DEMO_ACCOUNTS'));
    }

    /**
     * Keep well-formed demonstration accounts only
     *
     * Each one needs a login and a password; its label falls back to the login.
     *
     * @param mixed $accounts Declared accounts
     *
     * @return array<int, array{login: string, password: string, label: string}>
     */
    public static function filterDemoAccounts(mixed $accounts): array
    {
        if (!is_array($accounts)) {
            return [];
        }

        $filtered = [];
        foreach ($accounts as $account) {
            if (
                !is_array($account)
                || !is_string($account['login'] ?? null)
                || trim($account['login']) === ''
                || !is_string($account['password'] ?? null)
                || $account['password'] === ''
            ) {
                continue;
            }

            $label = $account['label'] ?? null;
            $filtered[] = [
                'login' => $account['login'],
                'password' => $account['password'],
                'label' => is_string($label) && trim($label) !== '' ? $label : $account['login'],
            ];
        }

        return $filtered;
    }

    /**
     * Is maintenance mode enabled
     */
    public static function isUnderMaintenance(): bool
    {
        return GALETTE_MODE === static::MODE_MAINT;
    }

    /**
     * Is debug mode enabled
     */
    public static function isDebugEnabled(): bool
    {
        if (GALETTE_MODE === static::MODE_DEV) {
            //since 1.1.0, GALETTE_MODE with DEV value is deprecated.
            Analog::log(
                'Using GALETTE_MODE set to DEV is deprecated. Use GALETTE_DEBUG.',
                Analog::WARNING
            );
            return true;
        }
        // @const bool GALETTE_DEBUG
        return GALETTE_DEBUG === true;
    }

    /**
     * Is SQL debug mode enabled
     */
    public static function isSqlDebugEnabled(): bool
    {
        return defined('GALETTE_SQL_DEBUG') && GALETTE_SQL_DEBUG || static::isDebugEnabled();
    }

    /**
     * Is a nightly build
     */
    public static function isNightly(): bool
    {
        return GALETTE_NIGHTLY !== false;
    }

    /**
     * Check if a string is serialized
     *
     * @param string $string String to check
     */
    public static function isSerialized(string $string): bool
    {
        return @unserialize($string) !== false;
    }

    /**
     * JSON decode with exception
     *
     * @param string $string JSON encoded string to decode
     *
     * @return array<string|int, mixed>
     * @throws RuntimeException
     */
    public static function jsonDecode(string $string): array
    {
        $decoded = \json_decode($string, associative: true); // @phpstan-ignore theCodingMachineSafe.function
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('JSON decode error: ' . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * JSON encode with exception
     *
     * @param array<string|int, mixed>|object $data Data to encode
     *
     * @throws RuntimeException
     */
    public static function jsonEncode(array|object $data): string
    {
        $encoded = \json_encode($data); // @phpstan-ignore theCodingMachineSafe.function
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('JSON encode error: ' . json_last_error_msg());
        }

        return $encoded;
    }

    /**
     * Load routes
     *
     * @param App<ContainerInterface> $app  App instance
     * @param bool                    $cron Load only routes cron tasks may need
     */
    public static function loadRoutes(App $app, bool $cron = false): void
    {
        (new RouteLoader())->load($app, $cron);
    }
}
