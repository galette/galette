<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\BaseGaletteTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

use function Safe\file_get_contents;
use function Safe\json_decode;

/**
 * CheckModules tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CheckModules extends BaseGaletteTestCase
{
    /**
     * Test modules, all should be ok
     */
    public function testAllOK(): void
    {
        $checks = new \Galette\Core\CheckModules();
        $this->assertTrue($checks->isValid());
        $this->assertGreaterThanOrEqual(12, count($checks->getGoods()));
        $this->assertLessThanOrEqual(14, count($checks->getGoods()));
        $this->assertSame([], $checks->getMissings());
        $this->assertSame([], $checks->getShoulds());
        $this->assertTrue($checks->isGood('mbstring'));
    }

    /**
     * Test all extensions missing
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testAllKO(): void
    {
        $checks = $this->getMockBuilder(\Galette\Core\CheckModules::class)
            ->setConstructorArgs([false])
            ->onlyMethods(['isExtensionLoaded'])
            ->getMock();
        $checks->method('isExtensionLoaded')->willReturn(false);

        $checks->doCheck(false);
        $this->assertSame(0, count($checks->getGoods()));
        $this->assertSame(2, count($checks->getShoulds()));
        $this->assertSame(12, count($checks->getMissings()));

        $html = $checks->toHtml();
        $this->assertStringNotContainsString('green check icon', $html);
        $this->assertSame(1946, strlen($html));
    }

    /**
     * Compatibility checks must stay in sync with composer.json
     *
     * composer.json is dropped from release archives, so the checks cannot read
     * it at runtime; this test is what keeps both lists from drifting apart.
     */
    public function testComposerRequirements(): void
    {
        $composer = json_decode(
            file_get_contents(GALETTE_ROOT . '../composer.json'),
            associative: true
        );

        $this->assertSame(
            '>=' . GALETTE_PHP_MIN,
            $composer['require']['php'],
            'GALETTE_PHP_MIN and composer.json PHP constraint differ'
        );

        $checked = [];
        foreach ((new \Galette\Core\CheckModules(do: false))->getModules() as $name => $required) {
            $checked[strtolower($name)] = $required;
        }

        $declared = [];
        foreach (array_keys($composer['require']) as $package) {
            if (str_starts_with($package, 'ext-')) {
                $declared[] = substr($package, 4);
            }
        }

        //declared in composer.json, but Galette runs without them: only the
        //optional features that use them (external scripts, telemetry) do not.
        $recommended = ['curl'];

        foreach ($declared as $extension) {
            $this->assertArrayHasKey(
                $extension,
                $checked,
                sprintf('%s is required by composer.json but not checked', $extension)
            );
            $this->assertSame(
                !in_array($extension, $recommended, strict: true),
                $checked[$extension],
                sprintf('%s is not required the same way in composer.json and in checks', $extension)
            );
        }

        //modules checked without being declared in composer.json: either required
        //by a dependency rather than by Galette itself, or only recommended.
        $this->assertSame(
            [
                'ctype',    //laminas-escaper, laminas-i18n
                'dom',      //league/html-to-markdown
                'iconv',    //bacon/bacon-qr-code
                'openssl'   //recommended, for mail encryption
            ],
            array_values(array_diff(array_keys($checked), $declared))
        );
    }

    /**
     * Test HTMl output
     */
    public function testToHtml(): void
    {
        $checks = new \Galette\Core\CheckModules();
        $checks->doCheck();
        $html = $checks->toHtml();
        $this->assertStringNotContainsString('icon-invalid.png', $html);
        $this->assertGreaterThanOrEqual(1970, strlen($html));
    }
}
