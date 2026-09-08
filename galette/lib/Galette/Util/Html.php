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
use Safe\Exceptions\FilesystemException;

use function Safe\mkdir;
use function Safe\preg_replace;
use function Safe\preg_replace_callback;

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
    /** @var string Shape of the replacement patterns Galette substitutes */
    private const string PATTERN = '/\{[A-Z0-9_]+\}/';

    /**
     * Strip anything a browser should not be asked to run
     *
     * What comes back is safe to render, links included.
     *
     * @param string $html     HTML to clean
     * @param bool   $keep_ids Keep `id` attributes, dropped by default
     */
    public static function clean(string $html, bool $keep_ids = false): string
    {
        $config = \HTMLPurifier_Config::createDefault();
        $cache_dir = rtrim(GALETTE_CACHE_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'htmlpurifier';
        if (!is_dir($cache_dir)) {
            try {
                mkdir($cache_dir, 0o755, recursive: true);
            } catch (FilesystemException $e) {
                //another request may have created it in between
                if (!is_dir($cache_dir)) {
                    throw $e;
                }
            }
        }
        $config->set('Cache.SerializerPath', $cache_dir);
        $config->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'mailto' => true,
            'ftp' => true,
        ]);
        if ($keep_ids) {
            //HTMLPurifier drops every id per default. PDF models need theirs:
            //their CSS selects on them (`td#pdf_assoname`, `div#pdf_footer`...)
            $config->set('Attr.EnableID', value: true);
        }
        $purifier = new \HTMLPurifier($config);

        // Remove all dangerous schemes
        $stripped_schemes = preg_replace(
            '/\b(?:javascript|data|vbscript):\s*/i',
            '',
            $html
        );

        [$hidden, $tokens] = self::hidePatterns($stripped_schemes);
        $purified = strtr($purifier->purify($hidden), $tokens);

        //HTMLPurifier normalizes every line ending to LF, and leaves no CR
        //behind. Sanitizing markup is no reason to rewrite whitespace: a mail
        //signature and a mail text keep the endings they came with
        if (str_contains($html, "\r\n")) {
            $purified = str_replace("\n", "\r\n", $purified);
        }

        return $purified;
    }

    /**
     * Take Galette's replacement patterns out of HTMLPurifier's way
     *
     * `{ASSO_WEBSITE}` sitting in an `href` comes back percent-encoded as
     * `%7BASSO_WEBSITE%7D`, and no longer matches the pattern that was meant
     * to substitute it. Each one travels as an opaque token instead, which
     * survives both a text and an attribute position, and the caller puts the
     * patterns back.
     *
     * @param string $html HTML to hide the patterns of
     *
     * @return array{0: string, 1: array<string, string>} HTML, then token => pattern
     */
    private static function hidePatterns(string $html): array
    {
        $tokens = [];

        $hidden = preg_replace_callback(
            self::PATTERN,
            function (array $matches) use (&$tokens): string {
                $token = 'galette-pattern-' . count($tokens);
                $tokens[$token] = $matches[0];
                return $token;
            },
            $html
        );

        return [$hidden, $tokens];
    }

    /**
     * Drop markup altogether and hand back the text it carried
     *
     * For a field that is not meant to hold HTML. What comes back is plain
     * text, not entities: the caller escapes it on output like any other
     * value, and a legitimate `&` or `<` survives untouched, line endings
     * included.
     *
     * @param string $html HTML to strip
     */
    public static function strip(string $html): string
    {
        //decode until nothing changes: `&amp;lt;script&amp;gt;` would
        //otherwise come back as markup once the tags are gone
        do {
            $previous = $html;
            $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);
        } while ($html !== $previous);

        //clean() first: strip_tags() alone would keep a script's body as text
        return html_entity_decode(strip_tags(self::clean($html)), ENT_QUOTES | ENT_HTML5);
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
        $config->setOption('strip_tags', value: true); //remove all tags
        $config->setOption('hard_break', value: true); //convert <br> to \n only
        $config->setOption('header_style', 'atx'); //set headers style to atx (with #)
        $config->setOption('strip_placeholder_links', value: true); //to remove links without links
        $config->setOption('remove_nodes', 'meta script style'); //nodes to just remove

        return $converter->convert($html);
    }
}
