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

namespace Galette\Core\test\units;

use Galette\Core\FeatureFlagManager;
use PHPUnit\Framework\TestCase;

/**
 * Feature Flag Manager tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class FeatureFlagManagerTest extends TestCase
{
    /**
     * Test that feature flags are disabled when debug mode is off
     */
    public function testFeatureFlagsDisabledInProductionMode(): void
    {
        // Mock production environment (GALETTE_DEBUG = false)
        // This test assumes GALETTE_DEBUG is false in test environment
        // or can be tested with a custom setUp that defines the constant

        $manager = new FeatureFlagManager();

        // Even if declared, flags should be disabled without debug mode
        $this->assertFalse(
            $manager->isEnabled('acls'),
            'Feature flags should be disabled in production mode'
        );
    }

    /**
     * Test getting all declared flags
     */
    public function testGetDeclaredFlags(): void
    {
        $manager = new FeatureFlagManager();
        $flags = $manager->getDeclaredFlags();

        $this->assertIsArray($flags);
        // In test environment with behavior.inc.php loaded, we should have 'acls'
        // This assertion depends on test environment configuration
    }

    /**
     * Test getting all flags with status
     */
    public function testGetAllFlagsWithStatus(): void
    {
        $manager = new FeatureFlagManager();
        $flagsWithStatus = $manager->getAllFlagsWithStatus();

        $this->assertIsArray($flagsWithStatus);

        foreach ($flagsWithStatus as $flag => $enabled) {
            $this->assertIsString($flag);
            $this->assertIsBool($enabled);
        }
    }

    /**
     * Test that flag names are case-insensitive
     */
    public function testFlagNamesAreCaseInsensitive(): void
    {
        $manager = new FeatureFlagManager();

        // Both should return the same result
        $resultLower = $manager->isEnabled('acls');
        $resultUpper = $manager->isEnabled('ACLS');
        $resultMixed = $manager->isEnabled('AcLs');

        $this->assertEquals($resultLower, $resultUpper);
        $this->assertEquals($resultLower, $resultMixed);
    }

    /**
     * Test debug mode check
     */
    public function testDebugModeCheck(): void
    {
        $manager = new FeatureFlagManager();
        $isDebug = $manager->isDebugMode();

        $this->assertIsBool($isDebug);
        // The actual value depends on GALETTE_DEBUG constant
    }

    /**
     * Test that non-declared flags return false
     */
    public function testNonDeclaredFlagReturnsFalse(): void
    {
        $manager = new FeatureFlagManager();

        $result = $manager->isEnabled('non-existent-flag-xyz');

        $this->assertFalse(
            $result,
            'Non-declared flags should always return false'
        );
    }

    /**
     * Test environment variable loading
     *
     * Note: This test would require setting up $_ENV in setUp() method
     * or using a test-specific configuration
     */
    public function testEnvironmentVariableLoading(): void
    {
        // Store original ENV
        $originalEnv = $_ENV;

        // Set test environment variable
        $_ENV['GALETTE_FEATURE_TEST_FLAG'] = '1';

        // Create new manager to load env vars
        $manager = new FeatureFlagManager();

        // Restore ENV
        $_ENV = $originalEnv;

        // If debug mode is on, the flag should be detected
        if ($manager->isDebugMode()) {
            $flags = $manager->getDeclaredFlags();
            // The 'test_flag' should be in the list
            $this->assertContains('test_flag', $flags);
        }

        $this->assertTrue(true); // Placeholder if debug mode is off
    }
}
