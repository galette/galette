<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

/**
 * Settings that can only be set in behavior.inc.php
 *
 * These are read before Galette can reach database, so they cannot be stored
 * as preferences. The advanced configuration page lists them so an
 * administrator can see what is set without opening the file, and knows which
 * names it understands.
 *
 * A constant superseded by a preference only shows up while it is declared:
 * it then overrides the setting replacing it, which the reader has to know.
 * Left undefined, it has nothing to say and stays out.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class BehaviorConstants
{
    /** Value is what the constant holds */
    public const string TYPE_VALUE = 'value';
    /** Only the existence of the constant counts, whatever its value */
    public const string TYPE_FLAG = 'flag';
    /** Value is an array */
    public const string TYPE_LIST = 'list';

    /**
     * Get every supported constant, with what it is currently set to
     *
     * @return array<int, array{
     *     name: string,
     *     defined: bool,
     *     value: string,
     *     description: string,
     *     replaced_by: ?string
     * }>
     */
    public static function getStatus(): array
    {
        $status = [];

        foreach (self::describe() as $name => [$type, $description]) {
            $status[] = self::entry(name: $name, type: $type, description: $description, replaced_by: null);
        }

        //constants a preference now supersedes are only worth a row while they
        //are declared: they then override the setting above, which is what the
        //reader has to know. Undefined, they are just a name nobody needs.
        foreach (PreferencesSchema::getConstants() as $preference => $name) {
            if (!defined($name)) {
                continue;
            }

            $status[] = self::entry(
                name: $name,
                type: self::TYPE_VALUE,
                description: sprintf(
                    _T("You may want to set it from the \"%s\" setting listed above and remove declared constant."),
                    $preference
                ),
                replaced_by: $preference
            );
        }

        return $status;
    }

    /**
     * Build one entry
     *
     * @param string  $name        Constant name
     * @param string  $type        One of the TYPE_* constants
     * @param string  $description What it does
     * @param ?string $replaced_by Preference superseding it, if any
     *
     * @return array{
     *     name: string,
     *     defined: bool,
     *     value: string,
     *     description: string,
     *     replaced_by: ?string
     * }
     */
    private static function entry(
        string $name,
        string $type,
        string $description,
        ?string $replaced_by
    ): array {
        return [
            'name'        => $name,
            'defined'     => defined($name),
            'value'       => self::format($name, $type),
            'description' => $description,
            'replaced_by' => $replaced_by,
        ];
    }

    /**
     * Describe every supported constant
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private static function describe(): array
    {
        return [
            'GALETTE_DEBUG' => [
                self::TYPE_VALUE,
                _T("Enable debug mode: verbose logs, Twig strict variables and detailed errors. Feature flags only apply when it is on."),
            ],
            'GALETTE_MODE' => [
                self::TYPE_VALUE,
                _T("PROD, MAINT to let only the superadmin log in, or DEMO to lock superadmin credentials and mail settings. DEV is deprecated, use debug mode."),
            ],
            'GALETTE_LOG_LVL' => [
                self::TYPE_VALUE,
                _T("Verbosity of the logs, as an Analog level. Defaults to WARNING, or DEBUG in debug mode."),
            ],
            'GALETTE_SQL_DEBUG' => [
                self::TYPE_FLAG,
                _T("Dump every SQL query to the logs. Only the existence of the constant counts, so setting it to false still enables the dump."),
            ],
            'GALETTE_FEATURE_FLAGS' => [
                self::TYPE_LIST,
                _T("Development features to activate, among those declared in the feature flags registry. They only apply in debug mode."),
            ],
        ];
    }

    /**
     * Render what a constant is currently set to
     *
     * @param string $name Constant name
     * @param string $type One of the TYPE_* constants
     */
    private static function format(string $name, string $type): string
    {
        if (!defined($name)) {
            return '';
        }

        if ($type === self::TYPE_FLAG) {
            return _T("enabled");
        }

        return self::stringify(constant($name), $type);
    }

    /**
     * Render a value for display
     *
     * @param mixed  $value Value to render
     * @param string $type  One of the TYPE_* constants
     */
    private static function stringify(mixed $value, string $type): string
    {
        if ($type === self::TYPE_LIST) {
            return is_array($value) ? implode(', ', $value) : (string)$value;
        }

        if (is_bool($value)) {
            return $value ? _T("yes") : _T("no");
        }

        return (string)$value;
    }
}
