<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

namespace Galette\Tests\Controllers;

use Galette\Tests\GaletteRoutingTestCase;

/**
 * Documents controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DocumentsController extends GaletteRoutingTestCase
{
    protected int $seed = 20250916084243;

    /**
     * Returns a fresh document instance (PDF status of the association)
     *
     * @param int $visibility Visibility of the document
     */
    private function createStatusDocument(int $visibility = \Galette\Entity\FieldsConfig::ALL): \Galette\Entity\Document
    {
        $this->assertTrue(copy(GALETTE_TESTS_PATH . '/fixtures/status.pdf', sys_get_temp_dir() . '/status.pdf'));
        $uploaded_files = [
            'document_file' => new \Slim\Psr7\UploadedFile(
                sys_get_temp_dir() . '/status.pdf',
                'status.pdf',
                'application/pdf',
                filesize(sys_get_temp_dir() . '/status.pdf'),
                UPLOAD_ERR_OK
            )
        ];
        $post = [
            'document_type' => \Galette\Entity\Document::STATUS,
            'comment' => 'Status of the association',
            'visible' => $visibility
        ];
        $document = new \Galette\Entity\Document($this->zdb);
        $this->assertTrue($document->store($post, $uploaded_files));
        $this->assertTrue(file_exists(GALETTE_DOCUMENTS_PATH . '/status.pdf'));

        return $document;
    }

    /**
     * Test documents list
     */
    public function testList(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $this->login->logOut();

        $route_name = 'documentsList';
        $request = $this->createRequest($route_name);

        //Refused from authenticate middleware
        //all document routes have the same ACLs, no need to test each one.
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //simple members do not have access
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertSame($mdata['login_adh'], $member_one->login);
        $test_response = $this->app->handle($request);
        $this->expectAuthMiddlewareRefused($test_response);

        //superadmin have access
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            '0 documents',
            $body
        );

        //create one document
        $document = $this->getDocumentInstance();
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

        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            '1 document',
            $body
        );

        //staff have access
        $staff_member = $this->getStaffMember($member_one);
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            '1 document',
            $body
        );

        $this->resetStaffStatus($staff_member, $member_one);

        //set member as admin
        $adm_member = $this->getAdminMember($member_one);
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isAdmin());

        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            '1 document',
            $body
        );

        //reset admin status
        $this->resetAdminStatus($adm_member);

        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_one]));

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertTrue($this->login->isGroupManager($g1->getId()));

        $test_response = $this->app->handle($request);
        $this->expectAuthMiddlewareRefused($test_response);
    }

    /**
     * Test documents list
     */
    public function testPublicList(): void
    {
        $route_name = 'documentsPublicList';
        $request = $this->createRequest($route_name);

        //public pages are not active by default
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['Unauthorized']]);

        // enable public page
        $this->preferences->pref_publicpages_visibility_documents = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PUBLIC;
        $this->assertTrue($this->preferences->store());

        $test_response = $this->app->handle($request);

        //reset
        $this->preferences->pref_publicpages_visibility_documents = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PRIVATE;
        $this->assertTrue($this->preferences->store());

        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            'No document',
            $body
        );
    }

    /**
     * Test documents add page
     */
    public function testAddPage(): void
    {
        $this->logSuperAdmin();

        $route_name = 'addDocument';
        $request = $this->createRequest($route_name);

        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            'Add document',
            $body
        );

        $this->login->logOut();
    }

    /**
     * Test documents edit page
     */
    public function testEditPage(): void
    {
        //create one document
        $document = $this->getDocumentInstance();
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

        $route_name = 'editDocument';
        $route_arguments = ['id' => $document->getId()];

        $this->logSuperAdmin();
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            'Edit document',
            $body
        );
        $this->assertStringContainsString(
            sprintf('<input type="hidden" name="id" id="id" value="%1$s"/>', $document->getId()),
            $body
        );

        $this->login->logout();
    }

    /**
     * Test add document
     */
    public function testAddDocument(): void
    {
        $route_name = 'doAddDocument';

        //login is required to access this page
        $request = $this->createRequest($route_name, [], 'POST');

        $this->assertTrue(copy(GALETTE_TESTS_PATH . '/fixtures/status.pdf', sys_get_temp_dir() . '/status.pdf'));
        $uploaded_files = [
            'document_file' => new \Slim\Psr7\UploadedFile(
                sys_get_temp_dir() . '/status.pdf',
                'status.pdf',
                'application/pdf',
                filesize(sys_get_temp_dir() . '/status.pdf'),
                UPLOAD_ERR_OK
            )
        ];
        $post = [
            'document_type' => \Galette\Entity\Document::STATUS,
            'comment' => 'Status of the association',
            'visible' => \Galette\Entity\FieldsConfig::ALL
        ];
        $request = $request->withParsedBody($post);
        $request = $request->withUploadedFiles($uploaded_files);

        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('documentsList')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['Document has been successfully stored!']]);

        //check document file is present
        $this->assertTrue(file_exists(GALETTE_DOCUMENTS_PATH . '/status.pdf'));
        unlink(GALETTE_DOCUMENTS_PATH . '/status.pdf');

        $this->login->logout();
    }

    /**
     * Test edit document
     */
    public function testEditDocument(): void
    {
        $this->logSuperAdmin();

        $document = $this->createStatusDocument();

        $route_name = 'doEditDocument';
        $route_arguments = ['id' => $document->getId()];
        $request = $this->createRequest($route_name, $route_arguments, 'POST');
        $post = [
            'document_type' => $document->getType(),
            'comment' => 'Edited comment',
            'visible' => $document->getPermission(),
        ];

        $request = $request->withParsedBody($post);

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('documentsList')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['Document has been successfully stored!']]);
        $this->login->logout();
    }

    /**
     * Test remove document page
     */
    public function testRemovePage(): void
    {
        $this->logSuperAdmin();

        $document = $this->getDocumentInstance();
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

        $route_name = 'removeDocument';
        $route_arguments = ['id' => $document->getId()];

        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Delete document', $body);
        $this->expectOK($test_response);
    }

    /**
     * Test delete document
     */
    public function testDeleteDocument(): void
    {
        $this->logSuperAdmin();

        $document = $this->createStatusDocument();
        $route_name = 'doRemoveDocument';

        $request = $this->createRequest($route_name, ['id' => $document->getID()], 'POST');
        $request = $request->withParsedBody(['id' => $document->getID()]);

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('documentsList')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['Removal has not been confirmed!']]);

        $request = $request->withParsedBody(['id' => $document->getId(), 'confirm' => true]);
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('documentsList')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['Successfully deleted!']]);

        $this->assertFalse(file_exists(GALETTE_DOCUMENTS_PATH . '/status.pdf'));
    }

    /**
     * Test get document file
     */
    public function testGetDocument(): void
    {
        //visible only to admins
        $document = $this->createStatusDocument(\Galette\Entity\FieldsConfig::ADMIN);

        $this->logSuperAdmin();
        $route_name = 'getDocumentFile';
        $route_arguments = ['id' => $document->getId()];
        $request = $this->createRequest($route_name, $route_arguments);

        $test_response = $this->app->handle($request);
        $expected_headers = [
            'Content-Description' => ['File Transfer'],
            'Content-Type' => ['application/pdf'],
            'Content-Disposition' => ['attachment;filename="status.pdf"'],
            'Pragma' => ['public'],
            'Content-Transfer-Encoding' => ['binary'],
            'Expires' => ['0'],
            'Cache-Control' => ['must-revalidate']
        ];
        $this->expectOK($test_response, $expected_headers);
        $body = (string)$test_response->getBody();
        $this->assertMatchesRegularExpression('/^%PDF-\d\.\d/', $body);
        $this->login->logout();

        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['You do not have permission for requested URL.']]);
    }

    /**
     * Test get document file
     */
    public function testGetMissingDocument(): void
    {
        $this->logSuperAdmin();

        $document = $this->getDocumentInstance();
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

        $route_name = 'getDocumentFile';
        $route_arguments = ['id' => $document->getId()];
        $request = $this->createRequest($route_name, $route_arguments);

        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::WARNING, 'A request has been made to get a document file named `status.pdf` that does not exists.');
        $this->expectFlashData(['error_detected' => ['The file does not exists or cannot be read :(']]);
        $this->login->logout();
    }
}
