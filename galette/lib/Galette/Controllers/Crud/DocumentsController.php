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

namespace Galette\Controllers\Crud;

use Galette\Core\Galette;
use Galette\Entity\Document;
use Galette\Filters\DocumentsList;
use Galette\IO\File;
use Throwable;
use Galette\Controllers\CrudController;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Analog\Analog;

/**
 * Galette documents controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class DocumentsController extends CrudController
{
    // CRUD - Create

    /**
     * Add page
     *
     * @param Request  $request   PSR Request
     * @param Response $response  PSR Response
     * @param ?string  $form_name Form name
     *
     * @return Response
     */
    public function add(Request $request, Response $response, ?string $form_name = null): Response
    {
        if (isset($this->session->document)) {
            $document = $this->session->document;
            unset($this->session->document);
        } else {
            $document = new Document($this->zdb);
        }
        $params = [
            'page_title'        => _T("Add document"),
            'action'            => 'add',
            'mode'              => (($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') ? 'ajax' : ''),
            'document'          => $document,
            'types'             => $document->getSystemTypes(),
            'perm_names'        => $document::getPermissionsList(true),
            'html_editor'       => true,
            'documentation'     => 'usermanual/documents.html#management'
        ];

        // display page
        $this->view->render(
            $response,
            'pages/document_form.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Add action
     *
     * @param Request  $request   PSR Request
     * @param Response $response  PSR Response
     * @param ?string  $form_name Form name
     *
     * @return Response
     */
    public function doAdd(Request $request, Response $response, ?string $form_name = null): Response
    {
        $post = $request->getParsedBody();

        $error_detected = [];
        $warning_detected = [];

        if (isset($post['cancel'])) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->cancelUri($this->getArgs($request)));
        }

        $document = new Document($this->zdb);

        try {
            $document->store($post, $_FILES);
            $error_detected = $document->getErrors();
            $warning_detected = $document->getWarnings();
        } catch (Throwable $e) {
            $msg = 'An error occurred adding new document.';
            Analog::log(
                $msg . ' | '
                . $e->getMessage(),
                Analog::ERROR
            );
            if (Galette::isDebugEnabled()) {
                throw $e;
            }
            $error_detected[] = _T('An error occurred adding document :(');
        }

        //flash messages
        if (count($error_detected) > 0) {
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        } else {
            $this->flash->addMessage(
                'success_detected',
                _T('Document has been successfully stored!')
            );
        }

        if (count($warning_detected) > 0) {
            foreach ($warning_detected as $warning) {
                $this->flash->addMessage(
                    'warning_detected',
                    $warning
                );
            }
        }

        //handle redirections
        if (count($error_detected) > 0) {
            //something went wrong :'(
            $this->session->document = $document;
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('addDocument')
                );
        } else {
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('documentsList')
                );
        }
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * List page
     *
     * @param Request             $request  PSR Request
     * @param Response            $response PSR Response
     * @param string|null         $option   One of 'page' or 'order'
     * @param integer|string|null $value    Value of the option
     *
     * @return Response
     */
    public function list(
        Request $request,
        Response $response,
        ?string $option = null,
        int|string|null $value = null,
    ): Response {
        $filters = new DocumentsList();

        $document = new Document($this->zdb);
        $documents = $document->getList();

        //assign pagination variables to the template and add pagination links
        $filters->setViewPagination($this->routeparser, $this->view);

        $params = [
            'page_title' => _T("Documents"),
            'nb' => count($documents),
            'documents' => $documents,
            'filters' => $filters,
            'documentation' => 'usermanual/documents.html'
        ];

        // display page
        $this->view->render(
            $response,
            'pages/documents_list.html.twig',
            $params
        );
        return $response;
    }

    /**
     * List page
     *
     * @param Request             $request  PSR Request
     * @param Response            $response PSR Response
     * @param string|null         $option   One of 'page' or 'order'
     * @param integer|string|null $value    Value of the option
     *
     * @return Response
     */
    public function publicList(
        Request $request,
        Response $response,
        ?string $option = null,
        int|string|null $value = null,
    ): Response {
        $document = new Document($this->zdb);
        $documents = $document->getTypedList();

        $params = [
            'page_title' => _T("Documents"),
            'typed_documents' => $documents,
            'documentation' => 'usermanual/documents.html#public-list'
        ];

        // display page
        $this->view->render(
            $response,
            'pages/documents_public_list.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Filtering
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function filter(Request $request, Response $response): Response
    {
        //no filtering
        return $response;
    }

    /**
     * Get a document
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Document ID
     *
     * @return Response
     */
    public function getDocument(
        Request $request,
        Response $response,
        int $id
    ): Response {
        $document = new Document($this->zdb, $id);

        if (!$document->canShow($this->login)) {
            $this->flash->addMessage(
                'error_detected',
                _T("You do not have permission for requested URL.")
            );

            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor(
                        'slash'
                    )
                );
        }

        if (file_exists($document->getDestDir() . $document->getDocumentFilename())) {
            $type = File::getMimeType($document->getDestDir() . $document->getDocumentFilename());

            $response = $response->withHeader('Content-Description', 'File Transfer')
                ->withHeader('Content-Type', $type)
                ->withHeader('Content-Disposition', 'attachment;filename="' . $document->getDocumentFilename() . '"')
                ->withHeader('Pragma', 'no-cache')
                ->withHeader('Content-Transfer-Encoding', 'binary')
                ->withHeader('Expires', '0')
                ->withHeader('Cache-Control', 'must-revalidate')
                ->withHeader('Pragma', 'public');

            $stream = fopen('php://memory', 'r+');
            fwrite($stream, file_get_contents($document->getDestDir() . $document->getDocumentFilename()));
            rewind($stream);

            return $response->withBody(new \Slim\Psr7\Stream($stream));
        } else {
            Analog::log(
                'A request has been made to get a document file named `'
                . $document->getDocumentFilename() . '` that does not exists.',
                Analog::WARNING
            );

            $this->flash->addMessage(
                'error_detected',
                _T("The file does not exists or cannot be read :(")
            );

            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor(
                        'slash'
                    )
                );
        }
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Document id
     *
     * @return Response
     */
    public function edit(Request $request, Response $response, int $id): Response
    {
        if (isset($this->session->document)) {
            $document = $this->session->document;
            unset($this->session->document);
        } else {
            $document = new Document($this->zdb, $id);
        }
        $params = [
            'page_title'        => _T("Edit document"),
            'action'            => 'edit',
            'mode'              => (($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') ? 'ajax' : ''),
            'document'          => $document,
            'types'             => $document->getSystemTypes(),
            'perm_names'        => $document::getPermissionsList(true),
            'html_editor'       => true,
            'documentation'     => 'usermanual/documents.html#management'
        ];

        // display page
        $this->view->render(
            $response,
            'pages/document_form.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Edit action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Document id
     *
     * @return Response
     */
    public function doEdit(Request $request, Response $response, int $id): Response
    {
        $post = $request->getParsedBody();

        $error_detected = [];
        $warning_detected = [];

        if (isset($post['cancel'])) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->cancelUri($this->getArgs($request)));
        }

        $document = new Document($this->zdb, $id);

        try {
            $document->store($post, $_FILES);
            $error_detected = $document->getErrors();
            $warning_detected = $document->getWarnings();
        } catch (Throwable $e) {
            $msg = 'An error occurred adding new document.';
            Analog::log(
                $msg . ' | '
                . $e->getMessage(),
                Analog::ERROR
            );
            if (Galette::isDebugEnabled()) {
                throw $e;
            }
            $error_detected[] = _T('An error occurred adding document :(');
        }

        //flash messages
        if (count($error_detected) > 0) {
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        } else {
            $this->flash->addMessage(
                'success_detected',
                _T('Document has been successfully stored!')
            );
        }

        if (count($warning_detected) > 0) {
            foreach ($warning_detected as $warning) {
                $this->flash->addMessage(
                    'warning_detected',
                    $warning
                );
            }
        }

        //handle redirections
        if (count($error_detected) > 0) {
            //something went wrong :'(
            $this->session->document = $document;
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('addDocument')
                );
        } else {
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('documentsList')
                );
        }
    }

    // /CRUD - Update
    // CRUD - Delete

    /**
     * Get redirection URI
     *
     * @param array<string,mixed> $args Route arguments
     *
     * @return string
     */
    public function redirectUri(array $args): string
    {
        return $this->routeparser->urlFor('documentsList');
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     *
     * @return string
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'doRemoveDocument',
            ['id' => $args['id']]
        );
    }

    /**
     * Get confirmation removal page title
     *
     * @param array<string,mixed> $args Route arguments
     *
     * @return string
     */
    public function confirmRemoveTitle(array $args): string
    {
        return _T('Delete document');
    }

    /**
     * Remove object
     *
     * @param array<string,mixed> $args Route arguments
     * @param array<string,mixed> $post POST values
     *
     * @return boolean
     */
    protected function doDelete(array $args, array $post): bool
    {
        $document = new Document($this->zdb, (int)$post['id']);
        return $document->remove();
    }

    // /CRUD - Delete
    // /CRUD
}
