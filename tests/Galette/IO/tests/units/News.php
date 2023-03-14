<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * News tests
 *
 * PHP version 5
 *
 * Copyright © 2017-2023 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Core
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2017-03-07
 */

namespace Galette\IO\test\units;

use PHPUnit\Framework\TestCase;

/**
 * News tests class
 *
 * @category  Core
 * @name      News
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2017-03-07
 */
class News extends TestCase
{
    private \Galette\Core\I18n $i18n;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->i18n = new \Galette\Core\I18n();
        global $i18n;
        $i18n = $this->i18n;
    }

    /**
     * Test news loading
     *
     * @return void
     */
    public function testLoadNews()
    {
        //ensure allow_url_fopen is on
        ini_set('allow_url_fopen', true);
        //load news without caching
        $news = new \Galette\IO\News('https://galette.eu/site/feed.xml', true);
        $posts = $news->getPosts();
        $this->assertGreaterThan(0, count($posts));
    }

    /**
     * Test news caching
     *
     * @return void
     */
    public function testCacheNews()
    {
        //will use default lang to build RSS URL
        $file = GALETTE_CACHE_DIR . md5('https://galette.eu/site/feed.xml') . '.cache';

        //ensure file does not exist
        $this->assertFalse(file_exists($file));

        //load news with caching
        $news = new \Galette\IO\News('https://galette.eu/site/feed.xml');

        $posts = $news->getPosts();
        $this->assertGreaterThan(0, count($posts));

        //ensure file does exists
        $this->assertTrue(file_exists($file));

        $dformat = 'Y-m-d H:i:s';
        $mdate = \DateTime::createFromFormat(
            $dformat,
            date(
                $dformat,
                filemtime($file)
            )
        );

        $expired = $mdate->sub(
            new \DateInterval('PT25H')
        );
        $touched = touch($file, $expired->getTimestamp());
        $this->assertTrue($touched);

        $news = new \Galette\IO\News('https://galette.eu/site/feed.xml');
        $mnewdate = \DateTime::createFromFormat(
            $dformat,
            date(
                $dformat,
                filemtime($file)
            )
        );
        $isnewdate = $mnewdate > $mdate;
        $this->assertTrue($isnewdate);

        //drop file finally
        unlink($file);
    }


    /**
     * Test news loading with allow_url_fopen off
     *
     * @return void
     */
    public function testLoadNewsWExeption()
    {
        $news = $this->getMockBuilder(\Galette\IO\News::class)
            ->setConstructorArgs(array('https://galette.eu/site/feed.xml', true))
            ->onlyMethods(array('allowURLFOpen'))
            ->getMock();
        $news->method('allowURLFOpen')->willReturn(false);

        $this->assertCount(0, $news->getPosts());
    }
}
