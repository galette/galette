<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

namespace Galette\Controllers\Crud\test\units;

use Galette\GaletteSeleniumCase;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;

/**
 * DynamicFields controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DynamicFieldsController extends GaletteSeleniumCase
{
    protected int $seed = 20240529064653;

    /**
     * Cleanup after tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->zdb = new \Galette\Core\Db();

        $delete = $this->zdb->delete(\Galette\DynamicFields\DynamicField::TABLE);
        $this->zdb->execute($delete);
        //cleanup dynamic translations
        $delete = $this->zdb->delete(\Galette\Core\L10n::TABLE);
        $this->zdb->execute($delete);

        $tables = $this->zdb->getTables();
        foreach ($tables as $table) {
            if (str_starts_with($table, 'galette_field_contents_')) {
                $this->zdb->db->query(
                    'DROP TABLE ' . $table,
                    \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
                );
            }
        }
    }

    /**
     * Cleanup after class
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        $self = new self(__METHOD__);
        $self->tearDown();
    }

    /**
     * Test adding dynamic field from controller
     *
     * @return void
     */
    public function testAddDynamicField(): void
    {
        $ufactory = new \Slim\Psr7\Factory\UriFactory();
        $sfactory = new \Slim\Psr7\Factory\StreamFactory();

        $request = new Request(
            'POST',
            $ufactory->createUri('http://localhost/fields/dynamic/add/adh'),
            new Headers(['Content-Type' => ['application/json']]),
            [],
            [],
            $sfactory->createStream()
        );
        $request = $request->withParsedBody(
            [
                'store' => true,
                'field_name' => 'Dynamic test field',
                'field_perm' => (string)\Galette\Entity\FieldsConfig::STAFF,
                'field_type' => (string)\Galette\DynamicFields\Line::LINE,
                'field_required' => '0',
                'form_name' => 'adh'
            ]
        );

        $response = new \Slim\Psr7\Response();
        $controller = new \Galette\Controllers\Crud\DynamicFieldsController($this->container);
        $this->container->injectOn($controller);

        $test_response = $controller->doAdd($request, $response, 'adh');
        $this->assertSame(
            [
                'success_detected' => [
                    'Dynamic field has been successfully stored!'
                ]
            ],
            $this->flash_data['slimFlash']
        );
    }

    /**
     * Test adding dynamic field from controller with an error
     *
     * @return void
     */
    public function testAddErrorDynamicField(): void
    {
        $ufactory = new \Slim\Psr7\Factory\UriFactory();
        $sfactory = new \Slim\Psr7\Factory\StreamFactory();

        $request = new Request(
            'POST',
            $ufactory->createUri('http://localhost/fields/dynamic/add/adh'),
            new Headers(['Content-Type' => ['application/json']]),
            [],
            [],
            $sfactory->createStream()
        );
        $request = $request->withParsedBody(
            [
                'store' => true,
                //'field_name' => 'Dynamic test field', //explicitly omitted; this one is required.
                'field_perm' => (string)\Galette\Entity\FieldsConfig::STAFF,
                'field_type' => (string)\Galette\DynamicFields\Line::LINE,
                'field_required' => '0',
                'form_name' => 'adh'
            ]
        );

        $response = new \Slim\Psr7\Response();
        $controller = new \Galette\Controllers\Crud\DynamicFieldsController($this->container);
        $this->container->injectOn($controller);

        $test_response = $controller->doAdd($request, $response, 'adh');
        $this->assertSame(
            [
                'error_detected' => [
                    'Missing required field name!'
                ]
            ],
            $this->flash_data['slimFlash']
        );
    }

    /**
     * Test updating dynamic field from controller
     *
     * @return void
     */
    public function testUpdateDynamicField(): void
    {
        //create field
        $field_data = [
            'field_name' => 'Dynamic test field',
            'field_perm' => (string)\Galette\Entity\FieldsConfig::STAFF,
            'field_type' => (string)\Galette\DynamicFields\Line::LINE,
            'field_required' => '0',
            'form_name' => 'adh'
        ];

        $df = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, (int)$field_data['field_type']);
        $stored = $df->store($field_data);
        $this->assertTrue(
            $stored,
            implode(
                ' ',
                $df->getErrors() + $df->getWarnings()
            )
        );
        $field_id = (string)$df->getId();

        $ufactory = new \Slim\Psr7\Factory\UriFactory();
        $sfactory = new \Slim\Psr7\Factory\StreamFactory();

        $request = new Request(
            'POST',
            $ufactory->createUri('http://localhost/fields/dynamic/add/adh'),
            new Headers(['Content-Type' => ['application/json']]),
            [],
            [],
            $sfactory->createStream()
        );
        $request = $request->withParsedBody(
            [
                'store' => true,
                'field_id' => $field_id,
                'field_name' => 'Dynamic test field (edited)',
                'field_perm' => (string)\Galette\Entity\FieldsConfig::STAFF,
                'field_type' => (string)\Galette\DynamicFields\Line::LINE,
                'field_required' => '0',
                'form_name' => 'adh'
            ]
        );

        $response = new \Slim\Psr7\Response();
        $controller = new \Galette\Controllers\Crud\DynamicFieldsController($this->container);
        $this->container->injectOn($controller);

        $test_response = $controller->doAdd($request, $response, 'adh');
        $this->assertSame(
            [
                'success_detected' => [
                    'Dynamic field has been successfully stored!'
                ]
            ],
            $this->flash_data['slimFlash']
        );
    }
}
