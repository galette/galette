<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Util;

use Galette\Converter\ImageConverter;
use League\HTMLToMarkdown\HtmlConverter;

use function Safe\preg_replace;

/**
 * Text utilities
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Text
{
    /**
     * Slugify a string
     *
     * @param string $string String to slugify
     * @param string $prefix Prefix to use
     */
    public static function slugify(string $string, string $prefix = ''): string
    {
        $slug_string = $prefix . $string;
        $slug_string = transliterator_transliterate("Any-Latin; Latin-ASCII; [^a-zA-Z0-9\.\ -_] Remove;", $slug_string);
        $slug_string = str_replace(' ', '-', mb_strtolower((string)$slug_string, 'UTF-8'));
        $slug_string = preg_replace('~[^0-9a-z_\.]+~i', '-', $slug_string);
        $slug_string = trim((string)$slug_string, '-');
        if ($slug_string == '') {
            throw new \RuntimeException(
                'Cannot create a slug from the given string ' . $string
            );
        }
        return $slug_string;
    }

    /**
     * Get a random string
     *
     * @see https://stackoverflow.com/questions/4356289/php-random-string-generator/31107425#31107425
     * @param int $length of the random string
     */
    public static function getRandomString(int $length): string
    {
        $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $str = '';
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }
        return $str;
    }

    /**
     * Truncate a string on words
     *
     * @param string $text      Text to truncate
     * @param int    $max_words Maximum number of words to keep
     * @param string $suffix    Suffix to append if truncated
     * @param bool   $keep_html Keep HTML tags or not
     */
    public static function truncateOnWords(
        string $text,
        int $max_words = 10,
        string $suffix = '…',
        bool $keep_html = false
    ): string {
        if ($keep_html === false) {
            // Remove HTML tags if not keeping HTML
            $text = strip_tags($text);
        }
        $words = explode(' ', $text);
        if (count($words) > $max_words) {
            return implode(' ', array_slice($words, 0, $max_words)) . $suffix;
        }
        return $text;
    }

    /**
     * Convert HTML to text
     *
     * @param string $html HTML to convert
     */
    public static function convertHtmlToText(string $html): string
    {
        $converter = new HtmlConverter();
        $environment = $converter->getEnvironment();
        $environment->addConverter(new ImageConverter()); // optionally - add converter manually

        $config = $converter->getConfig();
        $config->setOption('strip_tags', true); //remove all tags
        $config->setOption('hard_break', true); //convert <br> to \n only
        $config->setOption('header_style', 'atx'); //set headers style to atx (with #)
        $config->setOption('strip_placeholder_links', true); //to remove links without links
        $config->setOption('remove_nodes', 'meta script style'); //nodes to just remove

        return $converter->convert($html);
    }
}
