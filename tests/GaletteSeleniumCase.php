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

declare(strict_types=1);

namespace Galette;

use PHPUnit\Framework\TestCase;

/**
 * Galette selenium tests case main class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
abstract class GaletteSeleniumCase extends GaletteTestCase
{
    protected static $driver;
    protected static $bs_local;

    public static function setUpBeforeClass(): void
    {
        $CONFIG = $GLOBALS['CONFIG'];
        $task_id = getenv('TASK_ID') ? getenv('TASK_ID') : 0;

        $url = "https://" . $GLOBALS['BROWSERSTACK_USERNAME'] . ":" . $GLOBALS['BROWSERSTACK_ACCESS_KEY'] . "@" . $CONFIG['server'] ."/wd/hub";
        $caps = $CONFIG['environments'][$task_id];

        foreach ($CONFIG["capabilities"] as $key => $value) {
            if(!array_key_exists($key, $caps))
                $caps[$key] = $value;
        }

        if(array_key_exists("browserstack.local", $caps) && $caps["browserstack.local"])
        {
            $bs_local_args = array("key" => $GLOBALS['BROWSERSTACK_ACCESS_KEY']);
            self::$bs_local = new BrowserStack\Local();
            self::$bs_local->start($bs_local_args);
        }

        self::$driver = RemoteWebDriver::create($url, $caps);

        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass(): void
    {
        self::$driver->quit();
        if(self::$bs_local) self::$bs_local->stop();
        parent::tearDownAfterClass();
    }

    public function testGoogle() {
        self::$driver->get("https://www.google.com/ncr");
        $element = self::$driver->findElement(WebDriverBy::name("q"));
        $element->sendKeys("BrowserStack");
        $element->submit();
        $this->assertEquals('BrowserStack - Google Search', self::$driver->getTitle());
    }
}
