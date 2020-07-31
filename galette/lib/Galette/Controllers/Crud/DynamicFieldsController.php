<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette dynamic fields controller
 *
 * PHP version 5
 *
 * Copyright © 2020 The Galette Team
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
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-02
 */

namespace Galette\Controllers\Crud;

use Galette\Controllers\CrudController;
use Slim\Http\Request;
use Slim\Http\Response;
use Galette\DynamicFields\DynamicField;
use Analog\Analog;

/**
 * Galette dynamic fields controller
 *
 * @category  Controllers
 * @name      DynamicFieldsController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
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
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function add(Request $request, Response $response, array $args = []): Response
    {
        $form_name = $args['form'];

        $df = null;
        if ($this->session->dynamicfieldtype) {
            $df = $this->session->dynamicfieldtype;
            $this->session->dynamicfieldtype = null;
        }

        $params = [
            'page_title'        => _T("Add field"),
            'form_name'         => $form_name,
            'action'            => 'add',
            'perm_names'        => DynamicField::getPermsNames(),
            'mode'              => ($request->isXhr() ? 'ajax' : ''),
            'field_type_names'  => DynamicField::getFieldsTypesNames()
        ];

        // display page
        $this->view->render(
            $response,
            'editer_champ.tpl',
            $params
        );
        return $response;
    }

    /**
     * Add action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function doAdd(Request $request, Response $response, array $args = []): Response
    {
        $post = $request->getParsedBody();
        $post['form'] = $args['form'];

        $error_detected = [];
        $warning_detected = [];

        $df = DynamicField::getFieldType($this->zdb, $post['field_type']);

        try {
            $df->store($post);
            $error_detected = $df->getErrors();
            $warning_detected = $df->getWarnings();
        } catch (\Exception $e) {
            $msg = 'An error occurred adding new dynamic field.';
            Analog::log(
                $msg . ' | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            if (GALETTE_MODE == 'DEV') {
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
                    $this->router->pathFor(
                        'addDynamicField',
                        $args
                    )
                );
        } else {
            if (!$df instanceof \Galette\DynamicFields\Separator) {
                return $response
                    ->withStatus(301)
                    ->withHeader(
                        'Location',
                        $this->router->pathFor(
                            'editDynamicField',
                            [
                                'form'      => $args['form'],
                                'id'        => $df->getId()
                            ]
                        )
                    );
            }

            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->router->pathFor(
                        'configureDynamicFields',
                        ['form' => $args['form']]
                    )
                );
        }
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * List page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function list(Request $request, Response $response, array $args = []): Response
    {
        $form_name = $args['form'] ?? 'adh';
        if (isset($_POST['form']) && trim($_POST['form']) != '') {
            $form_name = $_POST['form'];
        }
        $fields = new \Galette\Repository\DynamicFieldsSet($this->zdb, $this->login);
        $fields_list = $fields->getList($form_name, $this->login);

        $field_type_names = DynamicField::getFieldsTypesNames();

        $params = [
            'fields_list'       => $fields_list,
            'form_name'         => $form_name,
            'form_title'        => DynamicField::getFormTitle($form_name),
            'page_title'        => _T("Dynamic fields configuration")
        ];

        $tpl = 'configurer_fiches.tpl';
        //Render directly template if we called from ajax,
        //render in a full page otherwise
        if (
            $request->isXhr()
            || isset($request->getQueryParams()['ajax'])
            && $request->getQueryParams()['ajax'] == 'true'
        ) {
            $tpl = 'configurer_fiche_content.tpl';
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
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function edit(Request $request, Response $response, array $args = []): Response
    {
        $id_dynf = (int)$args['id'];
        $form_name = $args['form'];

        $df = null;
        if ($this->session->dynamicfieldtype) {
            $df = $this->session->dynamicfieldtype;
            $this->session->dynamicfieldtype = null;
        } else {
            $df = DynamicField::loadFieldType($this->zdb, $id_dynf);
            if ($df === false) {
                $this->flash->addMessage(
                    'error_detected',
                    _T("Unable to retrieve field information.")
                );
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('configureDynamicFields'));
            }
        }

        $params = [
            'page_title'    => _T("Edit field"),
            'action'        => 'edit',
            'form_name'     => $form_name,
            'perm_names'    => DynamicField::getPermsNames(),
            'mode'          => ($request->isXhr() ? 'ajax' : ''),
            'df'            => $df
        ];

        // display page
        $this->view->render(
            $response,
            'editer_champ.tpl',
            $params
        );
        return $response;
    }

    /**
     * Edit action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function doEdit(Request $request, Response $response, array $args = []): Response
    {
        $post = $request->getParsedBody();
        $post['form'] = $args['form'];

        $error_detected = [];
        $warning_detected = [];

        $field_id = (int)$args['id'];
        $df = DynamicField::loadFieldType($this->zdb, $field_id);

        try {
            $df->store($post);
            $error_detected = $df->getErrors();
            $warning_detected = $df->getWarnings();
        } catch (\Exception $e) {
            $msg = 'An error occurred storing dynamic field ' . $df->getId() . '.';
            Analog::log(
                $msg . ' | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            if (GALETTE_MODE == 'DEV') {
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
                    $this->router->pathFor(
                        'editDynamicField',
                        $args
                    )
                );
        } else {
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->router->pathFor(
                        'configureDynamicFields',
                        ['form' => $args['form']]
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
    public function redirectUri(array $args = [])
    {
        return $this->router->pathFor('configureDynamicFields');
    }

    /**
     * Get form URI
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function formUri(array $args = [])
    {
        return $this->router->pathFor(
            'doRemoveDynamicField',
            ['id' => $args['id'], 'form' => $args['form']]
        );
    }

    /**
     * Get confirmation removal page title
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function confirmRemoveTitle(array $args = [])
    {
        $field = DynamicField::loadFieldType($this->zdb, (int)$args['id']);
        if ($field === false) {
            $this->flash->addMessage(
                'error_detected',
                _T("Requested field does not exists!")
            );
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('configureDynamicFields', ['form' => $args['form']]));
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
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function move(Request $request, Response $response, array $args = []): Response
    {
        $field_id = (int)$args['id'];
        $form_name = $args['form'];

        $field = DynamicField::loadFieldType($this->zdb, $field_id);
        if ($field->move($args['direction'])) {
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
            ->withHeader('Location', $this->router->pathFor('configureDynamicFields', ['form' => $form_name]));
    }
}
