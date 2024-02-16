<?php

/**
 * Copyright © 2003-2024 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
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
 */

namespace Galette\Util\test\units;

use PHPUnit\Framework\TestCase;

/**
 * Release tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Release extends TestCase
{
    /**
     * Releases provider
     *
     * @return array<int, array<int, string|bool>>
     */
    public static function releasesProvider(): array
    {
        return [
            ['0.7.0', '1.1.0', true],
            ['1.0.0', '1.1.0', true],
            ['1.0.1', '1.1.0', true],
            ['1.1.0', '1.1.0', false],
            ['1.2.0', '1.1.0', false],
        ];
    }

    /**
     * Test checkNewRelease
     *
     * @param string $current  Current release
     * @param string $latest   Latest release
     * @param bool   $expected Expected result
     *
     * @dataProvider releasesProvider
     * @return void
     */
    public function testNewRelease(string $current, string $latest, bool $expected): void
    {
        $release = $this->getMockBuilder(\Galette\Util\Release::class)
            ->onlyMethods(array('getCurrentRelease', 'getLatestRelease'))
            ->getMock();

        $release->method('getCurrentRelease')->willReturn($current);
        $release->method('getLatestRelease')->willReturn($latest);

        $this->assertSame($expected, $release->checkNewRelease());
    }

    /**
     * Releases provider
     *
     * @return array<int, array<int, string|bool>>
     */
    public static function releasesPageProvider(): array
    {
        return [
            [
                '0.7.0',
                '1.0.2',
                true,
                '<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
<title>Index of /galette/</title>
<style type="text/css">
a, a:active {text-decoration: none; color: blue;}
a:visited {color: #48468F;}
a:hover, a:focus {text-decoration: underline; color: red;}
body {background-color: #F5F5F5;}
h2 {margin-bottom: 12px;}
table {margin-left: 12px;}
th, td { font: 90% monospace; text-align: left;}
th { font-weight: bold; padding-right: 14px; padding-bottom: 3px;}
td {padding-right: 14px;}
td.s, th.s {text-align: right;}
div.list { background-color: white; border-top: 1px solid #646464; border-bottom: 1px solid #646464; padding-top: 10px; padding-bottom: 14px;}
div.foot { font: 90% monospace; color: #787878; padding-top: 4px;}
</style>
</head>
<body>
<h2>Index of /galette/</h2>
<div class="list">
<table summary="Directory Listing" cellpadding="0" cellspacing="0">
<thead><tr><th class="n">Name</th><th class="m">Last Modified</th><th class="s">Size</th><th class="t">Type</th></tr></thead>
<tbody>
<tr><td class="n"><a href="../">Parent Directory</a>/</td><td class="m">&nbsp;</td><td class="s">- &nbsp;</td><td class="t">Directory</td></tr>
<tr><td class="n"><a href="archives/">archives</a>/</td><td class="m">2024-Jan-16 09:01:57</td><td class="s">- &nbsp;</td><td class="t">Directory</td></tr>
<tr><td class="n"><a href="com/">com</a>/</td><td class="m">2016-Sep-22 22:23:10</td><td class="s">- &nbsp;</td><td class="t">Directory</td></tr>
<tr><td class="n"><a href="dev/">dev</a>/</td><td class="m">2023-Nov-22 18:56:34</td><td class="s">- &nbsp;</td><td class="t">Directory</td></tr>
<tr><td class="n"><a href="listes-galette/">listes-galette</a>/</td><td class="m">2017-Feb-02 02:06:43</td><td class="s">- &nbsp;</td><td class="t">Directory</td></tr>
<tr><td class="n"><a href="plugins/">plugins</a>/</td><td class="m">2024-Jan-16 17:23:37</td><td class="s">- &nbsp;</td><td class="t">Directory</td></tr>
<tr><td class="n"><a href="README">README</a></td><td class="m">2021-Sep-22 07:29:48</td><td class="s">0.1K</td><td class="t">text/plain</td></tr>
<tr><td class="n"><a href="galette-1.0.0.tar.bz2">galette-1.0.0.tar.bz2</a></td><td class="m">2023-Dec-07 07:57:12</td><td class="s">8.7M</td><td class="t">application/octet-stream</td></tr>
<tr><td class="n"><a href="galette-1.0.1.tar.bz2">galette-1.0.1.tar.bz2</a></td><td class="m">2024-Jan-16 09:00:19</td><td class="s">8.7M</td><td class="t">application/octet-stream</td></tr>
<tr><td class="n"><a href="galette-1.0.2.tar.bz2">galette-1.0.2.tar.bz2</a></td><td class="m">2024-Feb-03 09:32:46</td><td class="s">8.7M</td><td class="t">application/octet-stream</td></tr>
<tr><td class="n"><a href="galette-1.0.2.tar.bz2.asc">galette-1.0.2.tar.bz2.asc</a></td><td class="m">2024-Feb-03 09:32:46</td><td class="s">0.1K</td><td class="t">text/plain</td></tr>
<tr><td class="n"><a href="galette-dev.tar.bz2">galette-dev.tar.bz2</a></td><td class="m">2024-Feb-16 00:34:58</td><td class="s">8.7M</td><td class="t">application/octet-stream</td></tr>
<tr><td class="n"><a href="galette-dev.tar.bz2.asc">galette-dev.tar.bz2.asc</a></td><td class="m">2023-Oct-19 17:32:27</td><td class="s">0.1K</td><td class="t">text/plain</td></tr>
</tbody>
</table>
</div>
<div class="foot">lighttpd/1.4.35</div>
</body>
</html>' //real content as of 1.0.2 release
            ],
            [
                '1.0.0',
                '1.1.0',
                true,
                '<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
<title>Index of /galette/</title>
<style type="text/css">
a, a:active {text-decoration: none; color: blue;}
a:visited {color: #48468F;}
a:hover, a:focus {text-decoration: underline; color: red;}
body {background-color: #F5F5F5;}
h2 {margin-bottom: 12px;}
table {margin-left: 12px;}
th, td { font: 90% monospace; text-align: left;}
th { font-weight: bold; padding-right: 14px; padding-bottom: 3px;}
td {padding-right: 14px;}
td.s, th.s {text-align: right;}
div.list { background-color: white; border-top: 1px solid #646464; border-bottom: 1px solid #646464; padding-top: 10px; padding-bottom: 14px;}
div.foot { font: 90% monospace; color: #787878; padding-top: 4px;}
</style>
</head>
<body>
<h2>Index of /galette/</h2>
<div class="list">
<table summary="Directory Listing" cellpadding="0" cellspacing="0">
<thead><tr><th class="n">Name</th><th class="m">Last Modified</th><th class="s">Size</th><th class="t">Type</th></tr></thead>
<tbody>
<tr><td class="n"><a href="../">Parent Directory</a>/</td><td class="m">&nbsp;</td><td class="s">- &nbsp;</td><td class="t">Directory</td></tr>
<tr><td class="n"><a href="archives/">archives</a>/</td><td class="m">2024-Jan-16 09:01:57</td><td class="s">- &nbsp;</td><td class="t">Directory</td></tr>
<tr><td class="n"><a href="com/">com</a>/</td><td class="m">2016-Sep-22 22:23:10</td><td class="s">- &nbsp;</td><td class="t">Directory</td></tr>
<tr><td class="n"><a href="dev/">dev</a>/</td><td class="m">2023-Nov-22 18:56:34</td><td class="s">- &nbsp;</td><td class="t">Directory</td></tr>
<tr><td class="n"><a href="listes-galette/">listes-galette</a>/</td><td class="m">2017-Feb-02 02:06:43</td><td class="s">- &nbsp;</td><td class="t">Directory</td></tr>
<tr><td class="n"><a href="plugins/">plugins</a>/</td><td class="m">2024-Jan-16 17:23:37</td><td class="s">- &nbsp;</td><td class="t">Directory</td></tr>
<tr><td class="n"><a href="README">README</a></td><td class="m">2021-Sep-22 07:29:48</td><td class="s">0.1K</td><td class="t">text/plain</td></tr>
<tr><td class="n"><a href="galette-1.0.0.tar.bz2">galette-1.0.0.tar.bz2</a></td><td class="m">2023-Dec-07 07:57:12</td><td class="s">8.7M</td><td class="t">application/octet-stream</td></tr>
<tr><td class="n"><a href="galette-1.0.1.tar.bz2">galette-1.0.1.tar.bz2</a></td><td class="m">2024-Jan-16 09:00:19</td><td class="s">8.7M</td><td class="t">application/octet-stream</td></tr>
<tr><td class="n"><a href="galette-1.0.2.tar.bz2">galette-1.0.2.tar.bz2</a></td><td class="m">2024-Feb-03 09:32:46</td><td class="s">8.7M</td><td class="t">application/octet-stream</td></tr>
<tr><td class="n"><a href="galette-1.0.2.tar.bz2.asc">galette-1.0.2.tar.bz2.asc</a></td><td class="m">2024-Feb-03 09:32:46</td><td class="s">0.1K</td><td class="t">text/plain</td></tr>
<tr><td class="n"><a href="galette-1.1.0.tar.bz2">galette-1.1.0.tar.bz2</a></td><td class="m">2024-Feb-16 09:09:13</td><td class="s">10M</td><td class="t">application/octet-stream</td></tr>
<tr><td class="n"><a href="galette-1.1.0.tar.bz2.asc">galette-1.1.0.tar.bz2.asc</a></td><td class="m">2024-Feb-16 09:09:13</td><td class="s">0.1K</td><td class="t">text/plain</td></tr>
<tr><td class="n"><a href="galette-dev.tar.bz2">galette-dev.tar.bz2</a></td><td class="m">2024-Feb-16 00:34:58</td><td class="s">8.7M</td><td class="t">application/octet-stream</td></tr>
<tr><td class="n"><a href="galette-dev.tar.bz2.asc">galette-dev.tar.bz2.asc</a></td><td class="m">2023-Oct-19 17:32:27</td><td class="s">0.1K</td><td class="t">text/plain</td></tr>
</tbody>
</table>
</div>
<div class="foot">lighttpd/1.4.35</div>
</body>
</html>' //fakse 1.1.0 release
            ],
            [
                '1.1.0',
                '1.1.0',
                false,
                '<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<body>
<div class="list">
<table summary="Directory Listing" cellpadding="0" cellspacing="0">
<tbody>
<tr><td class="n"><a href="galette-1.1.0.tar.bz2">galette-1.1.0.tar.bz2</a></td><td class="m">2024-Feb-16 09:09:13</td><td class="s">10M</td><td class="t">application/octet-stream</td></tr>
</tbody>
</table>
</div>
</body>
</html>' //fake 1.1.0 release
            ],
            [
                '1.2.0',
                '1.1.0',
                false,
                '<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<body>
<div class="list">
<table summary="Directory Listing" cellpadding="0" cellspacing="0">
<tbody>
<tr><td class="n"><a href="galette-1.1.0.tar.bz2">galette-1.1.0.tar.bz2</a></td><td class="m">2024-Feb-16 09:09:13</td><td class="s">10M</td><td class="t">application/octet-stream</td></tr>
</tbody>
</table>
</div>
</body>
</html>' //fake 1.1.0 release
            ],
        ];
    }

    /**
     * Test findLatestRelease
     *
     * @param string $current  Current release
     * @param string $latest   Latest release
     * @param bool   $expected Expected result
     * @param string $page     Page content
     *
     * @dataProvider releasesPageProvider
     * @return void
     */
    public function testFindLatestRelease(string $current, string $latest, bool $expected, string $page): void
    {
        $release = $this->getMockBuilder(\Galette\Util\Release::class)
            ->setConstructorArgs([true])
            ->onlyMethods(array('setupClient', 'getCurrentRelease'))
            ->getMock();

        // Create a mock and queue two responses.
        $mock = new \GuzzleHttp\Handler\MockHandler([
            new \GuzzleHttp\Psr7\Response(
                200,
                ['X-Foo' => 'Bar'],
                $page
            )
        ]);

        $handlerStack = \GuzzleHttp\HandlerStack::create($mock);
        $client = new \GuzzleHttp\Client(['handler' => $handlerStack]);

        $release->method('getCurrentRelease')->willReturn($current);
        $release->method('setupClient')->willReturnCallback(function () use ($client) {
            return $client;
        });

        $this->assertSame($expected, $release->checkNewRelease());
        $this->assertSame($latest, $release->getLatestRelease());
    }
}
