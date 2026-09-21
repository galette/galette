<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\IO;

use Galette\Core\Preferences;
use Galette\Core\PreferencesSchema;

/**
 * Maximum size of an upload, one case per kind of upload
 *
 * Each case carries the preference holding its limit, so a caller asks for
 * "how big an attachment may be" rather than for a preference name, and every
 * kind stays free to move on its own: raising the limit for a mailing has
 * nothing to do with raising it for member pictures.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
enum UploadSize: string
{
    /** Member pictures, association and card logos */
    case Images = 'pref_upload_size_images';
    /** Files attached to a mailing */
    case Attachments = 'pref_upload_size_attachments';
    /** Association documents */
    case Documents = 'pref_upload_size_documents';
    /** CSV files handed to the import */
    case Imports = 'pref_upload_size_imports';
    /** Files of a dynamic field that declares no size of its own */
    case DynamicFiles = 'pref_upload_size_dynamic_files';

    /**
     * Maximum size for that kind of upload, in Ko
     *
     * PHP has the last word in any case: a file over `upload_max_filesize`, or
     * a request over `post_max_size`, never reaches Galette whatever is set
     * here.
     *
     * @param ?Preferences $preferences Preferences instance, when the caller holds one
     */
    public function get(?Preferences $preferences = null): int
    {
        //most uploads are handled by classes no Preferences instance ever
        //reaches - a picture is built from a member identifier alone - so the
        //global one answers for them
        $preferences ??= self::globalPreferences();

        if (!$preferences instanceof Preferences) {
            //not even a global one: installer, CLI before bootstrap, unit test
            return (int)PreferencesSchema::getDefaults()[$this->value];
        }

        return (int)$preferences->{$this->value};
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
