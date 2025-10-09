<?php

/**
 * Copyright © 2003-2025 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace GaletteTests\Entity;

use Galette\GaletteTestCase;

/**
 * Status tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Document extends GaletteTestCase
{
    protected int $seed = 20240312213127;

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $this->deleteDocuments();

        //drop dynamic translations
        $delete = $this->zdb->delete(\Galette\Core\L10n::TABLE);
        $this->zdb->execute($delete);
    }

    /**
     * Delete documents
     *
     * @return void
     */
    private function deleteDocuments(): void
    {
        $delete = $this->zdb->delete(\Galette\Entity\Document::TABLE);
        $this->zdb->execute($delete);
    }

    /**
     * Test document object
     *
     * @return void
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
     *
     * @return void
     */
    public function testGetSystemTypes(): void
    {
        $document = new \Galette\Entity\Document($this->zdb);
        $this->assertCount(5, $document->getSystemTypes());
    }

    /**
     * Test getList
     *
     * @return void
     */
    public function testGetList(): void
    {
        $document = $this->getDocumentInstance();

        // no document yet, list is empty
        $this->assertSame([], $document->getList());

        $uploaded_files = [
            'document_file' => new \Slim\Psr7\UploadedFile(
                '/tmp/status.pdf',
                'status.pdf',
                'application/pdf',
                2048,
                UPLOAD_ERR_OK
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
                '/tmp/afile.pdf',
                'afile.pdf',
                'application/pdf',
                4096,
                UPLOAD_ERR_OK
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
                '/tmp/member.pdf',
                'member.pdf',
                'application/pdf',
                4096,
                UPLOAD_ERR_OK
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
                '/tmp/noaccess.pdf',
                'noaccess.pdf',
                'application/pdf',
                4096,
                UPLOAD_ERR_OK
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
            ->setConstructorArgs(array($this->zdb, new \Galette\Core\I18n()))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
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
            ->setConstructorArgs(array($this->zdb, new \Galette\Core\I18n()))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
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
            ->setConstructorArgs(array($this->zdb, new \Galette\Core\I18n()))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
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
     *
     * @return void
     */
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
                '/tmp/afile.pdf',
                'afile.pdf',
                'application/pdf',
                4096,
                UPLOAD_ERR_OK
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
