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
     * Helper to create a mock of FeatureFlagManager
     *
     * @param string[] $declarations Flags declared in configuration
     * @param string[] $stored       Flags stored in database
     * @param bool     $locked       Whether GALETTE_FEATURE_FLAGS is declared
     */
    private function getManagerMock(
        array $declarations = ['acls', 'api-v2'],
        array $stored = [],
        bool $locked = false
    ): FeatureFlagManager {
        $manager = $this->getMockBuilder(FeatureFlagManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConfiguration', 'getDeclarations', 'getStoredDeclarations', 'isLockedByConstant'])
            ->getMock();

        $config = include GALETTE_TESTS_PATH . '/fixtures/feature_flags.inc.php';
        $manager->method('getConfiguration')->willReturn($config);
        $manager->method('getDeclarations')->willReturn($declarations);
        $manager->method('getStoredDeclarations')->willReturn($stored);
        $manager->method('isLockedByConstant')->willReturn($locked);
        $manager->load();
        return $manager;
    }

    /**
     * A flag nobody turned on is disabled
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testUndeclaredFlagIsDisabled(): void
    {
        $manager = $this->getManagerMock(declarations: []);

        $this->assertFalse($manager->isEnabled('acls'));
        $this->assertNull($manager->getSource('acls'));
    }

    /**
     * A flag used in code but missing from the registry is reported
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testUnregisteredFlag(): void
    {
        $manager = $this->getManagerMock(declarations: []);

        $this->assertFalse($manager->isEnabled('unknown'));
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Feature flag "unknown" is used in code but not registered in feature_flags.inc.php. Please add it to the registry.'
        );
    }

    /**
     * Test getting all declared flags
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetDeclaredFlags(): void
    {
        $manager = $this->getManagerMock();
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
        $manager = $this->getManagerMock();

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

        $this->assertSame(FeatureFlagManager::SOURCE_CONSTANT, $flagsWithStatus['acls']['source']);
        $this->assertNull($flagsWithStatus['oauth2']['source']);
    }

    /**
     * A flag whose dependencies are not all turned on is disabled
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testUnsatisfiedDependencies(): void
    {
        $manager = $this->getManagerMock();

        $this->assertTrue($manager->isEnabled('acls'));
        $this->assertFalse($manager->isEnabled('api-v2'));
        $this->assertSame(['oauth2'], $manager->getMissingDependencies('api-v2'));
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Feature flag "api-v2" is enabled but has unsatisfied dependencies: oauth2'
        );
    }

    /**
     * A flag stored in database is enabled
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testStoredFlagIsEnabled(): void
    {
        $manager = $this->getManagerMock(declarations: [], stored: ['acls']);

        $this->assertTrue($manager->isEnabled('acls'));
        $this->assertSame(FeatureFlagManager::SOURCE_DATABASE, $manager->getSource('acls'));
        $this->assertSame(['acls'], $manager->getStoredFlags());
    }

    /**
     * A stored flag that left the registry is ignored, and dropped on next store
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testStoredUnregisteredFlagIsIgnored(): void
    {
        $manager = $this->getManagerMock(declarations: [], stored: ['acls', 'gone']);

        $this->assertSame(['acls'], $manager->getStoredFlags());
        $this->assertNull($manager->getSource('gone'));
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Feature flag "gone" is stored as enabled but is not registered; it is ignored.'
        );

        $change = $manager->computeTurnOn('oauth2');
        $this->assertSame(['acls', 'oauth2'], $change['flags']);
    }

    /**
     * The constant takes precedence over what is stored
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testConstantTakesPrecedenceOverStored(): void
    {
        $manager = $this->getManagerMock(declarations: [], stored: ['acls'], locked: true);

        $this->assertFalse($manager->isEnabled('acls'));
        $this->assertSame([], $manager->getStoredFlags());

        foreach (['computeTurnOn', 'computeTurnOff'] as $method) {
            try {
                $manager->$method('acls');
                $this->fail(sprintf('%s must be refused while the constant is declared', $method));
            } catch (\DomainException $e) {
                $this->assertSame(FeatureFlagManager::ERR_LOCKED, $e->getCode());
            }
        }
    }

    /**
     * Turning a feature on turns on the ones it requires
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testComputeTurnOn(): void
    {
        $manager = $this->getManagerMock(declarations: []);

        $change = $manager->computeTurnOn('Api-V2');
        $this->assertSame(['api-v2', 'acls', 'oauth2'], $change['added']);
        $this->assertSame(['api-v2', 'acls', 'oauth2'], $change['flags']);

        $manager = $this->getManagerMock(declarations: [], stored: ['acls']);
        $change = $manager->computeTurnOn('oauth2');
        $this->assertSame(['oauth2'], $change['added']);
        $this->assertSame(['acls', 'oauth2'], $change['flags']);

        try {
            $manager->computeTurnOn('unknown');
            $this->fail('Turning an unregistered flag on must be refused');
        } catch (\DomainException $e) {
            $this->assertSame(FeatureFlagManager::ERR_UNKNOWN, $e->getCode());
        }
    }

    /**
     * Turning a feature off turns off the ones requiring it
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testComputeTurnOff(): void
    {
        $manager = $this->getManagerMock(declarations: [], stored: ['acls', 'oauth2', 'api-v2']);

        $change = $manager->computeTurnOff('acls');
        $this->assertSame(['acls', 'oauth2', 'api-v2'], $change['removed']);
        $this->assertSame([], $change['flags']);

        $change = $manager->computeTurnOff('api-v2');
        $this->assertSame(['api-v2'], $change['removed']);
        $this->assertSame(['acls', 'oauth2'], $change['flags']);

        //nothing stored, nothing removed
        $change = $manager->computeTurnOff('new-dashboard');
        $this->assertSame([], $change['removed']);
    }

    /**
     * Flags stored through the preferences are read back
     */
    public function testStoredThroughPreferences(): void
    {
        $preferences = $this->container->get(\Galette\Core\Preferences::class);
        $this->assertSame([], $preferences->getFeatureFlags());
        $this->assertTrue($preferences->storeFeatureFlags(['Two-Factor-Required', 'two-factor-required']));
        $this->assertSame('two-factor-required', $preferences->pref_feature_flags);

        $prefs = new \Galette\Core\Preferences($this->zdb);
        $this->assertSame(['two-factor-required'], $prefs->getFeatureFlags());

        $manager = new FeatureFlagManager($prefs);
        $this->assertTrue($manager->isEnabled('two-factor-required'));
        $this->assertSame(FeatureFlagManager::SOURCE_DATABASE, $manager->getSource('two-factor-required'));

        $this->assertTrue($preferences->storeFeatureFlags([]));
        $manager = new FeatureFlagManager(new \Galette\Core\Preferences($this->zdb));
        $this->assertFalse($manager->isEnabled('two-factor-required'));
    }

    /**
     * Test that flag names are case-insensitive
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testFlagNamesAreCaseInsensitive(): void
    {
        $manager = $this->getManagerMock(declarations: ['ACLS']);

        $this->assertTrue($manager->isEnabled('acls'));
        $this->assertTrue($manager->isEnabled('ACLS'));
        $this->assertTrue($manager->isEnabled('AcLs'));
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
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Feature flag "non-existent-flag-xyz" is used in code but not registered in feature_flags.inc.php. Please add it to the registry.'
        );
    }
}
