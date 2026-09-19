<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Core\TwoFactorSecret;
use Galette\Tests\GaletteTestCase;

/**
 * Second factor storage tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TwoFactorSecretTest extends GaletteTestCase
{
    protected int $seed = 20260821123000;

    private const string SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    /**
     * A freshly stored secret is not usable until the member has proved they
     * hold it: enrolment must not lock anyone out of their own account
     */
    public function testEnrolmentIsConfirmedBeforeBeingUsable(): void
    {
        $member = $this->createMember($this->dataAdherentOne());
        $state = new TwoFactorSecret($this->zdb);

        $this->assertFalse($state->load($member->id));
        $this->assertFalse($state->isLoaded());

        $this->assertTrue($state->create($member->id, self::SECRET));
        $this->assertTrue($state->isLoaded());
        $this->assertFalse($state->isEnabled());
        $this->assertSame(self::SECRET, $state->getSecret());
        $this->assertNull($state->getLastTimeslice());

        //still not enabled once read back from database
        $reloaded = new TwoFactorSecret($this->zdb);
        $this->assertTrue($reloaded->load($member->id));
        $this->assertFalse($reloaded->isEnabled());

        $this->assertTrue($state->enable());
        $this->assertTrue($reloaded->load($member->id));
        $this->assertTrue($reloaded->isEnabled());
    }

    /**
     * Enrolling again must not leave the previous secret able to authenticate
     */
    public function testEnrollingAgainReplacesEverything(): void
    {
        $member = $this->createMember($this->dataAdherentOne());
        $state = new TwoFactorSecret($this->zdb);

        $state->create($member->id, self::SECRET);
        $state->enable();
        $state->setLastTimeslice(42);
        $codes = $state->generateRecoveryCodes();
        $this->assertCount(TwoFactorSecret::CODES_COUNT, $codes);

        $state->create($member->id, 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP');

        $reloaded = new TwoFactorSecret($this->zdb);
        $this->assertTrue($reloaded->load($member->id));
        $this->assertSame('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', $reloaded->getSecret());
        //back to unconfirmed, replay mark cleared, old codes gone
        $this->assertFalse($reloaded->isEnabled());
        $this->assertNull($reloaded->getLastTimeslice());
        $this->assertSame(0, $reloaded->countRemainingCodes());
        $this->assertFalse($reloaded->consumeRecoveryCode($codes[0]));
    }

    /**
     * The last accepted time slice survives a reload: replay protection is
     * worthless if it only lives in memory
     */
    public function testLastTimesliceIsPersisted(): void
    {
        $member = $this->createMember($this->dataAdherentOne());
        $state = new TwoFactorSecret($this->zdb);
        $state->create($member->id, self::SECRET);

        $this->assertTrue($state->setLastTimeslice(56666666));

        $reloaded = new TwoFactorSecret($this->zdb);
        $reloaded->load($member->id);
        $this->assertSame(56666666, $reloaded->getLastTimeslice());
    }

    /**
     * Recovery codes are handed out once and stored hashed, so a read access
     * to the table does not hand over usable codes
     */
    public function testRecoveryCodesAreStoredHashed(): void
    {
        $member = $this->createMember($this->dataAdherentOne());
        $state = new TwoFactorSecret($this->zdb);
        $state->create($member->id, self::SECRET);

        $codes = $state->generateRecoveryCodes();

        $this->assertCount(TwoFactorSecret::CODES_COUNT, $codes);
        $this->assertSame(TwoFactorSecret::CODES_COUNT, count(array_unique($codes)));
        $this->assertSame(TwoFactorSecret::CODES_COUNT, $state->countRemainingCodes());
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[0-9A-F]{5}-[0-9A-F]{5}$/', $code);
        }

        //nothing readable is stored
        $select = $this->zdb->select(TwoFactorSecret::CODES_TABLE);
        $stored = [];
        foreach ($this->zdb->execute($select) as $row) {
            $stored[] = (string)$row->code;
        }
        $this->assertCount(TwoFactorSecret::CODES_COUNT, $stored);
        foreach ($stored as $hash) {
            $this->assertSame('$2y$', substr($hash, 0, 4));
            $this->assertNotContains($hash, $codes);
        }
    }

    /**
     * A recovery code works once
     */
    public function testRecoveryCodeIsSingleUse(): void
    {
        $member = $this->createMember($this->dataAdherentOne());
        $state = new TwoFactorSecret($this->zdb);
        $state->create($member->id, self::SECRET);
        $codes = $state->generateRecoveryCodes();

        $this->assertTrue($state->consumeRecoveryCode($codes[3]));
        $this->assertSame(TwoFactorSecret::CODES_COUNT - 1, $state->countRemainingCodes());

        $this->assertFalse($state->consumeRecoveryCode($codes[3]));
        $this->assertSame(TwoFactorSecret::CODES_COUNT - 1, $state->countRemainingCodes());

        //the others still work, and nothing invented does
        $this->assertTrue($state->consumeRecoveryCode($codes[0]));
        $this->assertFalse($state->consumeRecoveryCode('AAAAA-BBBBB'));
        $this->assertFalse($state->consumeRecoveryCode(''));
    }

    /**
     * Dropping a second factor takes its recovery codes with it
     */
    public function testRemoveTakesCodesAlong(): void
    {
        $member = $this->createMember($this->dataAdherentOne());
        $state = new TwoFactorSecret($this->zdb);
        $state->create($member->id, self::SECRET);
        $state->generateRecoveryCodes();

        $this->assertTrue($state->remove());
        $this->assertFalse($state->isLoaded());

        $reloaded = new TwoFactorSecret($this->zdb);
        $this->assertFalse($reloaded->load($member->id));
        $this->assertSame(0, $this->zdb->execute($this->zdb->select(TwoFactorSecret::CODES_TABLE))->count());
    }

    /**
     * Removing a member must not leave their second factor behind
     */
    public function testMemberRemovalCascades(): void
    {
        $member = $this->createMember($this->dataAdherentOne());
        $state = new TwoFactorSecret($this->zdb);
        $state->create($member->id, self::SECRET);
        $state->generateRecoveryCodes();

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where([\Galette\Entity\Adherent::PK => $member->id]);
        $this->zdb->execute($delete);

        $this->assertSame(0, $this->zdb->execute($this->zdb->select(TwoFactorSecret::TABLE))->count());
        $this->assertSame(0, $this->zdb->execute($this->zdb->select(TwoFactorSecret::CODES_TABLE))->count());
    }

    /**
     * Operations needing a loaded second factor say so rather than doing
     * something unexpected
     */
    public function testOperationsRequireALoadedSecret(): void
    {
        $state = new TwoFactorSecret($this->zdb);

        foreach (['enable', 'generateRecoveryCodes', 'remove'] as $method) {
            try {
                $state->$method();
                $this->fail($method . '() should have refused to run');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('second factor', $e->getMessage());
            }
        }
    }
}
