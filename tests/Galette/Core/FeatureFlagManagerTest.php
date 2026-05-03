<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\test\units;

use Galette\Core\FeatureFlagManager;
use Galette\Tests\BaseGaletteTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Feature Flag Manager tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class FeatureFlagManagerTest extends BaseGaletteTestCase
{
    /**
     * Helper to create a mock of FeatureFlagManager with specified debug mode
     *
     * @param string[] $methods
     */
    private function getManagerMock(bool $debug_on, array $methods = []): FeatureFlagManager
    {
        $manager = $this->getMockBuilder(FeatureFlagManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isDebugMode', 'getConfiguration', 'getDeclarations'] + $methods)
            ->getMock();
        $manager->method('isDebugMode')->willReturn($debug_on);

        $config = include GALETTE_TESTS_PATH . '/fixtures/feature_flags.inc.php';
        $manager->method('getConfiguration')->willReturn($config);
        $manager->method('getDeclarations')->willReturn(['acls', 'api-v2']);
        $manager->load();
        return $manager;
    }

    /**
     * Test that feature flags are disabled when debug mode is off
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testFeatureFlagsDisabledInProductionMode(): void
    {
        $manager = $this->getManagerMock(false);

        // Even if declared, flags should be disabled without debug mode
        $this->assertFalse(
            $manager->isEnabled('acls'),
            'Feature flags should be disabled in production mode'
        );
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Feature flag "acls" is declared but cannot be enabled in production mode. Set GALETTE_DEBUG=true to enable feature flags.'
        );
    }

    /**
     * Test getting all declared flags
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetDeclaredFlags(): void
    {
        $manager = $this->getManagerMock(true);
        $flags = $manager->getDeclaredFlags();

        $this->assertEquals(
            [
                'acls',
                'api-v2'
            ],
            $flags
        );
    }

    /**
     * Test getting all flags with status
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetAllFlagsWithStatus(): void
    {
        $manager = $this->getManagerMock(true);

        $flagsWithStatus = $manager->getAllFlagsWithStatus();
        $this->assertCount(4, $flagsWithStatus);

        $this->assertArrayHasKey('acls', $flagsWithStatus);
        $this->assertTrue($flagsWithStatus['acls']['enabled'], '"acls" feature flag should be enabled');
        $this->assertTrue($flagsWithStatus['acls']['declared'], '"acls" feature flag should be declared');
        $this->assertTrue($flagsWithStatus['acls']['dependencies_satisfied'], '"acls" feature flag dependencies should be met');

        $this->assertArrayHasKey('oauth2', $flagsWithStatus);
        $this->assertFalse($flagsWithStatus['oauth2']['enabled'], '"oauth2" feature flag should not be enabled');
        $this->assertFalse($flagsWithStatus['oauth2']['declared'], '"oauth2" feature flag should not be declared');

        $this->assertArrayHasKey('new-dashboard', $flagsWithStatus);
        $this->assertFalse($flagsWithStatus['new-dashboard']['enabled'], '"new-dashboard" feature flag should not be enabled');
        $this->assertFalse($flagsWithStatus['new-dashboard']['declared'], '"new-dashboard" feature flag should not be declared');

        $this->assertArrayHasKey('api-v2', $flagsWithStatus);
        $this->assertFalse($flagsWithStatus['api-v2']['enabled'], '"api-v2" feature flag should not be enabled, dep is missing');
        $this->assertTrue($flagsWithStatus['api-v2']['declared'], '"api-v2" feature flag should be declared');
        $this->assertFalse($flagsWithStatus['api-v2']['dependencies_satisfied'], '"api-v2" feature flag dependencies should not be met');
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
}
