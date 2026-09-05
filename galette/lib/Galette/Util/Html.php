<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Util;

use Galette\Converter\ImageConverter;
use Galette\Converter\ParagraphConverter;
use Galette\Converter\TextConverter;
use League\HTMLToMarkdown\HtmlConverter;

use function Safe\mkdir;
use function Safe\preg_replace;

/**
 * HTML utilities
 *
 * What to do with markup an administrator typed: make it safe to render, or
 * drop it altogether.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Html
{
    /**
     * Strip anything a browser should not be asked to run
     *
     * What comes back is safe to render, links included.
     *
     * @param string $html HTML to clean
     */
    public static function clean(string $html): string
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
        $html = preg_replace(
            '/\b(?:javascript|data|vbscript):\s*/i',
            '',
            $html
        );

        return $purifier->purify($html);
    }

    /**
     * Convert HTML to text
     *
     * @param string $html HTML to convert
     */
    public static function convertToText(string $html): string
    {
        $converter = new HtmlConverter();
        $environment = $converter->getEnvironment();
        $environment->addConverter(new ImageConverter()); // optionally - add converter manually
        //do not escape Markdown special characters, result is used as plain text
        $environment->addConverter(new TextConverter());
        $environment->addConverter(new ParagraphConverter());

        $config = $converter->getConfig();
        $config->setOption('strip_tags', true); //remove all tags
        $config->setOption('hard_break', true); //convert <br> to \n only
        $config->setOption('header_style', 'atx'); //set headers style to atx (with #)
        $config->setOption('strip_placeholder_links', true); //to remove links without links
        $config->setOption('remove_nodes', 'meta script style'); //nodes to just remove

        return $converter->convert($html);
    }
}
