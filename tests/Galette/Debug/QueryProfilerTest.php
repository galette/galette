<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Debug;

use Galette\Debug\QueryProfiler;
use PHPUnit\Framework\TestCase;

/**
 * Debug bar SQL profiler tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class QueryProfilerTest extends TestCase
{
    /**
     * A finished query has an end and knows where it came from
     */
    public function testFinishedQuery(): void
    {
        $profiler = new QueryProfiler();
        $profiler->profilerStart('SELECT 1');
        $profiler->profilerFinish();

        $profiles = $profiler->getProfiles();
        $this->assertCount(1, $profiles);
        $this->assertSame('SELECT 1', $profiles[0]['sql']);
        $this->assertNotNull($profiles[0]['end']);
        $this->assertGreaterThanOrEqual($profiles[0]['start'], $profiles[0]['end']);
        $this->assertNotNull($profiles[0]['source']);
        $this->assertSame(__FILE__, $profiles[0]['source']['file']);
    }

    /**
     * A query that never finished (it threw) is kept, without an end
     */
    public function testFailedQueryIsKept(): void
    {
        $profiler = new QueryProfiler();
        $profiler->profilerStart('SELECT broken');
        $profiler->profilerStart('SELECT 2');
        $profiler->profilerFinish();

        $profiles = $profiler->getProfiles();
        $this->assertCount(2, $profiles);
        $this->assertSame('SELECT broken', $profiles[0]['sql']);
        $this->assertNull($profiles[0]['end']);
        $this->assertSame('SELECT 2', $profiles[1]['sql']);
        $this->assertNotNull($profiles[1]['end']);
    }

    /**
     * A query that failed in Db::execute() keeps its duration and message
     */
    public function testFailedQueryHasDurationAndError(): void
    {
        $profiler = new QueryProfiler();
        $mark = $profiler->getMark();
        $profiler->profilerStart('SELECT broken');
        usleep(2000);
        $profiler->profilerFailed('Table does not exist', $mark);

        $profile = $profiler->getProfiles()[0];
        $this->assertNotNull($profile['end']);
        $this->assertGreaterThan(0.001, $profile['end'] - $profile['start']);
        $this->assertSame('Table does not exist', $profile['error']);
    }

    /**
     * When the driver already finished the query, the error goes to the last one
     */
    public function testFailureAfterFinish(): void
    {
        $profiler = new QueryProfiler();
        $profiler->profilerStart('SELECT 1');
        $profiler->profilerFinish();
        $mark = $profiler->getMark();
        $profiler->profilerStart('SELECT broken');
        $profiler->profilerFinish();
        $profiler->profilerFailed('Invalid query', $mark);

        $profiles = $profiler->getProfiles();
        $this->assertNull($profiles[0]['error']);
        $this->assertSame('Invalid query', $profiles[1]['error']);
    }

    /**
     * A failure before any query started leaves the previous one alone
     */
    public function testFailureBeforeQueryStarted(): void
    {
        $profiler = new QueryProfiler();
        $profiler->profilerStart('SELECT 1');
        $profiler->profilerFinish();

        //SQL building fails: nothing reaches the adapter
        $profiler->profilerFailed('Cannot build query', $profiler->getMark());

        $profiles = $profiler->getProfiles();
        $this->assertCount(1, $profiles);
        $this->assertNull($profiles[0]['error']);
    }

    /**
     * Finishing without a started query does nothing
     */
    public function testFinishWithoutStart(): void
    {
        $profiler = new QueryProfiler();
        $profiler->profilerFinish();
        $this->assertSame([], $profiler->getProfiles());
    }
}
