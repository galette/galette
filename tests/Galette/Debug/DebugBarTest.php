<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Debug;

use Galette\Debug\Collectors\GaletteCollector;
use Galette\Debug\Collectors\RequestCollector;
use Galette\Debug\DebugBar;
use PHPUnit\Framework\TestCase;

/**
 * Debug bar tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DebugBarTest extends TestCase
{
    /**
     * The bar never starts under tests, and its entry points are then no-ops
     */
    public function testNotAvailableInTests(): void
    {
        $this->assertFalse(DebugBar::isAvailable());
        DebugBar::boot();
        $this->assertFalse(DebugBar::isEnabled());
        $this->assertNull(DebugBar::get());
        $this->assertNull(DebugBar::getTwigExtension());

        //must not fail
        DebugBar::addException(new \RuntimeException('test'));
        DebugBar::startMeasure('test');
        DebugBar::stopMeasure('test');
        DebugBar::setRoute(route: null);

        $handler = static fn(array $info): null => null;
        $this->assertSame($handler, DebugBar::wrapLogHandler($handler));

        $session = new \RKA\Session();
        $this->assertFalse(DebugBar::isActive($session, $this->createStub(\Galette\Core\Login::class)));
    }

    /**
     * Build a login stub
     *
     * @param bool $logged       Logged in
     * @param bool $superadmin   Super administrator
     * @param bool $impersonated Impersonating a member
     */
    private function getLogin(bool $logged, bool $superadmin, bool $impersonated): \Galette\Core\Login
    {
        $login = $this->createStub(\Galette\Core\Login::class);
        $login->method('isLogged')->willReturn($logged);
        $login->method('isSuperAdmin')->willReturn($superadmin);
        $login->method('isImpersonated')->willReturn($impersonated);
        return $login;
    }

    /**
     * The flag is bound to the super administrator, impersonating or not
     */
    public function testIdentity(): void
    {
        $identity = new \ReflectionMethod(DebugBar::class, 'getIdentity');

        $superadmin = $identity->invoke(null, $this->getLogin(logged: true, superadmin: true, impersonated: false));
        $this->assertSame('superadmin', $superadmin);
        //impersonating a member: still the super administrator at the keyboard
        $this->assertSame($superadmin, $identity->invoke(null, $this->getLogin(logged: true, superadmin: false, impersonated: true)));

        //nobody else can hold the flag
        $this->assertNull($identity->invoke(null, $this->getLogin(logged: true, superadmin: false, impersonated: false)));
        $this->assertNull($identity->invoke(null, $this->getLogin(logged: false, superadmin: false, impersonated: false)));
    }

    /**
     * Only the super administrator turns the bar on or off
     */
    public function testCanToggle(): void
    {
        $login = $this->createStub(\Galette\Core\Login::class);
        $login->method('isSuperAdmin')->willReturn(false);
        $this->assertFalse(DebugBar::canToggle($login));

        $superadmin = $this->createStub(\Galette\Core\Login::class);
        $superadmin->method('isSuperAdmin')->willReturn(true);
        $this->assertTrue(DebugBar::canToggle($superadmin));
    }

    /**
     * Passwords and session objects never reach the bar
     */
    public function testRequestCollectorHidesSecrets(): void
    {
        $post = $_POST;
        $session = $_SESSION ?? null;
        $_POST = ['login' => 'jdoe', 'password' => 'averylongpassword', 'mdp_adh' => 'secret1', 'mdp_adh2' => 'secret1'];
        $_SESSION = ['member' => new \stdClass(), 'list' => ['nested' => new \ArrayObject()], 'lang' => 'fr_FR'];

        try {
            $collector = new RequestCollector();
            $data = json_encode($collector->collect(), JSON_THROW_ON_ERROR);
        } finally {
            $_POST = $post;
            if ($session === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $session;
            }
        }

        $this->assertStringContainsString('jdoe', $data);
        $this->assertStringNotContainsString('averylongpassword', $data);
        $this->assertStringNotContainsString('av***', $data);
        $this->assertStringNotContainsString('secret1', $data);
        $this->assertStringContainsString('(stdClass)', $data);
        $this->assertStringContainsString('(ArrayObject)', $data);
        $this->assertStringContainsString('fr_FR', $data);
    }

    /**
     * Galette collector renders plain text, without a container
     */
    public function testGaletteCollectorWithoutContainer(): void
    {
        $data = (new GaletteCollector())->collect();
        $this->assertSame('(none)', $data['route']);
        $this->assertSame(GALETTE_MODE, $data['mode']);
        $this->assertArrayNotHasKey('user', $data);
    }
}
