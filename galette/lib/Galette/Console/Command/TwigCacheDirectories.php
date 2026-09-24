<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console\Command;

use Symfony\Component\Console\Exception\InvalidOptionException;

use function Safe\realpath;

/**
 * Directories used to extract strings from Twig templates, for the core or a plugin
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
trait TwigCacheDirectories
{
    /**
     * Get templates directory
     *
     * @param ?string $plugin Plugin directory name, null for the core
     */
    protected function getTemplatesDirectory(?string $plugin): string
    {
        $directory_path = sprintf(
            '%s/../../../../templates/default',
            __DIR__,
        );
        if ($plugin) {
            $directory_path = sprintf(
                '%s/../../../../plugins/%s/templates/default',
                __DIR__,
                $plugin
            );
        }

        $directory = realpath($directory_path);
        if (!is_dir($directory) || !is_readable($directory)) {
            throw new InvalidOptionException(
                sprintf('Unable to read templates directory "%s"', $directory_path)
            );
        }

        return $directory;
    }

    /**
     * Get directory where templates are compiled
     *
     * @param ?string $plugin Plugin directory name, null for the core
     */
    protected function getCacheDirectory(?string $plugin): string
    {
        $cache_dir_path = sprintf(
            '%s/../../../../..',
            __DIR__,
        );
        if ($plugin) {
            $cache_dir_path = sprintf(
                '%s/../../../../plugins/%s',
                __DIR__,
                $plugin
            );
        }

        $cache_dir = realpath($cache_dir_path);
        if (!is_dir($cache_dir) || !is_readable($cache_dir)) {
            throw new InvalidOptionException(
                sprintf('Unable to read cache directory "%s"', $cache_dir_path)
            );
        }

        return $cache_dir . '/tempcache';
    }
}
