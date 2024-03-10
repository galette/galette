<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette dynamic fields controller
 *
 * PHP version 5
 *
 * Copyright © 2020-2023 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Controllers
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-02
 */

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

/**
 * Galette dynamic fields controller
 *
 * @category  Controllers
 * @name      DynamicFieldsController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-02
 */

class DynamicFieldsController extends CrudController
{
    // CRUD - Create

    /**
     * Add page
     *
     * @param Request  $request   PSR Request
     * @param Response $response  PSR Response
     * @param string   $form_name Form name
     *
     * @return Response
     */
    public function add(Request $request, Response $response, string $form_name = null): Response
    {
        $params = [
            'page_title'        => _T("Add field"),
            'form_name'         => $form_name,
            'action'            => 'add',
            'perm_names'        => DynamicField::getPermsNames(),
            'mode'              => (($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') ? 'ajax' : ''),
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
     * @param Request  $request   PSR Request
     * @param Response $response  PSR Response
     * @param string   $form_name Form name
     *
     * @return Response
     */
    public function doAdd(Request $request, Response $response, string $form_name = null): Response
    {
        $post = $request->getParsedBody();
        $post['form_name'] = $form_name;

        $error_detected = [];
        $warning_detected = [];

        if (isset($post['cancel'])) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->cancelUri($this->getArgs($request)));
        }

        $df = DynamicField::getFieldType($this->zdb, $post['field_type']);

        try {
            $df->store($post);
            $error_detected = $df->getErrors();
            $warning_detected = $df->getWarnings();
        } catch (Throwable $e) {
            $msg = 'An error occurred adding new dynamic field.';
            Analog::log(
                $msg . ' | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            if (Galette::isDebugEnabled()) {
                throw $e;
            }
            $error_detected[] = _T('An error occurred adding dynamic field :(');
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
                _T('Dynamic field has been successfully stored!')
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
            $this->session->dynamicfieldtype = $df;
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor(
                        'addDynamicField',
                        ['form_name' => $form_name]
                    )
                );
        } else {
            if (!$df instanceof \Galette\DynamicFields\Separator) {
                return $response
                    ->withStatus(301)
                    ->withHeader(
                        'Location',
                        $this->routeparser->urlFor(
                            'editDynamicField',
                            [
                                'form_name' => $form_name,
                                'id'        => $df->getId()
                            ]
                        )
                    );
            }

            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor(
                        'configureDynamicFields',
                        ['form_name' => $form_name]
                    )
                );
        }
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * List page
     *
     * @param Request        $request   PSR Request
     * @param Response       $response  PSR Response
     * @param string         $option    One of 'page' or 'order'
     * @param string|integer $value     Value of the option
     * @param string         $form_name Form name
     *
     * @return Response
     */
    public function list(
        Request $request,
        Response $response,
        $option = null,
        $value = null,
        $form_name = 'adh'
    ): Response {
        if (isset($_POST['form_name']) && trim($_POST['form_name']) != '') {
            $form_name = $_POST['form_name'];
        }
        $fields = new DynamicFieldsSet($this->zdb, $this->login);
        $fields_list = $fields->getList($form_name);

        $params = [
            'fields_list'       => $fields_list,
            'form_name'         => $form_name,
            'form_title'        => DynamicField::getFormTitle($form_name),
            'page_title'        => _T("Dynamic fields configuration"),
            'html_editor'       => true,
            'html_editor_active' => $this->preferences->pref_editor_enabled

        ];

        $tpl = 'pages/configuration_dynamic_fields.html.twig';
        //Render directly template if we called from ajax,
        //render in a full page otherwise
        if (
            ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest')
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
     * Get a dynamic file
     *
     * @param Request  $request   PSR Request
     * @param Response $response  PSR Response
     * @param string   $form_name Form name
     * @param integer  $id        Object ID
     * @param integer  $fid       Dynamic fields ID
     * @param integer  $pos       Dynamic field position
     * @param string   $name      File name
     *
     * @return Response
     */
    public function getDynamicFile(
        Request $request,
        Response $response,
        string $form_name,
        int $id,
        int $fid,
        int $pos,
        string $name
    ): Response {
        $object_class = DynamicFieldsSet::getClasses()[$form_name];
        if ($object_class === 'Galette\Entity\Adherent') {
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
            $this->flash->addMessage(
                'error_detected',
                _T("You do not have permission for requested URL.")
            );

            $route_name = 'member';
            if ($form_name == 'contrib') {
                $route_name = 'contribution';
            } elseif ($form_name == 'trans') {
                $route_name = 'transaction';
            }
            return $response
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor(
                        $route_name,
                        ['id' => $id]
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
                'A request has been made to get a dynamic file named `' .
                $filename . '` that does not exists.',
                Analog::WARNING
            );

            $this->flash->addMessage(
                'error_detected',
                _T("The file does not exists or cannot be read :(")
            );

            $route_name = 'member';
            if ($form_name == 'contrib') {
                $route_name = 'contribution';
            } elseif ($form_name == 'trans') {
                $route_name = 'transaction';
            }

            return $response
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor($route_name, ['id' => (string)$id])
                );
        }
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param Request  $request   PSR Request
     * @param Response $response  PSR Response
     * @param integer  $id        Dynamic field id
     * @param string   $form_name Form name
     *
     * @return Response
     */
    public function edit(Request $request, Response $response, int $id, $form_name = null): Response
    {
        $df = null;
        if ($this->session->dynamicfieldtype) {
            $df = $this->session->dynamicfieldtype;
            $this->session->dynamicfieldtype = null;
        } else {
            $df = DynamicField::loadFieldType($this->zdb, $id);
            if ($df === false) {
                $this->flash->addMessage(
                    'error_detected',
                    _T("Unable to retrieve field information.")
                );
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->routeparser->urlFor('configureDynamicFields'));
            }
        }

        $params = [
            'page_title'    => _T("Edit field"),
            'action'        => 'edit',
            'form_name'     => $form_name,
            'perm_names'    => DynamicField::getPermsNames(),
            'mode'          => (($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') ? 'ajax' : ''),
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
     * @param Request  $request   PSR Request
     * @param Response $response  PSR Response
     * @param integer  $id        Dynamic field id
     * @param string   $form_name Form name
     *
     * @return Response
     */
    public function doEdit(Request $request, Response $response, int $id = null, string $form_name = null): Response
    {
        $post = $request->getParsedBody();
        $post['form_name'] = $form_name;

        if (isset($post['cancel'])) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->cancelUri($this->getArgs($request)));
        }

        $error_detected = [];
        $warning_detected = [];

        $field_id = $id;
        $df = DynamicField::loadFieldType($this->zdb, $field_id);

        try {
            $df->store($post);
            $error_detected = $df->getErrors();
            $warning_detected = $df->getWarnings();
        } catch (Throwable $e) {
            $msg = 'An error occurred storing dynamic field ' . $df->getId() . '.';
            Analog::log(
                $msg . ' | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            if (Galette::isDebugEnabled()) {
                throw $e;
            }
            $error_detected[] = _T('An error occurred editing dynamic field :(');
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
                _T('Dynamic field has been successfully stored!')
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
            $this->session->dynamicfieldtype = $df;
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor(
                        'editDynamicField',
                        [
                            'form_name' => $form_name,
                            'id'        => $id
                        ]
                    )
                );
        } else {
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor(
                        'configureDynamicFields',
                        ['form_name' => $form_name]
                    )
                );
        }
    }

    // /CRUD - Update
    // CRUD - Delete

    /**
     * Get redirection URI
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function redirectUri(array $args)
    {
        return $this->routeparser->urlFor('configureDynamicFields', ['form_name' => $args['form_name']]);
    }

    /**
     * Get form URI
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function formUri(array $args)
    {
        return $this->routeparser->urlFor(
            'doRemoveDynamicField',
            ['id' => $args['id'], 'form_name' => $args['form_name']]
        );
    }

    /**
     * Get confirmation removal page title
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function confirmRemoveTitle(array $args)
    {
        $field = DynamicField::loadFieldType($this->zdb, (int)$args['id']);
        if ($field === false) {
            $this->flash->addMessage(
                'error_detected',
                _T("Requested field does not exists!")
            );
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
     * @param array $args Route arguments
     * @param array $post POST values
     *
     * @return boolean
     */
    protected function doDelete(array $args, array $post)
    {
        $field_id = (int)$post['id'];
        $field = DynamicField::loadFieldType($this->zdb, $field_id);
        return $field->remove();
    }

    // /CRUD - Delete
    // /CRUD

    /**
     * Move field
     *
     * @param Request  $request   PSR Request
     * @param Response $response  PSR Response
     * @param integer  $id        Field id
     * @param string   $form_name Form name
     * @param string   $direction One of DynamicField::MOVE_*
     *
     * @return Response
     */
    public function move(
        Request $request,
        Response $response,
        int $id,
        string $form_name,
        string $direction
    ): Response {
        $field = DynamicField::loadFieldType($this->zdb, $id);
        if ($field->move($direction)) {
            $this->flash->addMessage(
                'success_detected',
                _T("Field has been successfully moved")
            );
        } else {
            $this->flash->addMessage(
                'error_detected',
                _T("An error occurred moving field :(")
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('configureDynamicFields', ['form_name' => $form_name]));
    }
}
