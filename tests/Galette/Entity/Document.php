<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Entity;

use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Status tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Document extends GaletteTestCase
{
    protected int $seed = 20240312213127;

    /**
     * Test document object
     */
    public function testObject(): void
    {
        $document = new \Galette\Entity\Document($this->zdb);

        //getters only
        $this->assertSame('', $document->getDocumentFilename());
        $this->assertSame($document->getDestDir(), $document->getURL());
        $this->assertNull($document->getID());

        //setters and getters
        $this->assertSame('', $document->getType());
        $this->assertInstanceOf(\Galette\Entity\Document::class, $document->setType('mytype'));
        $this->assertSame('mytype', $document->getType());

        $this->assertNull($document->getComment());
        $this->assertInstanceOf(\Galette\Entity\Document::class, $document->setComment('any comment'));
        $this->assertSame('any comment', $document->getComment());
    }

    /**
     * Test document "system" types
     */
    public function testGetSystemTypes(): void
    {
        $document = new \Galette\Entity\Document($this->zdb);
        $this->assertCount(5, $document->getSystemTypes());
    }

    /**
     * Test getList
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetList(): void
    {
        $document = $this->getDocumentInstance();

        // no document yet, list is empty
        $this->assertSame([], $document->getList());

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
            'document_type' => \Galette\Entity\Document::STATUS,
            'comment' => 'Status of the association',
            'visible' => \Galette\Entity\FieldsConfig::ALL
        ];

        $this->assertTrue($document->store($post, $uploaded_files));

        //test list
        $list = $document->getList();
        $this->assertCount(1, $list);

        $entry = array_pop($list);
        $this->assertInstanceOf(\Galette\Entity\Document::class, $entry);
        $this->assertSame('status.pdf', $entry->getDocumentFilename());
        $this->assertSame(\Galette\Entity\Document::STATUS, $entry->getType());
        $this->assertSame('Status of the association', $entry->getComment());
        $this->assertSame(\Galette\Entity\FieldsConfig::ALL, $entry->getPermission());
        $this->assertSame('Public', $entry->getPermissionName());

        //test list by type (for public pages)
        $tlist = $document->getTypedList();
        $this->assertCount(1, $tlist);
        $this->assertArrayHasKey(\Galette\Entity\Document::STATUS, $tlist);
        $this->assertCount(1, $tlist[\Galette\Entity\Document::STATUS]);

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
        $list = $document->getList();
        $this->assertCount(1, $list);

        //test list - authenticated
        $this->logSuperAdmin();
        $list = $document->getList();
        $this->assertCount(2, $list);

        //test list by type (for public pages)
        $tlist = $document->getTypedList();
        $this->assertCount(2, $tlist);
        $this->assertArrayHasKey(\Galette\Entity\Document::STATUS, $tlist);
        $this->assertArrayHasKey('An other document type', $tlist);
        $this->assertCount(1, $tlist[\Galette\Entity\Document::STATUS]);
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
            'document_type' => \Galette\Entity\Document::MINUTES,
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
            'document_type' => \Galette\Entity\Document::MINUTES,
            'comment' => '',
            'visible' => \Galette\Entity\FieldsConfig::NOBODY
        ];
        $this->assertTrue($document->store($post, $uploaded_files));

        //test list - not authenticated
        $list = $document->getList();
        $this->assertCount(1, $list);

        //test list - authenticated. noaccess doc should be present
        $this->logSuperAdmin();
        $list = $document->getList();
        $this->assertCount(4, $list);

        //test list by type (for public pages) - noaccess doc should not be present.
        $tlist = $document->getTypedList();
        $this->assertCount(3, $tlist);
        $this->assertArrayHasKey(\Galette\Entity\Document::STATUS, $tlist);
        $this->assertCount(1, $tlist[\Galette\Entity\Document::STATUS]);
        $this->assertCount(1, $tlist['An other document type']);
        $this->assertCount(1, $tlist[\Galette\Entity\Document::MINUTES]);
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
        $list = $document->getList();
        $this->assertCount(3, $list);

        //test list by type (for public pages) - noaccess doc should not be present.
        $tlist = $document->getTypedList();
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
        $list = $document->getList();
        $this->assertCount(2, $list);

        //test list by type (for public pages)
        $tlist = $document->getTypedList();
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
        $list = $document->getList();
        $this->assertCount(1, $list);

        //test list by type (for public pages) - noaccess doc should not be present.
        $tlist = $document->getTypedList();
        $this->assertCount(1, $tlist);
    }

    /**
     * Test getTypes
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetTypes(): void
    {
        $document = new \Galette\Entity\Document($this->zdb);

        //per default, retrieve ony system types
        $list_types = $document->getSystemTypes();
        $this->assertSame($list_types, $document->getTypes());

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
        $this->assertSame($list_types, $document->getTypes());
    }
}
