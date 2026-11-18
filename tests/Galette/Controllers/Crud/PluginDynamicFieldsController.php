<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers\Crud;

use Galette\Tests\GaletteRoutingTestCase;

use function Safe\copy;
use function Safe\filesize;
use function Safe\unlink;

/**
 * Dynamic fields of a form declared by a plugin
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PluginDynamicFieldsController extends GaletteRoutingTestCase
{
    protected int $seed = 20261116091512;
    protected bool $load_plugins = true;

    /**
     * Test the form of the test plugin is configured as the core ones
     */
    public function testConfigure(): void
    {
        $this->assertSame(\GaletteTest1Plugin\Thing::class, \Galette\Repository\DynamicFieldsSet::getClasses()['plugin1']);
        $this->assertSame('Plugin one things', \Galette\DynamicFields\DynamicField::getFormsNames()['plugin1']);

        $this->logSuperAdmin();
        $request = $this->createRequest('configureDynamicFields', ['form_name' => 'plugin1']);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Plugin one things', $body);
        $this->assertStringContainsString($this->routeparser->urlFor('addDynamicField', ['form_name' => 'plugin1']), $body);

        //a field is added to the plugin form
        $request = $this->createRequest('doAddDynamicField', ['form_name' => 'plugin1'], 'POST');
        $request = $request->withParsedBody([
            'field_name' => 'Thing color',
            'field_perm' => (string)\Galette\Entity\FieldsConfig::USER_WRITE,
            'field_type' => (string)\Galette\DynamicFields\DynamicField::LINE,
            'field_required' => '0',
        ]);
        $test_response = $this->app->handle($request);
        $this->assertSame(301, $test_response->getStatusCode());
        $fields = (new \Galette\Repository\DynamicFieldsSet($this->zdb, $this->login))->getList('plugin1');
        $this->assertCount(1, $fields);
        $this->assertSame('Thing color', current($fields)->getName());
        $this->flash_data = [];

        //a form nobody declared is not found
        foreach (['configureDynamicFields', 'addDynamicField'] as $route_name) {
            $request = $this->createRequest($route_name, ['form_name' => 'plugin2']);
            $this->assertSame(404, $this->app->handle($request)->getStatusCode());
        }

        $this->login->logout();
    }

    /**
     * Test values of a plugin object are checked, stored and removed by the core
     */
    public function testValues(): void
    {
        $this->logSuperAdmin();
        $field_id = $this->createDynamicField('Thing color');

        $thing = new \GaletteTest1Plugin\Thing($this->zdb, $this->login, 1);
        $this->assertTrue($thing->check(['info_field_' . $field_id . '_1' => 'red']));
        $this->assertTrue($thing->store());

        //values are stored for the plugin form, apart from members with the same id
        $loaded = new \GaletteTest1Plugin\Thing($this->zdb, $this->login, 1);
        $this->assertSame('red', $loaded->getDynamicFields()->getValues($field_id)[0]['field_val']);
        $select = $this->zdb->select(\Galette\Entity\DynamicFieldsHandle::TABLE);
        $select->where(['field_id' => $field_id]);
        $rows = $this->zdb->execute($select)->toArray();
        $this->assertCount(1, $rows);
        $this->assertSame('plugin1', $rows[0]['field_form']);
        $this->assertSame(1, (int)$rows[0]['item_id']);

        $this->assertTrue($loaded->remove());
        $this->assertSame(0, $this->zdb->execute($select)->count());

        $this->login->logout();
    }

    /**
     * Test values of a new plugin object are stored once it gets its identifier
     */
    public function testNewObjectValues(): void
    {
        $this->logSuperAdmin();
        $field_id = $this->createDynamicField('Thing size');

        $thing = new \GaletteTest1Plugin\Thing($this->zdb, $this->login);
        $this->assertNull($thing->getID());
        $this->assertTrue($thing->check(['info_field_' . $field_id . '_1' => 'large']));
        $this->assertTrue($thing->store(new_id: 7));
        $this->assertSame(7, $thing->getID());

        $loaded = new \GaletteTest1Plugin\Thing($this->zdb, $this->login, 7);
        $this->assertSame('large', $loaded->getDynamicFields()->getValues($field_id)[0]['field_val']);

        $this->assertTrue($loaded->remove());
        $this->login->logout();
    }

    /**
     * Test plugin pages draw, check and store dynamic fields with the core components
     */
    public function testPages(): void
    {
        $this->logSuperAdmin();
        $line_id = $this->createDynamicField(name: 'Thing colors', repeat: 2);
        $required_id = $this->createDynamicField(name: 'Thing serial', required: true);
        $file_id = $this->createDynamicField(name: 'Thing picture', type: \Galette\DynamicFields\DynamicField::FILE);

        //the form draws the fields, and sends files
        $test_response = $this->app->handle($this->createRequest('plugin1_thing_edit', ['id' => '1']));
        $this->assertSame(200, $test_response->getStatusCode());
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('enctype="multipart/form-data"', $body);
        $this->assertStringContainsString('action="' . $this->routeparser->urlFor('plugin1_thing_doedit', ['id' => '1']) . '"', $body);
        foreach ([$line_id, $required_id, $file_id] as $field_id) {
            $this->assertStringContainsString('name="info_field_' . $field_id . '_1"', $body);
        }
        //a repeated field gets its template to add occurrences
        $this->assertStringContainsString('repeat_template', $body);

        //a missing required value goes back to the form
        $test_response = $this->app->handle(
            $this->createRequest('plugin1_thing_doedit', ['id' => '1'], 'POST')
                ->withParsedBody([
                    'info_field_' . $line_id . '_1' => 'red',
                    'info_field_' . $required_id . '_1' => ''
                ])
        );
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('plugin1_thing_edit', ['id' => '1'])]],
            $test_response->getHeaders()
        );
        $this->expectFlashData(['error_detected' => ['Missing required field Thing serial']]);
        $this->assertSame(0, $this->countValues(1));

        //repeated values and a file are stored
        copy(GALETTE_TESTS_PATH . '/fixtures/galette_pro.png', sys_get_temp_dir() . '/galette_pro.png');
        $test_response = $this->app->handle(
            $this->createRequest('plugin1_thing_doedit', ['id' => '1'], 'POST')
                ->withParsedBody([
                    'info_field_' . $line_id . '_1' => 'red',
                    'info_field_' . $line_id . '_2' => 'blue',
                    'info_field_' . $required_id . '_1' => 'SN-42'
                ])
                ->withUploadedFiles([
                    'info_field_' . $file_id . '_1' => new \Slim\Psr7\UploadedFile(
                        fileNameOrStream: sys_get_temp_dir() . '/galette_pro.png',
                        name: 'galette_pro.png',
                        type: 'image/png',
                        size: filesize(sys_get_temp_dir() . '/galette_pro.png'),
                        error: UPLOAD_ERR_OK
                    )
                ])
        );
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('plugin1_thing', ['id' => '1'])]],
            $test_response->getHeaders()
        );
        $filename = sprintf('plugin1_1_field_%s_value_1', $file_id);
        $this->assertFileExists(GALETTE_FILES_PATH . $filename);
        $thing = new \GaletteTest1Plugin\Thing($this->zdb, $this->login, 1);
        $this->assertSame(
            ['red', 'blue'],
            array_column($thing->getDynamicFields()->getValues($line_id), 'field_val')
        );
        $this->assertSame('galette_pro.png', $thing->getDynamicFields()->getValues($file_id)[0]['field_val']);

        //the display page shows values, and links the file
        $test_response = $this->app->handle($this->createRequest('plugin1_thing', ['id' => '1']));
        $this->assertSame(200, $test_response->getStatusCode());
        $body = (string)$test_response->getBody();
        foreach (['Thing colors', 'red', 'blue', 'SN-42'] as $expected) {
            $this->assertStringContainsString($expected, $body);
        }
        $this->assertStringContainsString(
            $this->routeparser->urlFor(
                'getDynamicFile',
                ['form_name' => 'plugin1', 'id' => '1', 'fid' => (string)$file_id, 'pos' => '1', 'name' => 'galette_pro.png']
            ),
            $body
        );

        //things the plugin does not know are not found
        $test_response = $this->app->handle($this->createRequest('plugin1_thing', ['id' => '3']));
        $this->assertSame(404, $test_response->getStatusCode());

        $this->assertTrue($thing->remove());
        unlink(GALETTE_FILES_PATH . $filename);
        $this->login->logout();
    }

    /**
     * Count stored values of a thing
     *
     * @param int $thing_id Thing identifier
     */
    private function countValues(int $thing_id): int
    {
        $select = $this->zdb->select(\Galette\Entity\DynamicFieldsHandle::TABLE);
        $select->where(['field_form' => 'plugin1', 'item_id' => $thing_id]);
        return $this->zdb->execute($select)->count();
    }

    /**
     * Test the form of a plugin object
     */
    public function testFormName(): void
    {
        $thing = new \GaletteTest1Plugin\Thing($this->zdb, $this->login, 1);
        $this->assertSame('plugin1', $thing->getFormName());

        //a subclass belongs to the form of its parent
        $special = new class ($this->zdb, $this->login, 1) extends \GaletteTest1Plugin\Thing {
        };
        $this->assertSame('plugin1', $special->getFormName());

        //no more form once the plugin is gone
        \Galette\Repository\DynamicFieldsSet::resetForms();
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No dynamic form is declared for class GaletteTest1Plugin\Thing: is its plugin active?');
        $thing->getFormName();
    }

    /**
     * Test files of a plugin object are sent to users the plugin lets see the object
     */
    public function testGetDynamicFile(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $field_id = $this->createDynamicField(
            name: 'Thing picture',
            type: \Galette\DynamicFields\DynamicField::FILE,
            perm: \Galette\Entity\FieldsConfig::USER_READ
        );
        $dynfile_id = 'info_field_' . $field_id . '_1';

        //both things get a file
        $filenames = [];
        foreach ([1, 2] as $thing_id) {
            copy(GALETTE_TESTS_PATH . '/fixtures/galette_pro.png', sys_get_temp_dir() . '/galette_pro.png');
            $thing = new \GaletteTest1Plugin\Thing($this->zdb, $this->login, $thing_id);
            $thing->storeFiles([
                $dynfile_id => new \Slim\Psr7\UploadedFile(
                    fileNameOrStream: sys_get_temp_dir() . '/galette_pro.png',
                    name: 'galette_pro.png',
                    type: 'image/png',
                    size: filesize(sys_get_temp_dir() . '/galette_pro.png'),
                    error: UPLOAD_ERR_OK
                )
            ]);
            $this->assertSame([], $thing->getErrors());
            $filenames[$thing_id] = sprintf('plugin1_%1$s_field_%2$s_value_1', $thing_id, $field_id);
            $this->assertFileExists(GALETTE_FILES_PATH . $filenames[$thing_id]);
        }

        $expected_headers = [
            'Content-Description' => ['File Transfer'],
            'Content-Type' => ['image/png'],
            'Content-Disposition' => ['attachment;filename="galette_pro.png"'],
            'Pragma' => ['public'],
            'Content-Transfer-Encoding' => ['binary'],
            'Expires' => ['0'],
            'Cache-Control' => ['must-revalidate']
        ];
        foreach ([1, 2] as $thing_id) {
            $test_response = $this->app->handle($this->createFileRequest($thing_id, $field_id));
            $this->expectOK($test_response, $expected_headers);
        }

        //an object the plugin does not know
        $test_response = $this->app->handle($this->createFileRequest(3, $field_id));
        $this->expectDenied($test_response);

        $this->login->logout();

        //a member sees the first thing only, as the plugin says
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertSame($member_one->id, $this->login->id);
        $test_response = $this->app->handle($this->createFileRequest(1, $field_id));
        $this->expectOK($test_response, $expected_headers);
        $test_response = $this->app->handle($this->createFileRequest(2, $field_id));
        $this->expectDenied($test_response);
        $this->login->logout();

        //a form nobody declared is not found
        $this->logSuperAdmin();
        $request = $this->createRequest(
            'getDynamicFile',
            [
                'form_name' => 'plugin2',
                'id' => '1',
                'fid' => (string)$field_id,
                'pos' => '1',
                'name' => 'galette_pro.png'
            ]
        );
        $this->assertSame(404, $this->app->handle($request)->getStatusCode());
        $this->login->logout();

        foreach ($filenames as $filename) {
            unlink(GALETTE_FILES_PATH . $filename);
        }
    }

    /**
     * Create a request for a file of a thing
     *
     * @param int $thing_id Thing identifier
     * @param int $field_id Field identifier
     */
    private function createFileRequest(int $thing_id, int $field_id): \Slim\Psr7\Request
    {
        return $this->createRequest(
            'getDynamicFile',
            [
                'form_name' => 'plugin1',
                'id' => (string)$thing_id,
                'fid' => (string)$field_id,
                'pos' => '1',
                'name' => 'galette_pro.png'
            ]
        );
    }

    /**
     * Expect a file was refused, going back to the page the plugin chose
     *
     * @param \Psr\Http\Message\ResponseInterface $test_response Response
     */
    private function expectDenied(\Psr\Http\Message\ResponseInterface $test_response): void
    {
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('plugin1_public_page')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['error_detected' => ['You do not have permission for requested URL.']]);
    }

    /**
     * Create a field of the plugin form
     *
     * @param string $name     Field name
     * @param int    $type     Field type (default to Line)
     * @param int    $perm     Field permission (default to user write)
     * @param bool   $required Whether the field is required
     * @param int    $repeat   Number of occurrences, 0 for unlimited
     *
     * @return int The created field id
     */
    private function createDynamicField(
        string $name,
        int $type = \Galette\DynamicFields\DynamicField::LINE,
        int $perm = \Galette\Entity\FieldsConfig::USER_WRITE,
        bool $required = false,
        int $repeat = 1
    ): int {
        $df = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $type);
        $stored = $df->store([
            'field_name' => $name,
            'field_perm' => (string)$perm,
            'field_type' => (string)$type,
            'field_required' => $required ? '1' : '0',
            'field_repeat' => (string)$repeat,
            'form_name' => 'plugin1'
        ]);
        $this->assertTrue($stored, implode(' ', $df->getErrors() + $df->getWarnings()));
        return $df->getId();
    }
}
