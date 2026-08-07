<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Entity;

use Galette\Tests\GaletteTestCase;
use Laminas\Db\Adapter\Adapter;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Contributions types tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ContributionsTypes extends GaletteTestCase
{
    /**
     * Test contributions types
     */
    public function testContributionsTypes(): void
    {
        global $i18n; // globals :(
        $i18n = $this->i18n;

        $ctype = new \Galette\Entity\ContributionsTypes($this->zdb);

        $this->assertSame(
            -2,
            $ctype->add(
                label: 'annual fee',
                description: '',
                amount: 10,
                extension: \Galette\Entity\ContributionsTypes::DONATION_TYPE
            )
        );
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'A contribution type with label `annual fee` already exists'
        );

        $this->assertTrue(
            $ctype->add(
                label: 'Test contribution type',
                description: 'Test contribution type description',
                amount: null,
                extension: \Galette\Entity\ContributionsTypes::DONATION_TYPE
            )
        );

        $id = $ctype->id;

        $ctype_id = $ctype->getIdByLabel('Test contribution type');
        $this->assertGreaterThan(0, $ctype_id);

        $test_ctype = $ctype->get($ctype_id);
        $this->assertInstanceOf(\ArrayObject::class, $test_ctype);

        $this->assertSame('Test contribution type', $test_ctype['libelle_type_cotis']);
        $this->assertNull($test_ctype['amount']);
        $this->assertSame(0, (int)$test_ctype['cotis_extension']);

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            [
                'text_orig'     => 'Test contribution type'
            ]
        );
        $results = $this->zdb->execute($select);
        $result = (array)$results->current();

        $this->assertSame(
            'Test contribution type',
            $result['text_orig']
        );

        $this->assertSame(
            \Galette\Entity\ContributionsTypes::ID_NOT_EXITS,
            $ctype->update(
                id: 42,
                label: 'annual fee',
                description: '',
                amount: 10,
                extension: \Galette\Entity\ContributionsTypes::DONATION_TYPE
            )
        );

        $this->assertTrue(
            $ctype->update(
                id: $id,
                label: 'Tested contribution type',
                description: 'Test contribution type description',
                amount: 42,
                extension: \Galette\Entity\ContributionsTypes::DEFAULT_TYPE
            )
        );

        $this->assertSame(
            'Tested contribution type',
            $ctype->getLabel($id)
        );

        $test_ctype = $ctype->get($id);
        $this->assertInstanceOf(\ArrayObject::class, $test_ctype);

        $this->assertSame('Tested contribution type', $test_ctype['libelle_type_cotis']);
        $this->assertSame(42.0, (float)$test_ctype['amount']);
        $this->assertSame(\Galette\Entity\ContributionsTypes::DEFAULT_TYPE, (int)$test_ctype['cotis_extension']);

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            [
                'text_orig'     => 'Tested contribution type'
            ]
        );
        $results = $this->zdb->execute($select);
        $result = (array)$results->current();

        $this->assertSame(
            'Tested contribution type',
            $result['text_orig']
        );

        $this->assertSame(
            \Galette\Entity\ContributionsTypes::ID_NOT_EXITS,
            $ctype->delete(42)
        );

        $this->assertTrue(
            $ctype->delete($id)
        );

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(
            [
                'text_orig'     => 'Tested contribution type'
            ]
        );
        $results = $this->zdb->execute($select);
        $this->assertSame(0, $results->count());
    }

    /**
     * Test getList
     */
    public function testGetList(): void
    {
        $ctypes = new \Galette\Entity\ContributionsTypes($this->zdb);

        $list = $ctypes->getList();
        $this->assertCount(7, $list);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($this->zdb->getSequenceName($ctypes::TABLE, $ctypes::PK));
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->assertGreaterThanOrEqual(7, $result->last_value, 'Incorrect contributions types sequence');

            $this->zdb->db->query(
                'SELECT setval(\'' . $this->zdb->getSequenceName($ctypes::TABLE, $ctypes::PK, true) . '\', 1)',
                Adapter::QUERY_MODE_EXECUTE
            );
        }

        //reinstall status
        $ctypes->installInit();

        $list = $ctypes->getList();
        $this->assertCount(7, $list);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($this->zdb->getSequenceName($ctypes::TABLE, $ctypes::PK));
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->assertGreaterThanOrEqual(7, $result->last_value, 'Incorrect contributions types sequence ' . $result->last_value);
        }
    }

    /**
     * Empty description provider
     *
     * @return array<int, array<int, string>>
     */
    public static function emptyProvider(): array
    {
        return [
            [''],
            ['<br>'],
            ['<br><br>'],
            ['<br><br><br><br>'],
            ['<p><br></p>'],
            ['<p><br></p><p><br></p>'],
            ['<p><br></p><p><br></p><p><br></p><p><br></p>'],
            ["    \n    <p><br></p><p><br></p>\n\n<p><br></p><p><br></p>"]
        ];
    }

    /**
     * Test contributions types empty with description
     */
    #[DataProvider('emptyProvider')]
    public function testEmptyDescription(string $description): void
    {
        global $i18n; // globals :(
        $i18n = $this->i18n;

        $ctype = new \Galette\Entity\ContributionsTypes($this->zdb);

        $label = 'Test no description';
        $this->assertTrue(
            $ctype->add(
                label: $label,
                description: $description,
                amount: null,
                extension: \Galette\Entity\ContributionsTypes::DONATION_TYPE
            )
        );
        $ctype_id = $ctype->id;

        $test_ctype = $ctype->get($ctype_id);
        $this->assertSame($label, $test_ctype['libelle_type_cotis']);
        $this->assertSame('', $test_ctype['description']);

        $label .= ' (modified)';
        $this->assertTrue(
            $ctype->update(
                id: $ctype_id,
                label: $label,
                description: $description,
                amount: null,
                extension: \Galette\Entity\ContributionsTypes::DONATION_TYPE
            )
        );

        $test_ctype = $ctype->get($ctype_id);
        $this->assertSame($label, $test_ctype['libelle_type_cotis']);
        $this->assertSame('', $test_ctype['description']);

        $real_description = 'with description';
        $description .= 'with description';
        $label .= ' - ' . $real_description;
        $this->assertTrue(
            $ctype->update(
                id: $ctype_id,
                label: $label,
                description: $description,
                amount: null,
                extension: \Galette\Entity\ContributionsTypes::DONATION_TYPE
            )
        );

        $test_ctype = $ctype->get($ctype_id);
        $this->assertSame($label, $test_ctype['libelle_type_cotis']);
        $this->assertSame($real_description, $test_ctype['description']);

        //now check with a real description
        $description = $real_description;
        $label .= ' - ' . $description;
        $this->assertTrue(
            $ctype->update(
                id: $ctype_id,
                label: $label,
                description: $description,
                amount: null,
                extension: \Galette\Entity\ContributionsTypes::DONATION_TYPE
            )
        );

        $test_ctype = $ctype->get($ctype_id);
        $this->assertSame($label, $test_ctype['libelle_type_cotis']);
        $this->assertSame($real_description, $test_ctype['description']);
    }

    /**
     * Description provider
     *
     * @return array<int, array{description: string, expected: string}>
     */
    public static function descriptionProvider(): array
    {
        return [
            [
                'description' => 'Just a description',
                'expected' => 'Just a description'
            ],
            [
                'description' => '   Just a description with spaces and<br>line breaks   ',
                'expected' => 'Just a description with spaces and<br />line breaks'
            ],
            [
                'description' => '<p>Just a description with HTML tags</p>',
                'expected' => '<p>Just a description with HTML tags</p>'
            ],
            [
                'description' => '<p>Just a description with HTML tags and line breaks</p><p><br></p><p>Second line</p>',
                'expected' => '<p>Just a description with HTML tags and line breaks</p><p>Second line</p>'
            ]
        ];
    }

    /**
     * Test contributions types empty with description
     */
    #[DataProvider('descriptionProvider')]
    public function testDescription(string $description, string $expected): void
    {
        global $i18n; // globals :(
        $i18n = $this->i18n;

        $ctype = new \Galette\Entity\ContributionsTypes($this->zdb);

        $label = 'Test description';
        $this->assertTrue(
            $ctype->add(
                label: $label,
                description: $description,
                amount: null,
                extension: \Galette\Entity\ContributionsTypes::DONATION_TYPE
            )
        );
        $ctype_id = $ctype->id;

        $test_ctype = $ctype->get($ctype_id);
        $this->assertSame($label, $test_ctype['libelle_type_cotis']);
        $this->assertSame($expected, $test_ctype['description']);

        $label .= ' (modified)';
        $this->assertTrue(
            $ctype->update(
                id: $ctype_id,
                label: $label,
                description: $description,
                amount: null,
                extension: \Galette\Entity\ContributionsTypes::DONATION_TYPE
            )
        );

        $test_ctype = $ctype->get($ctype_id);
        $this->assertSame($label, $test_ctype['libelle_type_cotis']);
        $this->assertSame($expected, $test_ctype['description']);
    }

    /**
     * XSS payload provider
     *
     * @return array<int, array{payload: string, onlabel?: bool}>
     */
    public static function xssPayloadProvider(): array
    {
        return [
            [
                'payload' => '<script>alert("XSS")</script>'],
            [
                'payload' => '<img src=x onerror=alert("XSS")>'
            ],
            [
                'payload' => '<svg/onload=alert("XSS")>'
            ],
            [
                'payload' => 'javascript:alert("XSS")',
                'onlabel' => false
            ],
            [
                'payload' => '<iframe src="javascript:alert(\'XSS\')">'
            ],
            [
                'payload' => '<ScRiPt>alert("XSS")</ScRiPt>'
            ]
        ];
    }

    /**
     * Test XSS protection in contribution type label
     */
    #[DataProvider('xssPayloadProvider')]
    public function testContributionTypeXSSProtection(string $payload, bool $onlabel = true): void
    {
        $this->logSuperAdmin();

        $ctype = new \Galette\Entity\ContributionsTypes($this->zdb);
        $this->assertTrue(
            $ctype->add(
                label: 'Test payload ' . $payload,
                description: $payload,
                amount: null,
                extension: \Galette\Entity\ContributionsTypes::DONATION_TYPE
            )
        );
        $ctype_id = $ctype->id;
        $test_ctype = $ctype->get($ctype_id);

        $fields = ['description'];
        if ($onlabel) {
            $fields[] = 'libelle_type_cotis';
        }
        foreach ($fields as $field) {
            // Check for sanitization
            $this->assertStringNotContainsString('<script>', $test_ctype[$field], "Payload should be sanitized in $field");
            $this->assertStringNotContainsString('onerror=', $test_ctype[$field], "Payload should be sanitized in $field");
            $this->assertStringNotContainsString('javascript:', $test_ctype[$field], "Payload should be sanitized in $field");

            $this->assertNotEquals($payload, $test_ctype[$field], "Payload should not be stored as is in $field");
        }
    }
}
