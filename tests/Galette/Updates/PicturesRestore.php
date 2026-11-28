<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Updates;

use Galette\Tests\GaletteTestCase;
use Laminas\Db\Adapter\Adapter;

use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\tempnam;
use function Safe\unlink;

/**
 * 1.40 upgrade script tests: pictures stored in the database are written to disk
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PicturesRestore extends GaletteTestCase
{
    //the pictures table is created and dropped, which implicitly commits transactions on MySQL
    protected bool $db_transactions = false;

    private const string TABLE = 'pictures';

    /** @var string[] Files created by the test, removed on tear down */
    private array $files = [];

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->seed = 20261121;
        include_once GALETTE_BASE_PATH . '/install/scripts/upgrade-to-1.40.php';
        $this->zdb->drop(self::TABLE, maymiss: true);
    }

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        if (isset($this->zdb)) {
            //even if test has failed
            $this->zdb->drop(self::TABLE, maymiss: true);
            $this->cleanMembers();
        }
        foreach ($this->files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    /**
     * Create pictures table as Galette 1.3 did, with pictures in it
     *
     * @param array<int, array{string, string}> $pictures Contents and format, by id
     */
    private function createPicturesTable(array $pictures): void
    {
        $table = PREFIX_DB . self::TABLE;
        $this->zdb->db->query(
            $this->zdb->isPostgres()
                ? 'CREATE TABLE ' . $table . ' (id_adh integer DEFAULT 0 NOT NULL, picture bytea NOT NULL,'
                    . ' format character varying(30) DEFAULT \'\' NOT NULL, PRIMARY KEY (id_adh))'
                : 'CREATE TABLE ' . $table . ' (id_adh int unsigned NOT NULL default 0, picture mediumblob NOT NULL,'
                    . ' format varchar(30) NOT NULL default \'\', PRIMARY KEY (id_adh))',
            Adapter::QUERY_MODE_EXECUTE
        );

        $insert = $this->zdb->insert(self::TABLE);
        $insert->values(['id_adh' => ':id_adh', 'picture' => ':picture', 'format' => ':format']);
        $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);
        $container = $stmt->getParameterContainer();
        $container->offsetSet('picture', ':picture', $container::TYPE_LOB);
        $stmt->setParameterContainer($container);
        foreach ($pictures as $id => [$contents, $format]) {
            $stmt->execute(['id_adh' => $id, 'picture' => $contents, 'format' => $format]);
        }
    }

    /**
     * Get updater
     */
    private function getUpdater(): \Galette\Updates\UpgradeTo140
    {
        $updater = new \Galette\Updates\UpgradeTo140();
        (new \ReflectionProperty($updater, 'zdb'))->setValue($updater, $this->zdb);
        return $updater;
    }

    /**
     * Expect a file, and remove it on tear down
     *
     * @param string $file File path
     */
    private function track(string $file): string
    {
        $this->files[] = $file;
        return $file;
    }

    /**
     * Test pictures missing on disk are written, existing files are kept
     */
    public function testRestorePictures(): void
    {
        $member = $this->getMemberOne();
        $other = $this->getMemberTwo();
        $image = file_get_contents(GALETTE_ROOT . '../tests/fake_image.jpg');

        $logo = $this->track(GALETTE_PHOTOS_PATH . 'custom_logo.png');
        $print_logo = $this->track(GALETTE_PHOTOS_PATH . 'custom_print_logo.gif');
        $restored = $this->track(GALETTE_PHOTOS_PATH . $member->id . '.jpg');
        $existing = $this->track(GALETTE_PHOTOS_PATH . $other->id . '.webp');
        $this->assertFalse(file_exists($logo));
        $this->assertFalse(file_exists($print_logo));
        $this->assertFalse(file_exists($restored));

        //a file exists already, under another extension
        file_put_contents($existing, 'on disk');
        $this->track(GALETTE_PHOTOS_PATH . $other->id . '.png');

        $this->createPicturesTable([
            0 => ["logo\0binary", 'png'],
            999999 => ['print logo', 'gif'],
            $member->id => [$image, 'jpg'],
            $other->id => ['in database', 'png'],
        ]);

        $updater = $this->getUpdater();
        $this->assertTrue((new \ReflectionMethod($updater, 'update'))->invoke($updater));
        $this->assertFalse($updater->hasErrors());

        $this->assertSame("logo\0binary", file_get_contents($logo));
        $this->assertSame('print logo', file_get_contents($print_logo));
        $this->assertSame($image, file_get_contents($restored));
        $this->assertSame('on disk', file_get_contents($existing));
        $this->assertFalse(file_exists(GALETTE_PHOTOS_PATH . $other->id . '.png'));
        //temporary files have been renamed
        $this->assertSame([], glob(GALETTE_PHOTOS_PATH . '*.tmp'));

        $this->assertSame(
            [
                [
                    'message' => '3 pictures have been written to ' . GALETTE_PHOTOS_PATH . '.',
                    'type' => \Galette\Updater\AbstractUpdater::REPORT_SUCCESS,
                    'res' => true
                ]
            ],
            $updater->getReport()
        );

        //restored member picture is found
        $this->assertTrue((new \Galette\Core\Picture($member->id))->hasPicture());

        //running again writes nothing
        $updater = $this->getUpdater();
        $this->assertTrue((new \ReflectionMethod($updater, 'update'))->invoke($updater));
        $this->assertSame([], $updater->getReport());
    }

    /**
     * Test a missing table is not an error
     */
    public function testWithoutTable(): void
    {
        $updater = $this->getUpdater();
        $this->assertTrue((new \ReflectionMethod($updater, 'update'))->invoke($updater));
        $this->assertSame([], $updater->getReport());
    }

    /**
     * Test update stops when a picture cannot be written
     */
    public function testWriteFailure(): void
    {
        $this->createPicturesTable([42 => ['picture', 'jpg']]);
        //a file cannot be a directory
        $file = $this->track(tempnam(sys_get_temp_dir(), 'galette-pictures'));

        $updater = $this->getUpdater();
        $this->assertFalse(
            (new \ReflectionMethod($updater, 'restorePictures'))->invoke(
                $updater,
                self::TABLE,
                'id_adh',
                $file . '/'
            )
        );
        $this->assertTrue($updater->hasErrors());
        $this->assertSame('Unable to write picture ' . $file . '/42.jpg.', $updater->getReport()[0]['message']);
        $this->assertFalse(file_exists($file . '/42.jpg.tmp'));
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Unable to restore picture 42 from ' . PREFIX_DB . self::TABLE);

        //table is kept, update can be run again
        $this->assertTrue($this->zdb->tableExists(self::TABLE));
    }
}
