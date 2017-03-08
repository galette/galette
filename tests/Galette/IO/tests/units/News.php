<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * News tests
 *
 * PHP version 5
 *
 * Copyright © 2017 The Galette Team
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
 * @copyright 2017 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2017-03-07
 */

namespace Galette\IO\test\units;

use \atoum;

/**
 * News tests class
 *
 * @category  Core
 * @name      News
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2017-03-07
 */
class News extends atoum
{
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
        $news = new \Galette\IO\News('http://galette.eu/dc/index.php/feed/atom', true);
        $posts = $news->getPosts();
        $this->array($posts)
            ->size->isGreaterThan(0);
    }

    /**
     * Test news caching
     *
     * @return void
     */
    public function testCacheNews()
    {
        $file = GALETTE_CACHE_DIR . md5('http://galette.eu/dc/index.php/feed/atom') . '.cache';

        //ensure file does not exists
        $this->boolean(file_exists($file))->isFalse;

        //load news with caching
        $news = new \Galette\IO\News('http://galette.eu/dc/index.php/feed/atom');

        $posts = $news->getPosts();
        $this->array($posts)
            ->size->isGreaterThan(0);

        //ensure file does exists
        $this->boolean(file_exists($file))->isTrue;

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
        $this->boolean($touched)->isTrue;

        $news = new \Galette\IO\News('http://galette.eu/dc/index.php/feed/atom');
        $mnewdate = \DateTime::createFromFormat(
            $dformat,
            date(
                $dformat,
                filemtime($file)
            )
        );
        $isnewdate = $mnewdate > $mdate;
        $this->boolean($isnewdate)->isTrue;

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
        $this->assert('News cannot be loaded')
            ->if($this->function->ini_get = 0)
            ->given($news = new \Galette\IO\News('http://galette.eu/dc/index.php/feed/atom', true))
            ->then
                ->array($news->getPosts())
                ->hasSize(0);
    }
}
