<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\IO;

use Analog\Analog;
use Galette\Core\Preferences;
use Galette\Core\PreferencesSchema;

use function Safe\preg_match;
use function Safe\preg_split;

/**
 * Extensions of the files an administrator lets in
 *
 * One list, set from the advanced configuration, for the documents, the
 * mailings attachments and the dynamic fields files; pictures and imports keep
 * the fixed list they work with. An empty list lets any extension in.
 *
 * It only narrows what `UnsafeFiles` leaves: a file a web server could run is
 * refused whatever the list says.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class UploadExtensions
{
    public const string PREFERENCE = 'pref_upload_extensions';

    /**
     * Office documents, pictures, text and archives
     *
     * No svg nor html: a browser opening one runs the scripts it carries.
     */
    public const string DEFAULT = 'pdf odt ods odp odg doc docx xls xlsx ppt pptx rtf txt csv'
        . ' jpg jpeg png gif webp zip';

    /**
     * Allowed extensions, lower case and without their dot
     *
     * @param ?Preferences $preferences Preferences instance, when the caller holds one
     *
     * @return array<int,string>
     */
    public static function get(?Preferences $preferences = null): array
    {
        //most uploads are handled by classes no Preferences instance ever
        //reaches, so the global one answers for them
        $preferences ??= self::globalPreferences();

        $value = $preferences instanceof Preferences
            ? $preferences->{self::PREFERENCE}
            : PreferencesSchema::getDefaults()[self::PREFERENCE];

        $extensions = is_string($value) ? self::parse($value) : null;
        if ($extensions === null) {
            //only a value written behind the settings back gets there
            Analog::log('Invalid allowed upload extensions, falling back to defaults.', Analog::WARNING);
            $extensions = self::parse((string)PreferencesSchema::getDefaults()[self::PREFERENCE]) ?? [];
        }

        return $extensions;
    }

    /**
     * Does the list let that file name in?
     *
     * @param string        $filename   File name, as sent by the client
     * @param array<string> $extensions Allowed extensions; an empty list lets anything in
     */
    public static function allows(string $filename, array $extensions): bool
    {
        if ($extensions === []) {
            return true;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $extensions, strict: true);
    }

    /**
     * Value for the `accept` attribute of a file input
     *
     * @param array<string> $extensions Allowed extensions
     */
    public static function accept(array $extensions): string
    {
        return implode(',', array_map(static fn(string $extension): string => '.' . $extension, $extensions));
    }

    /**
     * Read a list of extensions, as typed by an administrator
     *
     * Extensions are separated by spaces or commas, a leading dot is dropped
     * and case does not matter.
     *
     * @param string $value List of extensions
     *
     * @return array<int,string>|null Normalized extensions, or null if one of them is not valid
     */
    public static function parse(string $value): ?array
    {
        $extensions = [];
        $parts = preg_split(pattern: '/[\s,;]+/', subject: strtolower(trim($value)), limit: -1, flags: PREG_SPLIT_NO_EMPTY);
        foreach ($parts as $extension) {
            $extension = ltrim($extension, '.');
            if (preg_match('/^[a-z0-9]+$/', $extension) !== 1) {
                return null;
            }
            $extensions[] = $extension;
        }

        return array_values(array_unique($extensions));
    }

    /**
     * Extensions of a list no upload will ever accept
     *
     * @param array<string> $extensions Extensions
     *
     * @return array<int,string>
     */
    public static function getUnsafe(array $extensions): array
    {
        return array_values(
            array_filter(
                $extensions,
                static fn(string $extension): bool => UnsafeFiles::hasUnsafeExtension('file.' . $extension)
            )
        );
    }

    /**
     * The Preferences instance the application bootstrap has set up, if any
     */
    private static function globalPreferences(): ?Preferences
    {
        $preferences = $GLOBALS['preferences'] ?? null;

        return $preferences instanceof Preferences ? $preferences : null;
    }
}
