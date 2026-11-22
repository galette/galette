<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\IO;

use Galette\Core\Mailing;
use Galette\Core\PreferencesSchema;
use Galette\Entity\Document;
use Galette\IO\UploadExtensions;
use Galette\Tests\GaletteTestCase;

use function Safe\file_put_contents;
use function Safe\tempnam;
use function Safe\unlink;

/**
 * Allowed upload extensions tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class UploadExtensionsTest extends GaletteTestCase
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
     * The preference is an advanced one, and its default is a valid list
     */
    public function testPreferenceIsDeclared(): void
    {
        $this->assertTrue(PreferencesSchema::has(UploadExtensions::PREFERENCE));
        $this->assertSame(PreferencesSchema::TYPE_EXTENSIONS, PreferencesSchema::getType(UploadExtensions::PREFERENCE));
        $this->assertTrue(PreferencesSchema::isAdvanced(UploadExtensions::PREFERENCE));

        $defaults = UploadExtensions::parse(UploadExtensions::DEFAULT);
        $this->assertIsArray($defaults);
        $this->assertContains('pdf', $defaults);
        $this->assertSame([], UploadExtensions::getUnsafe($defaults));
        $this->assertNotContains('svg', $defaults);
        $this->assertNotContains('html', $defaults);
    }

    /**
     * Lists are read whatever the separator, the case or a leading dot
     */
    public function testParse(): void
    {
        $this->assertSame(['pdf', 'odt', 'zip', '7z'], UploadExtensions::parse(' PDF, .odt;zip  7z pdf '));
        $this->assertSame([], UploadExtensions::parse(''));
        $this->assertSame([], UploadExtensions::parse('  '));
        $this->assertNull(UploadExtensions::parse('pdf tar.gz'));
        $this->assertNull(UploadExtensions::parse('pdf *'));
        $this->assertNull(UploadExtensions::parse('pdf|php'));
    }

    /**
     * File names are checked on their last extension, case insensitively
     */
    public function testAllows(): void
    {
        $this->assertTrue(UploadExtensions::allows('Status.PDF', ['pdf']));
        $this->assertFalse(UploadExtensions::allows('minutes.7z', ['pdf']));
        $this->assertFalse(UploadExtensions::allows('README', ['pdf']));
        $this->assertTrue(UploadExtensions::allows('README', []));
        $this->assertTrue(UploadExtensions::allows('minutes.7z', []));
        $this->assertSame('.pdf,.odt', UploadExtensions::accept(['pdf', 'odt']));
        $this->assertSame(['php5', 'phtml'], UploadExtensions::getUnsafe(['pdf', 'php5', 'phtml']));
    }

    /**
     * The list comes from the given preferences, the global ones, or the defaults
     */
    public function testGet(): void
    {
        unset($GLOBALS['preferences']);
        $this->assertSame(UploadExtensions::parse(UploadExtensions::DEFAULT), UploadExtensions::get());

        $this->preferences->pref_upload_extensions = 'pdf odt';
        $this->assertSame(['pdf', 'odt'], UploadExtensions::get($this->preferences));

        $GLOBALS['preferences'] = $this->preferences;
        $this->assertSame(['pdf', 'odt'], UploadExtensions::get());

        $this->preferences->pref_upload_extensions = '';
        $this->assertSame([], UploadExtensions::get());

        //a value stored behind the settings back is not trusted
        $prefs = new \ReflectionProperty($this->preferences, 'prefs');
        $prefs->setValue($this->preferences, [UploadExtensions::PREFERENCE => 'pdf|php'] + $prefs->getValue($this->preferences));
        $this->assertSame(UploadExtensions::parse(UploadExtensions::DEFAULT), UploadExtensions::get());
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Invalid allowed upload extensions, falling back to defaults.');
    }

    /**
     * The setting is normalized, and cannot let in what a web server could run
     */
    public function testSetting(): void
    {
        $this->logSuperAdmin();

        $this->assertTrue($this->preferences->setValue(UploadExtensions::PREFERENCE, 'PDF, .odt, 7z', $this->login));
        $this->assertSame('pdf odt 7z', $this->preferences->pref_upload_extensions);

        $this->assertFalse($this->preferences->setValue(UploadExtensions::PREFERENCE, 'pdf tar.gz', $this->login));
        $this->assertSame(
            ["- Value for 'pref_upload_extensions' must be file extensions such as pdf or odt separated by spaces!"],
            $this->preferences->getErrors()
        );

        $this->assertFalse($this->preferences->setValue(UploadExtensions::PREFERENCE, 'pdf php phar', $this->login));
        $this->assertSame(
            ["- 'pref_upload_extensions' cannot allow php, phar: a web server could run such files."],
            $this->preferences->getErrors()
        );
        $this->assertSame('pdf odt 7z', $this->preferences->pref_upload_extensions);

        $this->assertTrue($this->preferences->setValue(UploadExtensions::PREFERENCE, '', $this->login));
        $this->assertSame('', $this->preferences->pref_upload_extensions);

        $this->preferences->setValue(UploadExtensions::PREFERENCE, UploadExtensions::DEFAULT, $this->login);
    }

    /**
     * Documents and mailings attachments follow the list
     */
    public function testUploadsFollowTheList(): void
    {
        $this->preferences->pref_upload_extensions = 'pdf';
        $GLOBALS['preferences'] = $this->preferences;

        $this->assertSame('pdf', (new Document($this->zdb))->getAllowedExts());

        $mailing = new Mailing($this->preferences);
        $tmp = tempnam(sys_get_temp_dir(), 'galette');
        file_put_contents($tmp, 'content');
        try {
            $this->assertSame(
                Mailing::INVALID_EXTENSION,
                $mailing->storeFile(new \Slim\Psr7\UploadedFile(
                    fileNameOrStream: $tmp,
                    name: 'minutes.7z',
                    size: 7,
                    error: UPLOAD_ERR_OK
                ))
            );
            $this->assertSame([], $mailing->attachments);
            $this->expectLogEntry(\Analog\Analog::ERROR, 'Invalid extension for file minutes.7z.');
            $this->assertSame(
                'File extension is not allowed, only pdf files are.',
                (new \ReflectionMethod($mailing, 'getErrorMessageFromCode'))->invoke($mailing, Mailing::INVALID_EXTENSION)
            );
        } finally {
            unlink($tmp);
            $mailing->removeAttachments(temp: true);
        }
    }
}
