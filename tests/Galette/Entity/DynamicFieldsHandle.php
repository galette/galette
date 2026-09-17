<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Entity;

use Galette\Tests\GaletteTestCase;

use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\unlink;

/**
 * Dynamic fields values handle tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DynamicFieldsHandle extends GaletteTestCase
{
    protected int $seed = 6548791325;

    /** @var array<string> Files created on disk by a test */
    private array $files = [];

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->files = [];

        parent::tearDown();
    }

    /**
     * Create a dynamic field on members form
     *
     * @param int    $type   Field type
     * @param string $name   Field name
     * @param ?int   $repeat Number of occurrences
     */
    private function createField(int $type, string $name, ?int $repeat): \Galette\DynamicFields\DynamicField
    {
        $field = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $type);
        $stored = $field->store([
            'form_name'         => 'adh',
            'field_name'        => $name,
            'field_perm'        => \Galette\Entity\FieldsConfig::USER_WRITE,
            'field_type'        => $type,
            'field_required'    => 0,
            'field_repeat'      => $repeat
        ]);
        $this->assertTrue(
            $stored,
            implode(' ', $field->getErrors() + $field->getWarnings())
        );

        return $field;
    }

    /**
     * Get a member with its dynamic fields loaded
     */
    private function getMemberWithDynamics(): \Galette\Entity\Adherent
    {
        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            $this->adh->id,
            ['dynamics' => true] + $this->adh->deps
        );
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        return $adh;
    }

    /**
     * Get stored occurrences of a field, as `val_index => field_val`
     *
     * @param \Galette\Entity\Adherent $adh   Member
     * @param int                      $field Field id
     *
     * @return array<int, string>
     */
    private function getStoredOccurrences(\Galette\Entity\Adherent $adh, int $field): array
    {
        $occurrences = [];
        foreach ($adh->getDynamicFields()->getValues($field) as $value) {
            $occurrences[(int)$value['val_index']] = $value['field_val'];
        }
        ksort($occurrences);

        return $occurrences;
    }

    /**
     * Test several occurrences of a repeatable field are stored and renumbered
     */
    public function testRepeatableOccurrences(): void
    {
        $this->logSuperAdmin();
        $field = $this->createField(\Galette\DynamicFields\DynamicField::DATE, 'Dynamic dates', 3);
        $this->assertTrue($field->isRepeatable());

        $this->getMemberOne();
        $fid = $field->getId();

        $adh = $this->getMemberWithDynamics();
        $data = $this->dataAdherentOne() + [
            'info_field_' . $fid . '_1' => '2024-01-05',
            'info_field_' . $fid . '_2' => '2024-06-12',
            'info_field_' . $fid . '_3' => '2025-02-28'
        ];
        $this->assertTrue($adh->check($data, [], []), implode(' ', $adh->getErrors()));
        $this->assertTrue($adh->store());

        $adh = $this->getMemberWithDynamics();
        $this->assertSame(
            [
                1 => '2024-01-05',
                2 => '2024-06-12',
                3 => '2025-02-28'
            ],
            $this->getStoredOccurrences($adh, $fid)
        );

        //the middle occurrence is dropped from the form; the last one takes its index
        $adh = $this->getMemberWithDynamics();
        $data = $this->dataAdherentOne() + [
            'info_field_' . $fid . '_1' => '2024-01-05',
            'info_field_' . $fid . '_3' => '2025-02-28'
        ];
        $this->assertTrue($adh->check($data, [], []), implode(' ', $adh->getErrors()));
        $this->assertTrue($adh->store());

        $adh = $this->getMemberWithDynamics();
        $this->assertSame(
            [
                1 => '2024-01-05',
                2 => '2025-02-28'
            ],
            $this->getStoredOccurrences($adh, $fid)
        );
    }

    /**
     * Test a required repeatable field is satisfied by its first occurrence alone
     */
    public function testRequiredRepeatableField(): void
    {
        $this->logSuperAdmin();
        $field = \Galette\DynamicFields\DynamicField::getFieldType(
            $this->zdb,
            \Galette\DynamicFields\DynamicField::DATE
        );
        $this->assertTrue($field->store([
            'form_name'         => 'adh',
            'field_name'        => 'Required dates',
            'field_perm'        => \Galette\Entity\FieldsConfig::USER_WRITE,
            'field_type'        => \Galette\DynamicFields\DynamicField::DATE,
            'field_required'    => 1,
            'field_repeat'      => 0
        ]));

        $this->getMemberOne();
        $fid = $field->getId();

        //an empty extra occurrence does not make the field missing
        $adh = $this->getMemberWithDynamics();
        $data = $this->dataAdherentOne() + [
            'info_field_' . $fid . '_1' => '2024-01-05',
            'info_field_' . $fid . '_2' => ''
        ];
        $this->assertTrue($adh->check($data, [], []), implode(' ', $adh->getErrors()));
        $this->assertTrue($adh->store());

        $adh = $this->getMemberWithDynamics();
        $this->assertSame([1 => '2024-01-05'], $this->getStoredOccurrences($adh, $fid));

        //no occurrence at all is reported once, not once per occurrence
        $adh = $this->getMemberWithDynamics();
        $data = $this->dataAdherentOne() + [
            'info_field_' . $fid . '_1' => '',
            'info_field_' . $fid . '_2' => ''
        ];
        $this->assertSame(['Missing required field Required dates'], $adh->check($data, [], []));
        $this->expectLogEntry(
            \Analog\Analog::ERROR,
            'Some errors has been thew attempting to edit/store a member'
        );
    }

    /**
     * Test any posted value on a file occurrence deletes it
     *
     * The delete checkbox posts `on`; the form also posts a plain marker when an
     * occurrence holding a file is removed from the page, since a stored file is
     * not posted back. Neither value is looked at, and both must delete.
     */
    public function testPostedFileOccurrenceIsDeleted(): void
    {
        $this->logSuperAdmin();
        $field = $this->createField(\Galette\DynamicFields\DynamicField::FILE, 'Deletable files', 2);

        $this->getMemberOne();
        $fid = $field->getId();

        $adh = $this->getMemberWithDynamics();
        $dynamics = $adh->getDynamicFields();
        $dynamics->setValue(item: $adh->id, field: $fid, index: 1, value: 'first.txt');
        $dynamics->setValue(item: $adh->id, field: $fid, index: 2, value: 'second.txt');
        $this->assertTrue($dynamics->storeValues($adh->id));

        /** @var \Galette\DynamicFields\File $stored_field */
        $stored_field = $dynamics->getFields()[$fid];
        $first = GALETTE_FILES_PATH . $stored_field->getFileName($adh->id, 1);
        $second = GALETTE_FILES_PATH . $stored_field->getFileName($adh->id, 2);
        $this->files = [$first, $second];
        file_put_contents($first, 'first');
        file_put_contents($second, 'second');

        $adh = $this->getMemberWithDynamics();
        $data = $this->dataAdherentOne() + ['info_field_' . $fid . '_1' => '1'];
        $this->assertTrue($adh->check($data, [], []), implode(' ', $adh->getErrors()));
        $this->assertTrue($adh->store());

        $adh = $this->getMemberWithDynamics();
        $this->assertSame([1 => 'second.txt'], $this->getStoredOccurrences($adh, $fid));
        $this->assertSame('second', file_get_contents($first));
        $this->assertFileDoesNotExist($second);
    }

    /**
     * Test files follow the occurrences they belong to
     */
    public function testRepeatableFileOccurrences(): void
    {
        $this->logSuperAdmin();
        $field = $this->createField(\Galette\DynamicFields\DynamicField::FILE, 'Dynamic files', 2);
        $this->assertTrue($field->isRepeatable());

        $this->getMemberOne();
        $fid = $field->getId();

        $adh = $this->getMemberWithDynamics();
        $dynamics = $adh->getDynamicFields();
        $dynamics->setValue(item: $adh->id, field: $fid, index: 1, value: 'first.txt');
        $dynamics->setValue(item: $adh->id, field: $fid, index: 2, value: 'second.txt');
        $this->assertTrue($dynamics->storeValues($adh->id));

        /** @var \Galette\DynamicFields\File $stored_field */
        $stored_field = $dynamics->getFields()[$fid];
        $first = GALETTE_FILES_PATH . $stored_field->getFileName($adh->id, 1);
        $second = GALETTE_FILES_PATH . $stored_field->getFileName($adh->id, 2);
        $this->files = [$first, $second];
        file_put_contents($first, 'first');
        file_put_contents($second, 'second');

        //drop the first occurrence: the second one takes its index, and its file follows
        $adh = $this->getMemberWithDynamics();
        $dynamics = $adh->getDynamicFields();
        $dynamics->unsetValue($fid, 1);
        $this->assertTrue($dynamics->storeValues($adh->id));

        $adh = $this->getMemberWithDynamics();
        $this->assertSame([1 => 'second.txt'], $this->getStoredOccurrences($adh, $fid));

        $this->assertFileExists($first);
        $this->assertSame('second', file_get_contents($first));
        $this->assertFileDoesNotExist($second);
    }
}
