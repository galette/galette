<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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

use function Safe\file_get_contents;
use function Safe\fopen;
use function Safe\fwrite;
use function Safe\rewind;

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
     * @param ?string $form_name Form name
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
            'mode'              => (($this->isAjax($request)) ? 'ajax' : ''),
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
     * @param ?string $form_name Form name
     */
    public function doAdd(Request $request, Response $response, ?string $form_name = null): Response
    {
        $document = new Document($this->zdb);
        return $this->store($request, $response, $document);
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * List page
     *
     * @param string|null     $option One of 'page' or 'order'
     * @param int|string|null $value  Value of the option
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
     * Public list page
     */
    public function publicList(Response $response): Response
    {
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
     */
    public function filter(Request $request, Response $response): Response
    {
        //no filtering
        return $response;
    }

    /**
     * Get a document
     *
     * @param int $id Document ID
     */
    public function getDocument(
        Response $response,
        int $id
    ): Response {
        $document = new Document($this->zdb, $id);

        if (!$document->canShow($this->login)) {
            return $this->redirectWithErrors(
                response: $response,
                errors: [_T("You do not have permission for requested URL.")],
                redirect_url: $this->routeparser->urlFor('slash')
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
     * @param int $id Document id
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
            'mode'              => (($this->isAjax($request)) ? 'ajax' : ''),
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
     * @param int $id Document id
     */
    public function doEdit(Request $request, Response $response, int $id): Response
    {
        $document = new Document($this->zdb, $id);
        return $this->store($request, $response, $document);
    }

    /**
     * Store a document
     */
    private function store(Request $request, Response $response, Document $document): Response
    {
        $post = $request->getParsedBody();

        $error_detected = [];
        $warning_detected = [];
        $success_detected = [];

        if (isset($post['cancel'])) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->cancelUri($this->getArgs($request)));
        }

        try {
            $document->store($post, $request->getUploadedFiles());
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

        //handle redirections
        if (count($error_detected) > 0) {
            //something went wrong :'(
            $this->session->document = $document;
            $redirect_url = $this->routeparser->urlFor('addDocument');
        } else {
            $success_detected[] = _T('Document has been successfully stored!');
            $redirect_url = $this->routeparser->urlFor('documentsList');
        }
        return $this->redirect(
            response: $response,
            redirect_url: $redirect_url,
            successes: $success_detected,
            warnings: $warning_detected,
            errors: $error_detected
        );
    }

    // /CRUD - Update
    // CRUD - Delete

    /**
     * Get redirection URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function redirectUri(array $args): string
    {
        return $this->routeparser->urlFor('documentsList');
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
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
     */
    protected function doDelete(array $args, array $post): bool
    {
        $document = new Document($this->zdb, (int)$post['id']);
        return $document->remove();
    }

    // /CRUD - Delete
    // /CRUD
}
