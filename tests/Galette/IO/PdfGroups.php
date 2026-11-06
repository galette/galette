<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\IO;

use Galette\Tests\GaletteTestCase;

/**
 * Groups PDF tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PdfGroups extends GaletteTestCase
{
    protected int $seed = 20261025;

    /**
     * Test groups export is granted by permissions, not by groups managers status
     */
    public function testDrawFromRole(): void
    {
        $this->grantToRole(\Galette\Access\Roles::MEMBER, ['group:read', 'group:export']);
        try {
            $this->logSuperAdmin();
            $group = new \Galette\Entity\Group();
            $group->setName('Exported from a role');
            $this->assertTrue($group->store());
            $this->getMemberTwo();
            $this->login->logOut();

            $mdata = $this->dataAdherentTwo();
            $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
            $this->assertFalse($this->login->isGroupManager());

            $access = $this->container->get(\Galette\Access\AccessControl::class);
            $empty = new \Galette\IO\PdfGroups($this->preferences, $access);
            $empty->draw([]);

            //group header and its (empty) managers and members lists are drawn
            $pdf = new \Galette\IO\PdfGroups($this->preferences, $access);
            $pdf->draw([$group]);
            $this->assertGreaterThan($empty->GetY(), $pdf->GetY());
        } finally {
            $this->revokeFromRole(\Galette\Access\Roles::MEMBER, ['group:read', 'group:export']);
            $this->login->logOut();
        }
    }
}
