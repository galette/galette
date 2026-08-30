<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Enums;

use Galette\Core\Authentication;
use Galette\Core\Preferences;
use Galette\Enums\ContactSource;
use Galette\Enums\PasswordStrength;
use Galette\Enums\PublicPageVisibility;
use PHPUnit\Framework\TestCase;

/**
 * Preferences enums tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PublicPageVisibilityTest extends TestCase
{
    /**
     * Stored values are a database and E2E contract, they cannot move
     */
    public function testStoredValuesAreStable(): void
    {
        $this->assertSame(0, PublicPageVisibility::Everyone->value);
        $this->assertSame(1, PublicPageVisibility::UpToDateMembers->value);
        $this->assertSame(2, PublicPageVisibility::StaffOnly->value);
        $this->assertSame(3, PublicPageVisibility::Hidden->value);
        $this->assertSame(4, PublicPageVisibility::Inherit->value);

        $this->assertSame(0, ContactSource::Preferences->value);
        $this->assertSame(1, ContactSource::StaffMember->value);
        $this->assertSame(2, ContactSource::StaffMemberMobile->value);

        $this->assertSame(0, PasswordStrength::None->value);
        $this->assertSame(4, PasswordStrength::VeryStrong->value);
    }

    /**
     * The constants kept for compatibility still carry the same values
     */
    public function testDeprecatedConstantsStillMatch(): void
    {
        $this->assertSame(PublicPageVisibility::Everyone->value, Preferences::PUBLIC_PAGES_VISIBILITY_PUBLIC);
        $this->assertSame(PublicPageVisibility::UpToDateMembers->value, Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED);
        $this->assertSame(PublicPageVisibility::StaffOnly->value, Preferences::PUBLIC_PAGES_VISIBILITY_PRIVATE);
        $this->assertSame(PublicPageVisibility::Hidden->value, Preferences::PUBLIC_PAGES_VISIBILITY_HIDDEN);
        $this->assertSame(PublicPageVisibility::Inherit->value, Preferences::PUBLIC_PAGES_VISIBILITY_INHERIT);

        $this->assertSame(ContactSource::Preferences->value, Preferences::POSTAL_ADDRESS_FROM_PREFS);
        $this->assertSame(ContactSource::StaffMember->value, Preferences::POSTAL_ADDRESS_FROM_STAFF);
        $this->assertSame(ContactSource::Preferences->value, Preferences::PHONE_NUMBER_FROM_PREFS);
        $this->assertSame(ContactSource::StaffMember->value, Preferences::PHONE_NUMBER_FROM_STAFF);
        $this->assertSame(ContactSource::StaffMemberMobile->value, Preferences::PHONE_NUMBER_MOBILE_FROM_STAFF);

        $this->assertSame(PasswordStrength::None->value, Preferences::PWD_NONE);
        $this->assertSame(PasswordStrength::Weak->value, Preferences::PWD_WEAK);
        $this->assertSame(PasswordStrength::Medium->value, Preferences::PWD_MEDIUM);
        $this->assertSame(PasswordStrength::Strong->value, Preferences::PWD_STRONG);
        $this->assertSame(PasswordStrength::VeryStrong->value, Preferences::PWD_VERY_STRONG);
    }

    /**
     * Who sees what
     */
    public function testVisibility(): void
    {
        $anonymous = $this->login(up2date: false, staff: false, admin: false);
        $member = $this->login(up2date: true, staff: false, admin: false);
        $staff = $this->login(up2date: false, staff: true, admin: false);

        $this->assertTrue(PublicPageVisibility::Everyone->isVisibleFor($anonymous));
        $this->assertTrue(PublicPageVisibility::Everyone->isVisibleFor($member));

        $this->assertFalse(PublicPageVisibility::UpToDateMembers->isVisibleFor($anonymous));
        $this->assertTrue(PublicPageVisibility::UpToDateMembers->isVisibleFor($member));
        //staff gets in even with a lapsed membership
        $this->assertTrue(PublicPageVisibility::UpToDateMembers->isVisibleFor($staff));

        $this->assertFalse(PublicPageVisibility::StaffOnly->isVisibleFor($member));
        $this->assertTrue(PublicPageVisibility::StaffOnly->isVisibleFor($staff));

        $this->assertFalse(PublicPageVisibility::Hidden->isVisibleFor($staff));
    }

    /**
     * Inheriting defers to whatever the caller resolves
     */
    public function testInherit(): void
    {
        $login = $this->login(up2date: false, staff: false, admin: false);

        $this->assertTrue(PublicPageVisibility::Inherit->isVisibleFor($login, fn(): bool => true));
        $this->assertFalse(PublicPageVisibility::Inherit->isVisibleFor($login, fn(): bool => false));
        //nothing to defer to: not visible, rather than a crash
        $this->assertFalse(PublicPageVisibility::Inherit->isVisibleFor($login));
    }

    /**
     * A staff member source is anything but the preferences
     */
    public function testContactSource(): void
    {
        $this->assertFalse(ContactSource::Preferences->isStaffMember());
        $this->assertTrue(ContactSource::StaffMember->isStaffMember());
        $this->assertTrue(ContactSource::StaffMemberMobile->isStaffMember());
    }

    /**
     * Strengths compare
     */
    public function testPasswordStrengthOrder(): void
    {
        $this->assertTrue(PasswordStrength::Strong->isAtLeast(PasswordStrength::Weak));
        $this->assertTrue(PasswordStrength::Weak->isAtLeast(PasswordStrength::Weak));
        $this->assertFalse(PasswordStrength::None->isAtLeast(PasswordStrength::Weak));
    }

    /**
     * Build an Authentication stub
     */
    private function login(bool $up2date, bool $staff, bool $admin): Authentication
    {
        //a stub, not a mock: nothing here cares how often it is asked
        $login = $this->createStub(Authentication::class);
        $login->method('isUp2Date')->willReturn($up2date);
        $login->method('isStaff')->willReturn($staff);
        $login->method('isAdmin')->willReturn($admin);

        return $login;
    }
}
