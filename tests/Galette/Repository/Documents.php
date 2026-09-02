<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Repository;

use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Status tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Documents extends GaletteTestCase
{
    protected int $seed = 20260901224816;

    /**
     * Test document "system" types
     */
    public function testGetSystemTypes(): void
    {
        $documents = new \Galette\Repository\Documents($this->zdb, $this->login);
        $this->assertCount(5, $documents->getSystemTypes());
    }

    /**
     * Test getList
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetList(): void
    {
        $documents = new \Galette\Repository\Documents($this->zdb, $this->login);
        $document = $this->getDocumentInstance();

        // no document yet, list is empty
        $this->assertSame([], $documents->getList());

        $uploaded_files = [
            'document_file' => new \Slim\Psr7\UploadedFile(
                fileNameOrStream: '/tmp/status.pdf',
                name: 'status.pdf',
                type: 'application/pdf',
                size: 2048,
                error: UPLOAD_ERR_OK
            )
        ];
        $post = [
            'document_type' => \Galette\Repository\Documents::STATUS,
            'comment' => 'Status of the association',
            'visible' => \Galette\Entity\FieldsConfig::ALL
        ];

        $this->assertTrue($document->store($post, $uploaded_files));

        //test list
        $list = $documents->getList();
        $this->assertCount(1, $list);

        $entry = array_pop($list);
        $this->assertInstanceOf(\Galette\Entity\Document::class, $entry);
        $this->assertSame('status.pdf', $entry->getDocumentFilename());
        $this->assertSame(\Galette\Repository\Documents::STATUS, $entry->getType());
        $this->assertSame('Status of the association', $entry->getComment());
        $this->assertSame(\Galette\Entity\FieldsConfig::ALL, $entry->getPermission());
        $this->assertSame('Public', $entry->getPermissionName());

        //test list by type (for public pages)
        $tlist = $documents->getTypedList();
        $this->assertCount(1, $tlist);
        $this->assertArrayHasKey(\Galette\Repository\Documents::STATUS, $tlist);
        $this->assertCount(1, $tlist[\Galette\Repository\Documents::STATUS]);

        //"upload" another document
        $document = $this->getDocumentInstance();
        $uploaded_files = [
            'document_file' => new \Slim\Psr7\UploadedFile(
                fileNameOrStream: '/tmp/afile.pdf',
                name: 'afile.pdf',
                type: 'application/pdf',
                size: 4096,
                error: UPLOAD_ERR_OK
            )
        ];
        $post = [
            'document_type' => 'An other document type',
            'comment' => '',
            'visible' => \Galette\Entity\FieldsConfig::STAFF
        ];

        $this->assertTrue($document->store($post, $uploaded_files));

        //test list - not authenticated
        $list = $documents->getList();
        $this->assertCount(1, $list);

        //test list - authenticated
        $this->logSuperAdmin();
        $list = $documents->getList();
        $this->assertCount(2, $list);

        //test list by type (for public pages)
        $tlist = $documents->getTypedList();
        $this->assertCount(2, $tlist);
        $this->assertArrayHasKey(\Galette\Repository\Documents::STATUS, $tlist);
        $this->assertArrayHasKey('An other document type', $tlist);
        $this->assertCount(1, $tlist[\Galette\Repository\Documents::STATUS]);
        $this->assertCount(1, $tlist['An other document type']);
        $this->assertTrue($this->login->logOut());

        //logged in regular member document
        $document = $this->getDocumentInstance();
        $uploaded_files = [
            'document_file' => new \Slim\Psr7\UploadedFile(
                fileNameOrStream: '/tmp/member.pdf',
                name: 'member.pdf',
                type: 'application/pdf',
                size: 4096,
                error: UPLOAD_ERR_OK
            )
        ];
        $post = [
            'document_type' => \Galette\Repository\Documents::MINUTES,
            'comment' => '',
            'visible' => \Galette\Entity\FieldsConfig::USER_READ
        ];
        $this->assertTrue($document->store($post, $uploaded_files));

        //inaccessible document
        $document = $this->getDocumentInstance();
        $uploaded_files = [
            'document_file' => new \Slim\Psr7\UploadedFile(
                fileNameOrStream: '/tmp/noaccess.pdf',
                name: 'noaccess.pdf',
                type: 'application/pdf',
                size: 4096,
                error: UPLOAD_ERR_OK
            )
        ];
        $post = [
            'document_type' => \Galette\Repository\Documents::MINUTES,
            'comment' => '',
            'visible' => \Galette\Entity\FieldsConfig::NOBODY
        ];
        $this->assertTrue($document->store($post, $uploaded_files));

        //test list - not authenticated
        $list = $documents->getList();
        $this->assertCount(1, $list);

        //test list - authenticated. noaccess doc should be present
        $this->logSuperAdmin();
        $documents = new \Galette\Repository\Documents($this->zdb, $this->login);
        $list = $documents->getList();
        $this->assertCount(4, $list);

        //test list by type (for public pages) - noaccess doc should not be present.
        $tlist = $documents->getTypedList();
        $this->assertCount(3, $tlist);
        $this->assertArrayHasKey(\Galette\Repository\Documents::STATUS, $tlist);
        $this->assertCount(1, $tlist[\Galette\Repository\Documents::STATUS]);
        $this->assertCount(1, $tlist['An other document type']);
        $this->assertCount(1, $tlist[\Galette\Repository\Documents::MINUTES]);
        $this->login->logOut();

        global $login;
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);

        //test list - authenticated, but not admin. noaccess doc should not be present
        $documents = new \Galette\Repository\Documents($this->zdb, $login);
        $list = $documents->getList();
        $this->assertCount(3, $list);

        //test list by type (for public pages) - noaccess doc should not be present.
        $tlist = $documents->getTypedList();
        $this->assertCount(3, $tlist);

        //regular user
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(false);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);

        //test list - authenticated, but not admin nor staff
        $documents = new \Galette\Repository\Documents($this->zdb, $login);
        $list = $documents->getList();
        $this->assertCount(2, $list);

        //test list by type (for public pages)
        $tlist = $documents->getTypedList();
        $this->assertCount(2, $tlist);

        //non logged in user
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
            ->getMock();

        $login->method('isLogged')->willReturn(false);
        $login->method('isStaff')->willReturn(false);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);

        //test list - authenticated, but not admin. noaccess doc should not be present
        $this->logSuperAdmin();
        $documents = new \Galette\Repository\Documents($this->zdb, $login);
        $list = $documents->getList();
        $this->assertCount(1, $list);

        //test list by type (for public pages) - noaccess doc should not be present.
        $tlist = $documents->getTypedList();
        $this->assertCount(1, $tlist);
    }

    /**
     * Test getTypes
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetTypes(): void
    {
        $documents = new \Galette\Repository\Documents($this->zdb, $this->login);

        //per default, retrieve ony system types
        $list_types = $documents->getSystemTypes();
        $this->assertSame($list_types, $documents->getTypes());

        //create a new type
        $document = $this->getDocumentInstance();
        $uploaded_files = [
            'document_file' => new \Slim\Psr7\UploadedFile(
                fileNameOrStream: '/tmp/afile.pdf',
                name: 'afile.pdf',
                type: 'application/pdf',
                size: 4096,
                error: UPLOAD_ERR_OK
            )
        ];
        $post = [
            'document_type' => 'An other document type',
            'comment' => '',
            'visible' => \Galette\Entity\FieldsConfig::STAFF
        ];

        $this->assertTrue($document->store($post, $uploaded_files));

        //check new type is present from getTypes() method
        $list_types['An other document type'] = 'An other document type';
        $this->assertSame($list_types, $documents->getTypes());
    }
}
