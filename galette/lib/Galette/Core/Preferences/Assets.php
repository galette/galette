<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Preferences;

use Analog\Analog;
use Galette\Core\Logo;
use Galette\Core\PrintLogo;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Flash\Messages;

use function Safe\mkdir;
use function Safe\preg_replace;
use function Safe\unlink;

/**
 * The files and stylesheets preferences produce
 *
 * Uploading a logo, purifying the HTML an administrator typed, and dropping
 * the generated dark mode stylesheet have nothing to do with reading or
 * writing a preference. They only live next to them because a preference is
 * what triggers them.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class Assets
{
    /** Generated stylesheet, rebuilt from the custom colours on next request */
    private const string DARK_CSS = 'dark.css';

    /**
     * Store an uploaded logo
     *
     * @param Logo|PrintLogo        $logo          Logo instance
     * @param UploadedFileInterface $uploaded_file Uploaded file
     *
     * @return array<string> Errors, empty when the logo was stored
     */
    public function storeLogo(Logo|PrintLogo $logo, UploadedFileInterface $uploaded_file): array
    {
        $errors = [];

        if ($uploaded_file->getError() === UPLOAD_ERR_OK) {
            $res = $logo->storeFile($uploaded_file);
            if ($res !== true) {
                $errors[] = $logo->getErrorMessage($res);
            }
        } elseif ($uploaded_file->getError() !== UPLOAD_ERR_NO_FILE) {
            $errors[] = $logo->getPhpErrorMessage($uploaded_file->getError());
        }

        if ($errors !== []) {
            Analog::log(
                'Some errors has been thew attempting to edit/store logo' . "\n"
                . print_r($errors, true),
                Analog::WARNING
            );
        }

        return $errors;
    }

    /**
     * Purify HTML an administrator typed
     *
     * @param string $value Value to clean
     */
    public function cleanHtmlValue(string $value): string
    {
        $config = \HTMLPurifier_Config::createDefault();
        $cache_dir = rtrim(GALETTE_CACHE_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'htmlpurifier';
        if (!file_exists($cache_dir)) {
            mkdir($cache_dir, 0o755, true);
        }
        $config->set('Cache.SerializerPath', $cache_dir);
        $config->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'mailto' => true,
            'ftp' => true,
        ]);
        $purifier = new \HTMLPurifier($config);

        // Remove all dangerous schemes
        $value = preg_replace(
            '/\b(?:javascript|data|vbscript):\s*/i',
            '',
            $value
        );

        return $purifier->purify($value);
    }

    /**
     * Would storing those values make the dark stylesheet stale?
     *
     * Current values have to be read the way they are exposed, not the way
     * they are stored: a boolean kept as an empty string does not compare to a
     * submitted one the same way.
     *
     * @param array<string, mixed> $submitted Values about to be stored
     * @param array<string, mixed> $current   Current values, as exposed
     */
    public function isCssImpacted(array $submitted, array $current): bool
    {
        foreach ($current as $name => $value) {
            if (($submitted[$name] ?? '') != $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Drop the generated dark mode stylesheet
     *
     * @param Messages $flash Flash messages instance
     *
     * @return bool Whether there was one to drop
     */
    public function resetDarkCss(Messages $flash): bool
    {
        $cssfile = GALETTE_CACHE_DIR . '/' . self::DARK_CSS;

        if (!file_exists($cssfile)) {
            return false;
        }

        unlink($cssfile);
        // Inform user when the dark mode CSS file has been reset
        $flash->addMessage(
            'info_detected',
            _T("Dark mode CSS file has been reset.")
        );

        return true;
    }
}
