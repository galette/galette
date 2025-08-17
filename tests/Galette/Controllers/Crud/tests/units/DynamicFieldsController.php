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

namespace Galette\Controllers\Crud\test\units;

use Galette\GaletteRoutingTestCase;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;

/**
 * DynamicFields controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DynamicFieldsController extends GaletteRoutingTestCase
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
     * Test add page from controller
     *
     * @return void
     */
    public function testAddPageDynamicField(): void
    {
        $route_name = 'addDynamicField';
        $route_arguments = [
            'form_name' => 'adh'
        ];

        //login is required to access this page
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test with logged-in user
        $this->logSuperAdmin();
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->login->logout();
        $this->expectOK($test_response);
    }

    /**
     * Test edit page from controller
     *
     * @return void
     */
    public function testEditPageDynamicField(): void
    {
        $field_id = $this->createDynamicField();
        $route_name = 'editDynamicField';
        $route_arguments = [
            'id' => $field_id,
            'form_name' => 'adh'
        ];

        //login is required to access this page
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test with logged-in user
        $this->logSuperAdmin();
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->login->logout();
        $this->expectOK($test_response);

        //test non existing field
        $this->logSuperAdmin();
        $route_arguments['id'] = ++$field_id;
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->login->logout();
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('configureDynamicFields')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Unable to retrieve field information.']], $this->flash_data['slimFlash']);
        $this->flash_data = [];
    }

    /**
     * Test adding dynamic field from controller
     *
     * @return void
     */
    public function testAddDynamicField(): void
    {
        $route_name = 'doAddDynamicField';
        $route_arguments = [
            'form_name' => 'adh'
        ];
        $request = $this->createRequest($route_name, $route_arguments, 'POST', 'application/json');
        $cfield_data = [
            'store' => true,
            'field_name' => 'Dynamic test field',
            'field_perm' => (string)\Galette\Entity\FieldsConfig::STAFF,
            'field_type' => (string)\Galette\DynamicFields\Line::LINE,
            'field_required' => '0',
            'form_name' => 'adh'
        ];
        $request = $request->withParsedBody($cfield_data);

        //login is required to access this page
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test with logged-in user
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->login->logout();

        //get new field id
        $select = $this->zdb->select(\Galette\DynamicFields\DynamicField::TABLE)
            ->where(['field_name' => $cfield_data['field_name']]);
        $result = $this->zdb->execute($select);
        $id = $result->current()->field_id;

        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('editDynamicField', ['id' => $id, 'form_name' => 'adh'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
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
     * Test cencelled adding dynamic field from controller
     *
     * @return void
     */
    public function testAddCancelynamicField(): void
    {
        $route_name = 'doAddDynamicField';
        $route_arguments = [
            'form_name' => 'adh'
        ];
        $request = $this->createRequest($route_name, $route_arguments, 'POST', 'application/json');
        $cfield_data = [
            'store' => true,
            'field_name' => 'Dynamic test field',
            'field_perm' => (string)\Galette\Entity\FieldsConfig::STAFF,
            'field_type' => (string)\Galette\DynamicFields\Line::LINE,
            'field_required' => '0',
            'form_name' => 'adh',
            'cancel' => true
        ];
        $request = $request->withParsedBody($cfield_data);

        //test with logged-in user
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->login->logout();

        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('configureDynamicFields', ['form_name' => 'adh'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data['slimFlash']);
    }

    /**
     * Test adding dynamic field from controller with an error
     *
     * @return void
     */
    public function testAddErrorDynamicField(): void
    {
        $request = $this->createRequest(
            'addDynamicField',
            ['form_name' => 'adh'],
            'POST',
            'application/json'
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

        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->login->logout();

        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('addDynamicField', ['form_name' => 'adh'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
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
        $field_id = $this->createDynamicField();

        $request = $this->createRequest(
            'editDynamicField',
            ['id' => $field_id, 'form_name' => 'adh'],
            'POST',
            'application/json'
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

        //login is required to access this page
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test with logged-in user
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->login->logout();

        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('configureDynamicFields', ['form_name' => 'adh'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
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
     * Test canceled updating dynamic field from controller
     *
     * @return void
     */
    public function testUpdateCancelDynamicField(): void
    {
        $field_id = $this->createDynamicField();

        $request = $this->createRequest(
            'editDynamicField',
            ['id' => $field_id, 'form_name' => 'adh'],
            'POST',
            'application/json'
        );
        $request = $request->withParsedBody(
            [
                'store' => true,
                'field_id' => $field_id,
                'field_name' => 'Dynamic test field (edited)',
                'field_perm' => (string)\Galette\Entity\FieldsConfig::STAFF,
                'field_type' => (string)\Galette\DynamicFields\Line::LINE,
                'field_required' => '0',
                'form_name' => 'adh',
                'cancel' => true
            ]
        );

        //test with logged-in user
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->login->logout();

        //we should just be redirected to dynamic fields list
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('configureDynamicFields', ['form_name' => 'adh'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data['slimFlash']);
    }

    /**
     * Test error updating dynamic field from controller
     *
     * @return void
     */
    public function testUpdateErrorDynamicField(): void
    {
        $field_id = (string)$this->createDynamicField();

        $request = $this->createRequest(
            'editDynamicField',
            ['id' => $field_id, 'form_name' => 'adh'],
            'POST',
            'application/json'
        );
        $request = $request->withParsedBody(
            [
                'store' => true,
                'field_id' => $field_id,
                //'field_name' => 'Dynamic test field (edited)', //explicitly omitted; this one is required.
                'field_perm' => (string)\Galette\Entity\FieldsConfig::STAFF,
                'field_type' => (string)\Galette\DynamicFields\Line::LINE,
                'field_required' => '0',
                'form_name' => 'adh'
            ]
        );

        //test with logged-in user
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->login->logout();

        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('editDynamicField', ['id' => $field_id, 'form_name' => 'adh'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
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
     * Test remove page dynamic field from controller
     *
     * @return void
     */
    public function testRemovePageDynamicField(): void
    {
        $field_id = $this->createDynamicField();
        $route_name = 'removeDynamicField';
        $route_arguments = [
            'id' => $field_id,
            'form_name' => 'adh'
        ];

        //login is required to access this page
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test with logged-in user
        $this->logSuperAdmin();
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->login->logout();
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Remove dynamic field Dynamic test field', $body);
        $this->expectOK($test_response);

        //test with a field that does not exist
        $route_arguments = [
            'id' => ++$field_id,
            'form_name' => 'adh'
        ];
        $this->logSuperAdmin();
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->login->logout();
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Requested field does not exists!', $body);
        $this->expectOK($test_response);
    }

    /**
     * Test dynamic field removal from controller
     *
     * @return void
     */
    public function testRemoveDynamicField(): void
    {
        $field_id = $this->createDynamicField();
        $route_name = 'doRemoveDynamicField';
        $route_arguments = [
            'id' => $field_id,
            'form_name' => 'adh'
        ];

        //login is required to access this page
        $request = $this->createRequest($route_name, $route_arguments, 'POST');
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test with logged-in user
        $this->logSuperAdmin();
        $request = $this->createRequest($route_name, $route_arguments, 'POST');
        $test_response = $this->app->handle($request);
        $this->login->logout();
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('configureDynamicFields', ['form_name' => 'adh'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Removal has not been confirmed!']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //make sure field still exists
        $select = $this->zdb->select(\Galette\DynamicFields\DynamicField::TABLE)
            ->where(['field_id' => $field_id]);
        $result = $this->zdb->execute($select);
        $this->assertCount(1, $result);

        $this->logSuperAdmin();
        $request = $request->withParsedBody(['confirm' => true]);
        $test_response = $this->app->handle($request);
        $this->login->logout();
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('configureDynamicFields', ['form_name' => 'adh'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::ERROR, 'An error occurred on delete | Undefined array key "id"');
        $this->assertSame(['error_detected' => ['An error occurred trying to delete :(']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //make sure field still exists
        $select = $this->zdb->select(\Galette\DynamicFields\DynamicField::TABLE)
            ->where(['field_id' => $field_id]);
        $result = $this->zdb->execute($select);
        $this->assertCount(1, $result);

        $this->logSuperAdmin();
        $request = $request->withParsedBody(['id' => $field_id, 'confirm' => true]);
        $test_response = $this->app->handle($request);
        $this->login->logout();
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('configureDynamicFields', ['form_name' => 'adh'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Successfully deleted!']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //make sure field no longer exists
        $select = $this->zdb->select(\Galette\DynamicFields\DynamicField::TABLE)
            ->where(['field_id' => $field_id]);
        $result = $this->zdb->execute($select);
        $this->assertCount(0, $result);

        //test with a field that does not exist
        $this->logSuperAdmin();
        $request = $request->withParsedBody(['id' => $field_id, 'confirm' => true]);
        $test_response = $this->app->handle($request);
        $this->login->logout();
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('configureDynamicFields', ['form_name' => 'adh'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Requested field does not exists!']], $this->flash_data['slimFlash']);
        $this->flash_data = [];
    }

    /**
     * Test dynamic field removal from controller
     *
     * @return void
     */
    public function testMoveDynamicField(): void
    {
        $field_id_1 = $this->createDynamicField();
        $field_id_2 = $this->createDynamicField('I like to move it :D');
        $route_name = 'moveDynamicField';
        $route_arguments = [
            'id' => $field_id_2,
            'form_name' => 'adh',
            'direction' => \Galette\DynamicFields\DynamicField::MOVE_UP
        ];

        //check positions
        $select = $this->zdb->select(\Galette\DynamicFields\DynamicField::TABLE)
            ->where(['field_id' => $field_id_1]);
        $rank_1 = $this->zdb->execute($select)->current()->field_index;
        $select = $this->zdb->select(\Galette\DynamicFields\DynamicField::TABLE)
            ->where(['field_id' => $field_id_2]);
        $rank_2 = $this->zdb->execute($select)->current()->field_index;
        $this->assertGreaterThan($rank_1, $rank_2);

        //login is required to access this page
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test with logged-in user
        $this->logSuperAdmin();
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->login->logout();
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('configureDynamicFields', ['form_name' => 'adh'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Field has been successfully moved']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //check new positions
        $select = $this->zdb->select(\Galette\DynamicFields\DynamicField::TABLE)
            ->where(['field_id' => $field_id_1]);
        $new_rank_1 = $this->zdb->execute($select)->current()->field_index;
        $select = $this->zdb->select(\Galette\DynamicFields\DynamicField::TABLE)
            ->where(['field_id' => $field_id_2]);
        $new_rank_2 = $this->zdb->execute($select)->current()->field_index;
        $this->assertNotEquals($new_rank_1, $rank_1);
        $this->assertNotEquals($new_rank_2, $rank_2);
        $this->assertGreaterThan($new_rank_2, $new_rank_1);

        //field that does not exist
        $this->logSuperAdmin();
        $route_arguments['id'] = ++$field_id_2;
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->login->logout();
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('configureDynamicFields', ['form_name' => 'adh'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['An error occurred moving field :(']], $this->flash_data['slimFlash']);
        $this->flash_data = [];
    }

    /**
     * Test dynamic fields list
     *
     * @return void
     */
    public function testList(): void
    {
        $field_id_1 = $this->createDynamicField();
        $field_id_2 = $this->createDynamicField('Second chance');
        $field_id_3 = $this->createDynamicField('Yet another field');

        $request = $this->createRequest(
            'configureDynamicFields',
            ['form_name' => 'adh']
        );

        //login is required to access this page
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test with logged-in user
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->login->logout();

        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Dynamic test field', $body);
        $this->assertStringContainsString(sprintf('href="/fields/dynamic/edit/adh/%1$s"', $field_id_1), $body);
        $this->assertStringContainsString('Second chance', $body);
        $this->assertStringContainsString(sprintf('href="/fields/dynamic/edit/adh/%1$s"', $field_id_2), $body);
        $this->assertStringContainsString('Yet another field', $body);
        $this->assertStringContainsString(sprintf('href="/fields/dynamic/edit/adh/%1$s"', $field_id_3), $body);
    }

    /**
     * Create a dynamic field for tests
     *
     * @param string $name Name of the field to create
     *
     * @return int The created field id
     */
    private function createDynamicField(string $name = 'Dynamic test field'): int
    {
        //create field
        $field_data = [
            'field_name' => $name,
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
        return $df->getId();
    }
}
