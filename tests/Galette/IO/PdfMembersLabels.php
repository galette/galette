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
 * Members labels PDF tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PdfMembersLabels extends GaletteTestCase
{
    protected int $seed = 20261023;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->adh = new \Galette\Entity\Adherent($this->zdb);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
    }

    /**
     * Test label lines without company name
     */
    public function testLabelLines(): void
    {
        $this->createMember($this->dataAdherentOne());

        $pdf = new \Galette\IO\PdfMembersLabels($this->preferences);
        $this->assertSame(
            [
                'name' => 'DURAND René',
                'address_0' => '66, boulevard De Oliveira',
                'town' => '39 069 Martel',
                'country' => 'Antarctique'
            ],
            $pdf->getLabelLines($this->adh)
        );
    }

    /**
     * Test company name is printed between name and address
     */
    public function testLabelLinesWithCompany(): void
    {
        $company = 'Dupont & Fils, manufacture de chaussures et articles de maroquinerie';
        $this->createMember([
            'societe_adh' => $company,
            'is_company' => true
        ] + $this->dataAdherentOne());
        $this->assertTrue($this->adh->isCompany());

        $pdf = new \Galette\IO\PdfMembersLabels($this->preferences);
        $lines = $pdf->getLabelLines($this->adh);
        $this->assertSame(
            ['name', 'company', 'address_0', 'town', 'country'],
            array_keys($lines)
        );
        $this->assertSame($company, $lines['company']);

        //long company name must not prevent labels generation
        $pdf->drawLabels([$this->adh]);
        $this->assertSame(1, $pdf->getNumPages());
    }
}
