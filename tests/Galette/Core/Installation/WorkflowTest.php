<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

namespace Galette\Core\Installation\test\units;

use Galette\Core\Install;
use Galette\Core\Installation\Workflow;
use Galette\Core\Installation\Step\CheckStep;
use Galette\Core\Installation\StepInterface;
use Galette\Core\Installation\StepResult;
use PHPUnit\Framework\TestCase;

/**
 * Workflow tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class WorkflowTest extends TestCase
{
    private Install $install;
    private Workflow $workflow;

    /**
     * Set up test
     */
    protected function setUp(): void
    {
        $this->install = new Install();
        $this->workflow = new Workflow($this->install);
    }

    /**
     * Test workflow initialization
     */
    public function testWorkflowInit(): void
    {
        $this->assertInstanceOf(Workflow::class, $this->workflow);
        $this->assertEquals(0, $this->workflow->getCurrentStepIndex());
        $this->assertEquals(0, $this->workflow->getTotalSteps());
    }

    /**
     * Test adding steps
     */
    public function testAddStep(): void
    {
        $step = new CheckStep($this->install);
        $this->workflow->addStep($step);

        $this->assertEquals(1, $this->workflow->getTotalSteps());
        $this->assertInstanceOf(StepInterface::class, $this->workflow->getCurrentStep());
    }

    /**
     * Test navigation
     */
    public function testNavigation(): void
    {
        $step1 = new CheckStep($this->install);
        $step2 = new CheckStep($this->install);

        $this->workflow->addStep($step1)->addStep($step2);

        $this->assertEquals(0, $this->workflow->getCurrentStepIndex());
        $this->assertTrue($this->workflow->hasNext());
        $this->assertFalse($this->workflow->hasPrevious());

        $this->workflow->advance();
        $this->assertEquals(1, $this->workflow->getCurrentStepIndex());
        $this->assertFalse($this->workflow->hasNext());
        $this->assertTrue($this->workflow->hasPrevious());

        $this->workflow->goBack();
        $this->assertEquals(0, $this->workflow->getCurrentStepIndex());
    }

    /**
     * Test context management
     */
    public function testContextManagement(): void
    {
        $context = ['key' => 'value'];
        $this->workflow->setContext($context);

        $this->assertEquals($context, $this->workflow->getContext());
    }

    /**
     * Test workflow completion
     */
    public function testIsComplete(): void
    {
        $step = new CheckStep($this->install);
        $this->workflow->addStep($step);

        $this->assertFalse($this->workflow->isComplete());

        // Cannot advance past the last step - there's no next step
        $canAdvance = $this->workflow->advance();
        $this->assertFalse($canAdvance);
        
        // But we're now at the end (no next step, but we've started)
        // Actually, we need to be PAST the last step to be complete
        // With only 1 step, currentStepIndex=0, hasNext=false but we haven't advanced yet
        // So isComplete requires: !hasNext AND currentStepIndex > 0
        
        // Add another step so we can advance
        $step2 = new CheckStep($this->install);
        $this->workflow->addStep($step2);
        
        // Now we can advance
        $this->workflow->advance();
        $this->assertTrue($this->workflow->isComplete());
    }

    /**
     * Test jump to step
     */
    public function testJumpToStep(): void
    {
        $step1 = new CheckStep($this->install);
        $this->workflow->addStep($step1);

        $result = $this->workflow->jumpToStep(CheckStep::STEP_NAME);
        $this->assertTrue($result);
        $this->assertEquals(0, $this->workflow->getCurrentStepIndex());

        $result = $this->workflow->jumpToStep('nonexistent');
        $this->assertFalse($result);
    }
}


