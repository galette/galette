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
     * @param bool     $debug_on     Debug mode
     * @param string[] $declarations Flags declared in configuration
     * @param string[] $stored       Flags stored in database
     * @param bool     $locked       Whether GALETTE_FEATURE_FLAGS is declared
     */
    private function getManagerMock(
        bool $debug_on,
        array $declarations = ['acls', 'api-v2'],
        array $stored = [],
        bool $locked = false
    ): FeatureFlagManager {
        $manager = $this->getMockBuilder(FeatureFlagManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isDebugMode', 'getConfiguration', 'getDeclarations', 'getStoredDeclarations', 'isLockedByConstant'])
            ->getMock();
        $manager->method('isDebugMode')->willReturn($debug_on);

        $config = include GALETTE_TESTS_PATH . '/fixtures/feature_flags.inc.php';
        $manager->method('getConfiguration')->willReturn($config);
        $manager->method('getDeclarations')->willReturn($declarations);
        $manager->method('getStoredDeclarations')->willReturn($stored);
        $manager->method('isLockedByConstant')->willReturn($locked);
        $manager->load();
        return $manager;
    }

    /**
     * Test that feature flags are disabled when debug mode is off
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testFeatureFlagsDisabledInProductionMode(): void
    {
        $manager = $this->getManagerMock(debug_on: false);

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
        $manager = $this->getManagerMock(debug_on: true);
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
        $manager = $this->getManagerMock(debug_on: true);

        $flagsWithStatus = $manager->getAllFlagsWithStatus();
        $this->assertCount(7, $flagsWithStatus);

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

        $this->assertSame(FeatureFlagManager::STAGE_DEV, $flagsWithStatus['acls']['stage']);
        $this->assertSame(FeatureFlagManager::SOURCE_CONSTANT, $flagsWithStatus['acls']['source']);
        $this->assertSame(FeatureFlagManager::STAGE_PREVIEW, $flagsWithStatus['preview-feature']['stage']);
        $this->assertNull($flagsWithStatus['preview-feature']['source']);
    }

    /**
     * A preview feature stored in database works without debug mode
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testStoredPreviewEnabledInProductionMode(): void
    {
        $manager = $this->getManagerMock(debug_on: false, declarations: [], stored: ['preview-feature']);

        $this->assertTrue($manager->isEnabled('preview-feature'));
        $this->assertSame(FeatureFlagManager::SOURCE_DATABASE, $manager->getSource('preview-feature'));
        $this->assertSame(['preview-feature'], $manager->getStoredFlags());
    }

    /**
     * A preview feature declared in configuration works without debug mode
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testDeclaredPreviewEnabledInProductionMode(): void
    {
        $manager = $this->getManagerMock(debug_on: false, declarations: ['preview-feature']);

        $this->assertTrue($manager->isEnabled('preview-feature'));
        $this->assertSame(FeatureFlagManager::SOURCE_CONSTANT, $manager->getSource('preview-feature'));
    }

    /**
     * Only preview features are read from database
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testStoredDevAndUnknownFlagsAreIgnored(): void
    {
        $manager = $this->getManagerMock(debug_on: true, declarations: [], stored: ['acls', 'gone']);

        $this->assertFalse($manager->isEnabled('acls'));
        $this->assertSame([], $manager->getStoredFlags());
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Feature flag "acls" is stored as enabled but is not a preview feature; it is ignored.'
        );
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Feature flag "gone" is stored as enabled but is not a preview feature; it is ignored.'
        );
    }

    /**
     * The constant takes precedence over what is stored
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testConstantTakesPrecedenceOverStored(): void
    {
        $manager = $this->getManagerMock(debug_on: false, declarations: [], stored: ['preview-feature'], locked: true);

        $this->assertFalse($manager->isEnabled('preview-feature'));
        $this->assertSame([], $manager->getStoredFlags());

        try {
            $manager->computeTurnOn('preview-feature');
            $this->fail('Turning a flag on must be refused while the constant is declared');
        } catch (\DomainException $e) {
            $this->assertSame(FeatureFlagManager::ERR_LOCKED, $e->getCode());
        }
    }

    /**
     * A preview feature requiring one in development is off without debug mode
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testPreviewRequiringDevFlag(): void
    {
        $manager = $this->getManagerMock(debug_on: false, declarations: ['acls', 'preview-on-dev']);
        $this->assertFalse($manager->isEnabled('preview-on-dev'));
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Feature flag "preview-on-dev" is enabled but has unsatisfied dependencies: acls'
        );

        $manager = $this->getManagerMock(debug_on: true, declarations: ['acls', 'preview-on-dev']);
        $this->assertTrue($manager->isEnabled('preview-on-dev'));
    }

    /**
     * Turning a preview feature on turns on the ones it requires
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testComputeTurnOn(): void
    {
        $manager = $this->getManagerMock(debug_on: false, declarations: []);

        $change = $manager->computeTurnOn('Preview-Child');
        $this->assertSame(['preview-child', 'preview-feature'], $change['added']);
        $this->assertSame(['preview-child', 'preview-feature'], $change['flags']);

        $manager = $this->getManagerMock(debug_on: false, declarations: [], stored: ['preview-feature']);
        $change = $manager->computeTurnOn('preview-child');
        $this->assertSame(['preview-child'], $change['added']);
        $this->assertSame(['preview-feature', 'preview-child'], $change['flags']);

        foreach (
            [
                'acls' => FeatureFlagManager::ERR_NOT_PREVIEW,
                'unknown' => FeatureFlagManager::ERR_NOT_PREVIEW,
                'preview-on-dev' => FeatureFlagManager::ERR_DEV_DEPENDENCY,
            ] as $flag => $code
        ) {
            try {
                $manager->computeTurnOn($flag);
                $this->fail(sprintf('Turning "%s" on must be refused', $flag));
            } catch (\DomainException $e) {
                $this->assertSame($code, $e->getCode(), $flag);
            }
        }
    }

    /**
     * Turning a preview feature off turns off the ones requiring it
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testComputeTurnOff(): void
    {
        $manager = $this->getManagerMock(debug_on: false, declarations: [], stored: ['preview-feature', 'preview-child']);

        $change = $manager->computeTurnOff('preview-feature');
        $this->assertSame(['preview-feature', 'preview-child'], $change['removed']);
        $this->assertSame([], $change['flags']);

        $change = $manager->computeTurnOff('preview-child');
        $this->assertSame(['preview-child'], $change['removed']);
        $this->assertSame(['preview-feature'], $change['flags']);

        //nothing stored, nothing removed
        $change = $manager->computeTurnOff('acls');
        $this->assertSame([], $change['removed']);
    }

    /**
     * Labels and risks are translated on demand
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testLabelAndRisk(): void
    {
        $manager = $this->getManagerMock(debug_on: false, declarations: []);

        $this->assertSame('Preview feature', $manager->getLabel('preview-feature'));
        $this->assertSame('Might break things', $manager->getRisk('preview-feature'));
        //no label: the description stands in
        $this->assertSame('A preview feature requiring another one', $manager->getLabel('preview-child'));
        $this->assertNull($manager->getRisk('preview-child'));
        $this->assertSame(
            ['preview-feature', 'preview-child', 'preview-on-dev'],
            array_keys($manager->getPreviewFlags())
        );
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
