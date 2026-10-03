<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\IO;

use Galette\Core\Mailing;
use Galette\Core\Picture;
use Galette\Core\PreferencesSchema;
use Galette\DynamicFields\DynamicField;
use Galette\Entity\Document;
use Galette\IO\CsvIn;
use Galette\IO\File;
use Galette\IO\UploadSize;
use Galette\Tests\GaletteTestCase;

/**
 * Upload sizes tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class UploadSizeTest extends GaletteTestCase
{
    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        unset($GLOBALS['preferences']);
        parent::tearDown();
    }

    /**
     * Every kind is backed by an advanced integer preference
     */
    public function testEveryKindIsDeclared(): void
    {
        foreach (UploadSize::cases() as $kind) {
            $this->assertTrue(
                PreferencesSchema::has($kind->value),
                $kind->name . ' points to an undeclared preference'
            );
            $this->assertSame(
                PreferencesSchema::TYPE_INT,
                PreferencesSchema::getType($kind->value),
                $kind->value . ' is not an integer'
            );
            $this->assertTrue(
                PreferencesSchema::isAdvanced($kind->value),
                $kind->value . ' is not an advanced preference'
            );
        }
    }

    /**
     * Without any preferences at hand, the schema default applies
     */
    public function testFallsBackOnSchemaDefault(): void
    {
        unset($GLOBALS['preferences']);

        $this->assertSame(File::MAX_FILE_SIZE, UploadSize::Images->get());
        $this->assertSame(File::MAX_FILE_SIZE, UploadSize::Attachments->get());
        $this->assertSame(File::MAX_FILE_SIZE, UploadSize::Documents->get());
        $this->assertSame(File::MAX_FILE_SIZE, UploadSize::Imports->get());
        $this->assertSame(
            DynamicField::DEFAULT_MAX_FILE_SIZE,
            UploadSize::DynamicFiles->get()
        );
    }

    /**
     * The value comes from the preferences the caller hands over
     */
    public function testReadsGivenPreferences(): void
    {
        $this->preferences->pref_upload_size_attachments = 8192;

        $this->assertSame(8192, UploadSize::Attachments->get($this->preferences));
    }

    /**
     * A caller holding no preferences gets the ones the application set up
     */
    public function testReadsGlobalPreferences(): void
    {
        $this->preferences->pref_upload_size_documents = 4096;
        $GLOBALS['preferences'] = $this->preferences;

        $this->assertSame(4096, UploadSize::Documents->get());
    }

    /**
     * Each kind of upload follows its own preference
     */
    public function testEachUploadFollowsItsOwnSize(): void
    {
        $this->preferences->pref_upload_size_images = 512;
        $this->preferences->pref_upload_size_attachments = 8192;
        $this->preferences->pref_upload_size_documents = 4096;
        $this->preferences->pref_upload_size_imports = 64;
        $GLOBALS['preferences'] = $this->preferences;

        $this->assertSame(512, (new Picture())->getMaxLength());
        $this->assertSame(8192, (new Mailing($this->preferences))->getMaxLength());
        $this->assertSame(4096, (new Document($this->zdb))->getMaxLength());
        $this->assertSame(64, $this->container->get(CsvIn::class)->getMaxLength());
    }

    /**
     * A size is a positive number of Ko
     */
    public function testRejectsAnEmptySize(): void
    {
        $this->logSuperAdmin();

        $this->assertFalse(
            $this->preferences->setValue('pref_upload_size_images', 0, $this->login)
        );
        $this->assertSame(
            ["- Value for 'pref_upload_size_images' must be a positive number!"],
            $this->preferences->getErrors()
        );

        $this->assertTrue(
            $this->preferences->setValue('pref_upload_size_images', 8192, $this->login)
        );
        $this->assertSame(8192, UploadSize::Images->get($this->preferences));

        $this->preferences->setValue(
            'pref_upload_size_images',
            PreferencesSchema::getDefaults()['pref_upload_size_images'],
            $this->login
        );
    }
}
