<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Util;

use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Safe\DateTimeImmutable;

/**
 * Relative dates tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RelativeDate extends GaletteTestCase
{
    /**
     * Expressions, and what they resolve to on 2025-01-31
     *
     * @return array<string, array<int, string>>
     */
    public static function resolveProvider(): array
    {
        return [
            'today' => ['@today', '2025-01-31'],
            'days ahead' => ['@today+30d', '2025-03-02'],
            'days back' => ['@today-31d', '2024-12-31'],
            'weeks' => ['@today+2w', '2025-02-14'],
            'month clamped' => ['@today+1m', '2025-02-28'],
            'month next year' => ['@today+13m', '2026-02-28'],
            'years back' => ['@today-18y', '2007-01-31'],
            'month start' => ['@month_start', '2025-01-01'],
            'month end' => ['@month_end', '2025-01-31'],
            'next month end' => ['@month_end+1m', '2025-02-28'],
            'month end plus days' => ['@month_end+1d', '2025-02-01'],
            'previous month start' => ['@month_start-1m', '2024-12-01'],
            'year start' => ['@year_start', '2025-01-01'],
            'year end' => ['@year_end', '2025-12-31'],
            'previous year end' => ['@year_end-1y', '2024-12-31'],
        ];
    }

    /**
     * Test resolution
     *
     * @param string $expression Expression
     * @param string $expected   Expected date
     */
    #[DataProvider('resolveProvider')]
    public function testResolve(string $expression, string $expected): void
    {
        $now = new DateTimeImmutable('2025-01-31 15:42:00');
        $this->assertTrue(\Galette\Util\RelativeDate::isValid($expression));
        $this->assertSame(
            $expected,
            \Galette\Util\RelativeDate::resolve($expression, preferences: null, now: $now)->format('Y-m-d')
        );
    }

    /**
     * Invalid expressions
     *
     * @return array<string, array<int, string>>
     */
    public static function invalidProvider(): array
    {
        return [
            'no prefix' => ['today'],
            'unknown anchor' => ['@tomorrow'],
            'unknown unit' => ['@today+3h'],
            'no amount' => ['@today+d'],
            'no sign' => ['@today3d'],
            'too large' => ['@today+99999d'],
            'trailing' => ['@today+3d '],
            'sql' => ["@today' OR 1=1"],
        ];
    }

    /**
     * Test invalid expressions
     *
     * @param string $expression Expression
     */
    #[DataProvider('invalidProvider')]
    public function testInvalid(string $expression): void
    {
        $this->assertFalse(\Galette\Util\RelativeDate::isValid($expression));
        $this->expectException(\InvalidArgumentException::class);
        \Galette\Util\RelativeDate::resolve($expression);
    }

    /**
     * Test detection
     */
    public function testIsRelative(): void
    {
        $this->assertTrue(\Galette\Util\RelativeDate::isRelative('@today'));
        $this->assertTrue(\Galette\Util\RelativeDate::isRelative('@whatever'));
        $this->assertFalse(\Galette\Util\RelativeDate::isRelative('2025-01-31'));
        $this->assertFalse(\Galette\Util\RelativeDate::isRelative(''));
        $this->assertFalse(\Galette\Util\RelativeDate::isRelative(value: null));
        $this->assertFalse(\Galette\Util\RelativeDate::isRelative(['@today']));
    }

    /**
     * Test build
     */
    public function testBuild(): void
    {
        $this->assertSame('@today', \Galette\Util\RelativeDate::build('today'));
        $this->assertSame('@today+30d', \Galette\Util\RelativeDate::build('today', 30, 'd'));
        $this->assertSame('@period_start-1y', \Galette\Util\RelativeDate::build('period_start', -1, 'y'));

        $this->expectException(\InvalidArgumentException::class);
        \Galette\Util\RelativeDate::build('nope');
    }

    /**
     * Test membership period anchors
     */
    public function testPeriod(): void
    {
        $now = new DateTimeImmutable('2025-03-15');

        //fixed beginning of membership
        $this->setRawPreference('pref_beg_membership', '01/09');
        $this->setRawPreference('pref_membership_ext', '');
        $this->assertSame(
            '2024-09-01',
            \Galette\Util\RelativeDate::resolve('@period_start', $this->preferences, $now)->format('Y-m-d')
        );
        $this->assertSame(
            '2025-08-31',
            \Galette\Util\RelativeDate::resolve('@period_end', $this->preferences, $now)->format('Y-m-d')
        );
        $this->assertSame(
            '2024-08-31',
            \Galette\Util\RelativeDate::resolve('@period_end-1y', $this->preferences, $now)->format('Y-m-d')
        );
        $this->assertSame(
            '2025-09-01',
            \Galette\Util\RelativeDate::resolve('@period_start+1y', $this->preferences, $now)->format('Y-m-d')
        );

        //period starts on the reference day
        $this->assertSame(
            '2025-09-01',
            \Galette\Util\RelativeDate::resolve(
                '@period_start',
                $this->preferences,
                new DateTimeImmutable('2025-09-01 08:00:00')
            )->format('Y-m-d')
        );

        //rolling membership: no period
        $this->setRawPreference('pref_beg_membership', '');
        $this->setRawPreference('pref_membership_ext', 12);
        $this->assertNull($this->preferences->getMembershipPeriodStart($now));
        $this->expectException(\RuntimeException::class);
        \Galette\Util\RelativeDate::resolve('@period_start', $this->preferences, $now);
    }

    /**
     * Test description
     */
    public function testDescribe(): void
    {
        $this->i18n->changeLanguage('en_US');

        $this->assertSame('today', \Galette\Util\RelativeDate::describe('@today'));
        $this->assertSame('today plus 30 days', \Galette\Util\RelativeDate::describe('@today+30d'));
        $this->assertSame('today minus 1 year', \Galette\Util\RelativeDate::describe('@today-1y'));
        $this->assertSame(
            'end of the month plus 2 months',
            \Galette\Util\RelativeDate::describe('@month_end+2m')
        );
        $this->assertSame('@nope', \Galette\Util\RelativeDate::describe('@nope'));

        $this->assertArrayHasKey('period_start', \Galette\Util\RelativeDate::getAnchors());
        $this->assertArrayNotHasKey('period_start', \Galette\Util\RelativeDate::getAnchors(with_period: false));
    }
}
