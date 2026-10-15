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

    /**
     * Test a group heading is never separated from its members table
     */
    public function testHeadingKeptWithTable(): void
    {
        $this->logSuperAdmin();
        $groups = [];
        for ($i = 1; $i <= 40; ++$i) {
            $group = new \Galette\Entity\Group();
            $group->setName('Kept together ' . $i);
            $this->assertTrue($group->store());
            $groups[] = $group;
        }

        $access = $this->container->get(\Galette\Access\AccessControl::class);
        $pdf = new class ($this->preferences, $access) extends \Galette\IO\PdfGroups {
            /** @var array<int, int> */
            public array $headings = [];
            /** @var array<int, int> */
            public array $tables = [];

            /**
             * Record the page each group heading is drawn on
             */
            public function text(
                string $text,
                float $w = 0,
                float $h = 0,
                string $align = '',
                bool $ln = true,
                string $valign = 'T',
                ?string $fill = null,
                string $border = '',
                ?float $x = null,
                ?float $y = null,
                bool $wrap = true
            ): float {
                if (str_starts_with($text, 'Kept together ')) {
                    $this->headings[] = $this->getNumPages();
                }
                return parent::text($text, $w, $h, $align, $ln, $valign, $fill, $border, $x, $y, $wrap);
            }

            /**
             * Record the page each members table ends on
             */
            public function writeHTML(
                string $html,
                ?float $x = null,
                ?float $y = null,
                float $w = 0,
                float $h = 0
            ): void {
                //tables of empty groups are a single row: they stay on one page
                parent::writeHTML($html, $x, $y, $w, $h);
                $this->tables[] = $this->getNumPages();
            }
        };
        $pdf->draw($groups);

        $this->assertCount(40, $pdf->headings);
        $this->assertGreaterThan(1, $pdf->getNumPages());
        $this->assertSame($pdf->headings, $pdf->tables);
    }
}
