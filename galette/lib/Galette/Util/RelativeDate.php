<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Util;

use Galette\Core\Preferences;
use InvalidArgumentException;
use RuntimeException;
use Safe\DateTimeImmutable;

use function Safe\preg_match;

/**
 * Dates relative to the day a search is run
 *
 * A saved search must not freeze "due within 30 days" on the day it has been
 * saved. Such a bound is kept as an expression, resolved each time the search
 * runs: an anchor, optionally shifted by a number of days, weeks, months or
 * years - "@today+30d", "@today-18y", "@period_start".
 *
 * The expression is language neutral, so a search saved in one language can
 * be replayed in another one.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RelativeDate
{
    public const string PREFIX = '@';

    public const string TODAY = 'today';
    public const string MONTH_START = 'month_start';
    public const string MONTH_END = 'month_end';
    public const string YEAR_START = 'year_start';
    public const string YEAR_END = 'year_end';
    public const string PERIOD_START = 'period_start';
    public const string PERIOD_END = 'period_end';

    public const string UNIT_DAY = 'd';
    public const string UNIT_WEEK = 'w';
    public const string UNIT_MONTH = 'm';
    public const string UNIT_YEAR = 'y';

    /** Largest shift accepted, whatever the unit */
    public const int MAX_AMOUNT = 9999;

    private const string PATTERN = '/^@(today|month_start|month_end|year_start|year_end|period_start|period_end)'
        . '(?:([+-])(\d{1,4})([dwmy]))?$/';

    /**
     * Is value a relative date expression?
     *
     * Only checks the prefix: a value that claims to be relative but does not
     * parse must be reported, not taken for a regular date.
     *
     * @param mixed $value Value to check
     */
    public static function isRelative(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    /**
     * Is expression valid?
     *
     * @param string $expression Expression to check
     */
    public static function isValid(string $expression): bool
    {
        try {
            self::parse($expression);
            return true;
        } catch (InvalidArgumentException) { // no log: invalid expression is the answer
            return false;
        }
    }

    /**
     * Split an expression into its parts
     *
     * @param string $expression Expression to parse
     *
     * @return array{anchor: string, amount: int, unit: ?string}
     *
     * @throws InvalidArgumentException
     */
    public static function parse(string $expression): array
    {
        if (!preg_match(self::PATTERN, $expression, $matches)) {
            throw new InvalidArgumentException('Invalid relative date expression: ' . $expression);
        }

        $amount = isset($matches[3]) ? (int)$matches[3] : 0;
        if ($amount > self::MAX_AMOUNT) {
            throw new InvalidArgumentException('Relative date shift is too large: ' . $expression);
        }
        if (($matches[2] ?? '') === '-') {
            $amount = -$amount;
        }

        return [
            'anchor' => $matches[1],
            'amount' => $amount,
            'unit' => $matches[4] ?? null,
        ];
    }

    /**
     * Build an expression from its parts
     *
     * @param string  $anchor Anchor
     * @param int     $amount Signed shift; 0 for none
     * @param ?string $unit   Unit of the shift
     *
     * @throws InvalidArgumentException
     */
    public static function build(string $anchor, int $amount = 0, ?string $unit = null): string
    {
        $expression = self::PREFIX . $anchor;
        if ($amount !== 0) {
            $expression .= ($amount > 0 ? '+' : '-') . abs($amount) . $unit;
        }
        //validate what has been built
        self::parse($expression);

        return $expression;
    }

    /**
     * Resolve an expression into a date
     *
     * Months and years are added calendar-wise: the day is kept, and clamped
     * on the last day of a shorter month (January 31st plus one month is
     * February 28th or 29th, not March 3rd). An end anchor stays an end:
     * "@month_end+1m" is the last day of next month.
     *
     * @param string              $expression  Expression to resolve
     * @param ?Preferences        $preferences Preferences, required by period anchors
     * @param ?\DateTimeInterface $now         Reference date, defaults to today
     *
     * @throws InvalidArgumentException when expression is invalid
     * @throws RuntimeException when membership period is not defined
     */
    public static function resolve(
        string $expression,
        ?Preferences $preferences = null,
        ?\DateTimeInterface $now = null
    ): DateTimeImmutable {
        ['anchor' => $anchor, 'amount' => $amount, 'unit' => $unit] = self::parse($expression);
        $today = DateTimeImmutable::createFromInterface($now ?? new DateTimeImmutable())->setTime(0, 0);

        $is_end = in_array($anchor, [self::MONTH_END, self::YEAR_END, self::PERIOD_END], strict: true);
        $shift_end = $is_end && in_array($unit, [self::UNIT_MONTH, self::UNIT_YEAR], strict: true);

        //an end anchor shifted by months or years is computed from the start
        //of its range, so that it is still an end once shifted
        $date = match ($anchor) {
            self::TODAY => $today,
            self::MONTH_START => $today->modify('first day of this month'),
            self::MONTH_END => $shift_end
                ? $today->modify('first day of this month')
                : $today->modify('last day of this month'),
            self::YEAR_START => $today->setDate((int)$today->format('Y'), 1, 1),
            self::YEAR_END => $shift_end
                ? $today->setDate((int)$today->format('Y'), 1, 1)
                : $today->setDate((int)$today->format('Y'), 12, 31),
            self::PERIOD_START, self::PERIOD_END => self::getPeriodStart($preferences, $today),
            default => throw new InvalidArgumentException('Unknown anchor: ' . $anchor),
        };

        if ($anchor === self::PERIOD_END && !$shift_end) {
            $date = self::endOfRange($date, 12);
        }

        if ($amount !== 0) {
            $date = match ($unit) {
                self::UNIT_DAY => $date->modify(sprintf('%+d days', $amount)),
                self::UNIT_WEEK => $date->modify(sprintf('%+d weeks', $amount)),
                self::UNIT_MONTH => self::addMonths($date, $amount),
                self::UNIT_YEAR => self::addMonths($date, $amount * 12),
                default => throw new InvalidArgumentException('Unknown unit: ' . $unit),
            };
        }

        if ($shift_end) {
            $date = self::endOfRange($date, $anchor === self::MONTH_END ? 1 : 12);
        }

        return $date;
    }

    /**
     * Human readable expression
     *
     * @param string $expression Expression to describe
     */
    public static function describe(string $expression): string
    {
        try {
            ['anchor' => $anchor, 'amount' => $amount, 'unit' => $unit] = self::parse($expression);
        } catch (InvalidArgumentException) { // no log: shown as is
            return $expression;
        }

        $label = self::getAnchors()[$anchor];
        if ($amount === 0) {
            return $label;
        }

        $count = abs($amount);
        $shift = match ($unit) {
            self::UNIT_DAY => _Tn('%1$s day', '%1$s days', $count),
            self::UNIT_WEEK => _Tn('%1$s week', '%1$s weeks', $count),
            self::UNIT_MONTH => _Tn('%1$s month', '%1$s months', $count),
            default => _Tn('%1$s year', '%1$s years', $count),
        };
        $shift = sprintf($shift, $count);

        return sprintf(
            $amount > 0
                //TRANS: %1$s is a reference date (today, start of month, ...), %2$s a duration (30 days, ...)
                ? _T('%1$s plus %2$s')
                //TRANS: %1$s is a reference date (today, start of month, ...), %2$s a duration (30 days, ...)
                : _T('%1$s minus %2$s'),
            $label,
            $shift
        );
    }

    /**
     * Known anchors, with their labels
     *
     * @param bool $with_period Include membership period anchors
     *
     * @return array<string, string>
     */
    public static function getAnchors(bool $with_period = true): array
    {
        $anchors = [
            self::TODAY => _T('today'),
            self::MONTH_START => _T('start of the month'),
            self::MONTH_END => _T('end of the month'),
            self::YEAR_START => _T('start of the year'),
            self::YEAR_END => _T('end of the year'),
        ];
        if ($with_period) {
            $anchors[self::PERIOD_START] = _T('start of the membership period');
            $anchors[self::PERIOD_END] = _T('end of the membership period');
        }

        return $anchors;
    }

    /**
     * Known units, with their labels
     *
     * @return array<string, string>
     */
    public static function getUnits(): array
    {
        return [
            self::UNIT_DAY => _T('days'),
            self::UNIT_WEEK => _T('weeks'),
            self::UNIT_MONTH => _T('months'),
            self::UNIT_YEAR => _T('years'),
        ];
    }

    /**
     * Start of current membership period
     *
     * @param ?Preferences      $preferences Preferences
     * @param DateTimeImmutable $today       Reference date
     *
     * @throws RuntimeException
     */
    private static function getPeriodStart(?Preferences $preferences, DateTimeImmutable $today): DateTimeImmutable
    {
        return $preferences?->getMembershipPeriodStart($today)
            ?? throw new RuntimeException('Membership period is not defined.');
    }

    /**
     * Add months, clamping the day on shorter months
     *
     * @param DateTimeImmutable $date   Date
     * @param int               $months Months to add, may be negative
     */
    private static function addMonths(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        $total = (int)$date->format('Y') * 12 + (int)$date->format('n') - 1 + $months;
        $first = $date->setDate(intdiv($total, 12), $total % 12 + 1, 1);
        $day = min((int)$date->format('j'), (int)$first->format('t'));

        return $first->setDate((int)$first->format('Y'), (int)$first->format('n'), $day);
    }

    /**
     * Last day of a range of months starting on a date
     *
     * @param DateTimeImmutable $start  First day of the range
     * @param int               $months Length of the range, in months
     */
    private static function endOfRange(DateTimeImmutable $start, int $months): DateTimeImmutable
    {
        return self::addMonths($start, $months)->modify('-1 day');
    }
}
