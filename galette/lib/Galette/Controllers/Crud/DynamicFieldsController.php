<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers\Crud;

use Galette\Core\Galette;
use Galette\IO\File;
use Galette\Repository\DynamicFieldsSet;
use Throwable;
use Galette\Controllers\CrudController;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\DynamicFields\DynamicField;
use Analog\Analog;

use function Safe\file_get_contents;
use function Safe\fopen;
use function Safe\fwrite;
use function Safe\rename;
use function Safe\rewind;

/**
 * Galette dynamic fields controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class DynamicFieldsController extends CrudController
{
    // CRUD - Create

    /**
     * Add page
     *
     * @param ?string $form_name Form name
     */
    public function add(Request $request, Response $response, ?string $form_name = null): Response
    {
        $params = [
            'page_title'        => _T("Add field"),
            'form_name'         => $form_name,
            'action'            => 'add',
            'perm_names'        => DynamicField::getPermissionsList(),
            'mode'              => (($this->isAjax($request)) ? 'ajax' : ''),
            'field_type_names'  => DynamicField::getFieldsTypesNames()
        ];

        if ($this->session->dynamicfieldtype) {
            $params['df'] = $this->session->dynamicfieldtype;
            $this->session->dynamicfieldtype = null;
        }

        // display page
        $this->view->render(
            $response,
            'pages/configuration_dynamic_field_form.html.twig',
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
        $post = $request->getParsedBody();
        $post['form_name'] = $form_name;

        $error_detected = [];
        $warning_detected = [];
        $success_detected = [];

        if (isset($post['cancel'])) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->cancelUri($this->getArgs($request)));
        }

        $df = DynamicField::getFieldType($this->zdb, (int)$post['field_type']);

        try {
            $df->store($post);
            $error_detected = $df->getErrors();
            $warning_detected = $df->getWarnings();
        } catch (Throwable $e) {
            $msg = 'An error occurred adding new dynamic field.';
            Analog::log(
                $msg . ' | '
                . $e->getMessage(),
                Analog::ERROR
            );
            if (Galette::isDebugEnabled()) {
                throw $e;
            }
            $error_detected[] = _T('An error occurred adding dynamic field :(');
        }

        if (count($error_detected) === 0) {
            $success_detected[] = _T('Dynamic field has been successfully stored!');
        }

        //handle redirections
        if (count($error_detected) > 0) {
            //something went wrong :'(
            $this->session->dynamicfieldtype = $df;
            $redirect_url = $this->routeparser->urlFor(
                'addDynamicField',
                ['form_name' => $form_name]
            );
        } elseif (!$df instanceof \Galette\DynamicFields\Separator) {
            $redirect_url = $this->routeparser->urlFor(
                'editDynamicField',
                [
                    'form_name' => $form_name,
                    'id'        => (string)$df->getId()
                ]
            );
        } else {
            $redirect_url = $this->routeparser->urlFor(
                'configureDynamicFields',
                ['form_name' => $form_name]
            );
        }

        return $this->redirect(
            response: $response,
            redirect_url: $redirect_url,
            successes: $success_detected,
            warnings: $warning_detected,
            errors: $error_detected
        );
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * List page
     *
     * @param string|null     $option    One of 'page' or 'order'
     * @param int|string|null $value     Value of the option
     * @param string          $form_name Form name
     */
    public function list(
        Request $request,
        Response $response,
        ?string $option = null,
        int|string|null $value = null,
        string $form_name = 'adh'
    ): Response {
        if (isset($_POST['form_name']) && trim((string)$_POST['form_name']) != '') {
            $form_name = $_POST['form_name'];
        }
        $fields = new DynamicFieldsSet($this->zdb, $this->login);
        $fields_list = $fields->getList($form_name);

        $params = [
            'fields_list'       => $fields_list,
            'form_name'         => $form_name,
            'form_title'        => DynamicField::getFormTitle($form_name),
            'page_title'        => _T("Dynamic fields"),
            'html_editor'       => true,
            'html_editor_active' => $this->preferences->pref_editor_enabled,
            'documentation'     => 'usermanual/configuration.html#dynamic-fields'

        ];

        $tpl = 'pages/configuration_dynamic_fields.html.twig';
        //Render directly template if we called from ajax,
        //render in a full page otherwise
        if (
            ($this->isAjax($request))
            || isset($request->getQueryParams()['ajax'])
            && $request->getQueryParams()['ajax'] == 'true'
        ) {
            $tpl = 'elements/edit_dynamic_fields.html.twig';
        } else {
            $all_forms = DynamicField::getFormsNames();
            $params['all_forms'] = $all_forms;
        }

        // display page
        $this->view->render(
            $response,
            $tpl,
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
     * Get a dynamic file
     *
     * @param string $form_name Form name
     * @param int    $id        Object ID
     * @param int    $fid       Dynamic fields ID
     * @param int    $pos       Dynamic field position
     * @param string $name      File name
     */
    public function getDynamicFile(
        Response $response,
        string $form_name,
        int $id,
        int $fid,
        int $pos,
        string $name
    ): Response {
        $object_class = DynamicFieldsSet::getClasses()[$form_name];
        if ($object_class === \Galette\Entity\Adherent::class) {
            $object = new $object_class($this->zdb);
        } else {
            $object = new $object_class($this->zdb, $this->login);
        }

        $object
            ->disableAllDeps()
            ->enableDep('dynamics')
            ->load($id);
        $fields = $object->getDynamicFields()->getFields();
        $field = $fields[$fid] ?? null;

        $denied = null;
        if (!$object->canShow($this->login)) {
            if (!isset($fields[$fid])) {
                //field does not exist or access is forbidden
                $denied = true;
            } else {
                $denied = false;
            }
        }

        if ($denied === true) {
            $route_name = 'member';
            if ($form_name == 'contrib') {
                $route_name = 'contribution';
            } elseif ($form_name == 'trans') {
                $route_name = 'transaction';
            }

            return $this->redirectWithErrors(
                response: $response,
                errors: [_T("You do not have permission for requested URL.")],
                redirect_url: $this->routeparser->urlFor(
                    $route_name,
                    ['id' => (string)$id]
                )
            );
        }

        $filename = $field->getFileName($id, $pos);

        if ($form_name !== 'member' && !file_exists(GALETTE_FILES_PATH . $filename)) {
            //handle old names for non adh dynamic files
            $test_filename = $field->getFileName($id, $pos, 'member');
            if (file_exists(GALETTE_FILES_PATH . $test_filename)) {
                //rename old file to new name
                rename(GALETTE_FILES_PATH . $test_filename, GALETTE_FILES_PATH . $filename);
            }
        }

        if (file_exists(GALETTE_FILES_PATH . $filename)) {
            $type = File::getMimeType(GALETTE_FILES_PATH . $filename);

            $response = $response->withHeader('Content-Description', 'File Transfer')
                ->withHeader('Content-Type', $type)
                ->withHeader('Content-Disposition', 'attachment;filename="' . $name . '"')
                ->withHeader('Pragma', 'no-cache')
                ->withHeader('Content-Transfer-Encoding', 'binary')
                ->withHeader('Expires', '0')
                ->withHeader('Cache-Control', 'must-revalidate')
                ->withHeader('Pragma', 'public');

            $stream = fopen('php://memory', 'r+');
            fwrite($stream, file_get_contents(GALETTE_FILES_PATH . $filename));
            rewind($stream);

            return $response->withBody(new \Slim\Psr7\Stream($stream));
        } else {
            Analog::log(
                'A request has been made to get a dynamic file named `'
                . $filename . '` that does not exists.',
                Analog::WARNING
            );

            $route_name = 'member';
            if ($form_name == 'contrib') {
                $route_name = 'contribution';
            } elseif ($form_name == 'trans') {
                $route_name = 'transaction';
            }

            return $this->redirectWithErrors(
                response: $response,
                errors: [_T("The file does not exists or cannot be read :(")],
                redirect_url: $this->routeparser->urlFor($route_name, ['id' => (string)$id])
            );
        }
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param int     $id        Dynamic field id
     * @param ?string $form_name Form name
     */
    public function edit(Request $request, Response $response, int $id, ?string $form_name = null): Response
    {
        if ($this->session->dynamicfieldtype) {
            $df = $this->session->dynamicfieldtype;
            $this->session->dynamicfieldtype = null;
        } else {
            $df = DynamicField::loadFieldType($this->zdb, $id);
            if ($df === false) {
                return $this->redirectWithErrors(
                    response: $response,
                    errors: [_T("Unable to retrieve field information.")],
                    redirect_url: $this->routeparser->urlFor('configureDynamicFields')
                );
            }
        }

        $params = [
            'page_title'    => _T("Edit field"),
            'action'        => 'edit',
            'form_name'     => $form_name,
            'perm_names'    => DynamicField::getPermissionsList(),
            'mode'          => (($this->isAjax($request)) ? 'ajax' : ''),
            'df'            => $df,
            'html_editor'   => true,
            'html_editor_active' => $this->preferences->pref_editor_enabled
        ];

        // display page
        $this->view->render(
            $response,
            'pages/configuration_dynamic_field_form.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Edit action
     *
     * @param int     $id        Dynamic field id
     * @param ?string $form_name Form name
     */
    public function doEdit(Request $request, Response $response, int $id, ?string $form_name = null): Response
    {
        $post = $request->getParsedBody();
        $post['form_name'] = $form_name;

        if (isset($post['cancel'])) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->cancelUri($this->getArgs($request)));
        }

        $success_detected = [];

        $field_id = $id;
        $df = DynamicField::loadFieldType($this->zdb, $field_id);

        try {
            $df->store($post);
            $error_detected = $df->getErrors();
        } catch (Throwable $e) {
            $msg = 'An error occurred storing dynamic field ' . $df->getId() . '.';
            Analog::log(
                $msg . ' | '
                . $e->getMessage(),
                Analog::ERROR
            );
            if (Galette::isDebugEnabled()) {
                throw $e;
            }

            $error_detected = $df->getErrors();
            if (count($error_detected) == 0) {
                $error_detected[] = _T('An error occurred editing dynamic field :(');
            }
        }

        //flash messages
        if (count($error_detected) === 0) {
            $success_detected[] = _T('Dynamic field has been successfully stored!');
        }

        $warning_detected = $df->getWarnings();

        //handle redirections
        if (count($error_detected) > 0) {
            //something went wrong :'(
            $this->session->dynamicfieldtype = $df;
            $redirect_url = $this->routeparser->urlFor(
                'editDynamicField',
                [
                    'form_name' => $form_name,
                    'id'        => (string)$id
                ]
            );
        } else {
            $redirect_url = $this->routeparser->urlFor(
                'configureDynamicFields',
                ['form_name' => $form_name]
            );
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
        return $this->routeparser->urlFor('configureDynamicFields', ['form_name' => $args['form_name']]);
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'doRemoveDynamicField',
            ['id' => $args['id'], 'form_name' => $args['form_name']]
        );
    }

    /**
     * Get confirmation removal page title
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function confirmRemoveTitle(array $args): string
    {
        $field = DynamicField::loadFieldType($this->zdb, (int)$args['id']);
        if ($field === false) {
            return _T("Requested field does not exists!");
        }

        return sprintf(
            _T('Remove dynamic field %1$s'),
            $field->getName()
        );
    }

    /**
     * Remove object
     *
     * @param array<string,mixed> $args Route arguments
     * @param array<string,mixed> $post POST values
     */
    protected function doDelete(array $args, array $post): bool
    {
        $field_id = (int)$post['id'];
        $field = DynamicField::loadFieldType($this->zdb, $field_id);
        if ($field === false) {
            $this->flash->addMessage(
                'error_detected',
                _T("Requested field does not exists!")
            );
            return false;
        }
        return $field->remove();
    }

    // /CRUD - Delete
    // /CRUD

    /**
     * Move field
     *
     * @param int    $id        Field id
     * @param string $form_name Form name
     * @param string $direction One of DynamicField::MOVE_*
     */
    public function move(
        Response $response,
        int $id,
        string $form_name,
        string $direction
    ): Response {
        $field = DynamicField::loadFieldType($this->zdb, $id);
        $redirect_url = $this->routeparser->urlFor('configureDynamicFields', ['form_name' => $form_name]);
        $error_detected = [];
        $success_detected = [];

        if ($field !== false && $field->move($direction)) {
            $success_detected[] = _T("Field has been successfully moved");
        } else {
            $error_detected[] = _T("An error occurred moving field :(");
        }

        return $this->redirect(
            response: $response,
            redirect_url: $redirect_url,
            successes: $success_detected,
            errors: $error_detected
        );
    }
}
