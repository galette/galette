<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Updates;

use Galette\Core\L10n;
use Galette\Tests\GaletteTestCase;
use Galette\Updater\AbstractUpdater;

/**
 * Labels translations keys, from md5 to the current hash (1.40 upgrade script)
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class L10nKeysRehash extends GaletteTestCase
{
    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(L10n::TABLE);
        $delete->where->like('text_orig', 'Rehash test %');
        $this->zdb->execute($delete);
        parent::tearDown();
    }

    /**
     * Get 1.40 upgrade script
     */
    private function getUpdater(): AbstractUpdater
    {
        include_once GALETTE_BASE_PATH . 'install/scripts/upgrade-to-1.40.php';
        $updater = new \Galette\Updates\UpgradeTo140();
        $zdb = new \ReflectionProperty($updater, 'zdb');
        $zdb->setValue($updater, $this->zdb);
        return $updater;
    }

    /**
     * Translations stored under md5 keys are found again under the new ones
     */
    public function testRehash(): void
    {
        $translations = [
            ['Rehash test label', 'fr_FR.utf8', 'Libellé de test'],
            ['Rehash test label', 'de_DE.utf8', 'Testbezeichnung'],
            ['Rehash test other', 'fr_FR.utf8', 'Autre'],
        ];
        foreach ($translations as [$orig, $locale, $trans]) {
            $insert = $this->zdb->insert(L10n::TABLE);
            $insert->values([
                //as versions before 1.4.0 stored them
                'text_orig_sum' => md5($orig),
                'text_orig'     => $orig,
                'text_locale'   => $locale,
                'text_nref'     => 1,
                'text_trans'    => $trans
            ]);
            $this->zdb->execute($insert);
        }

        $updater = $this->getUpdater();
        $update = new \ReflectionMethod($updater, 'update');
        $this->assertTrue($update->invoke($updater));
        //running it again changes nothing
        $this->assertTrue($update->invoke($updater));

        $l10n = $this->container->get(L10n::class);
        foreach ($translations as [$orig, $locale, $trans]) {
            $this->assertSame($trans, $l10n->getDynamicTranslation($orig, $locale));
        }

        $select = $this->zdb->select(L10n::TABLE);
        $select->where->like('text_orig', 'Rehash test %');
        $keys = [];
        foreach ($this->zdb->execute($select) as $row) {
            $keys[] = $row->text_orig_sum;
        }
        sort($keys);
        $expected = [L10n::sum('Rehash test label'), L10n::sum('Rehash test label'), L10n::sum('Rehash test other')];
        sort($expected);
        $this->assertSame($expected, $keys);
    }
}
