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
use function Safe\file_get_contents;
use function Safe\filesize;
use function Safe\tempnam;
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

    /** @var array<string> Files to remove once the test is over, whatever its outcome */
    private array $files = [];

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->files = [];
        parent::tearDown();
    }

    /**
     * Get an uploaded file, from a copy of a fixture
     *
     * @param string $fixture Fixture path, from the tests directory
     * @param string $name    Client file name
     * @param int    $error   Upload error
     */
    private function upload(string $fixture, string $name, int $error = UPLOAD_ERR_OK): \Slim\Psr7\UploadedFile
    {
        //a path of its own: another run may use the same fixture
        $tmp = tempnam(sys_get_temp_dir(), 'galette_upload_');
        $this->files[] = $tmp;
        copy(GALETTE_TESTS_PATH . '/' . $fixture, $tmp);
        return new \Slim\Psr7\UploadedFile(
            fileNameOrStream: $tmp,
            name: $name,
            type: 'image/png',
            size: filesize($tmp),
            error: $error
        );
    }

    /**
     * Get the path of a stored file, removed once the test is over
     *
     * @param int $thing_id Thing identifier
     * @param int $field_id Field identifier
     * @param int $pos      Occurrence
     */
    private function storedPath(int $thing_id, int $field_id, int $pos = 1): string
    {
        $path = GALETTE_FILES_PATH . sprintf('plugin1_%1$s_field_%2$s_value_%3$s', $thing_id, $field_id, $pos);
        $this->files[] = $path;
        return $path;
    }

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
     * Test objects may hide fields of their form, or require them
     */
    public function testObjectModes(): void
    {
        $this->logSuperAdmin();
        $hidden_id = $this->createDynamicField('Thing color', required: true);
        $required_id = $this->createDynamicField('Thing size');
        $optional_id = $this->createDynamicField('Thing weight', required: true);

        $thing = new \GaletteTest1Plugin\Thing($this->zdb, $this->login, 1);
        $this->assertTrue($thing->check(['info_field_' . $hidden_id . '_1' => 'red']));
        $this->assertTrue($thing->store());

        $thing = new \GaletteTest1Plugin\Thing($this->zdb, $this->login, 1);
        $thing->hidden_fields = [$hidden_id => true];
        $thing->required_fields = [$required_id => true, $optional_id => false];

        //a hidden field is not displayed, but kept in the form for scripts to show it again
        $view = $this->container->get(\Slim\Views\Twig::class);
        $form = $view->fetch('components/dynamic_fields.html.twig', ['object' => $thing]);
        $this->assertMatchesRegularExpression('/class="[^"]*column displaynone" data-dynfield="' . $hidden_id . '"/', $form);
        $this->assertMatchesRegularExpression('/class="[^"]*column" data-dynfield="' . $required_id . '"/', $form);
        $this->assertMatchesRegularExpression('/<div class="field required">\s*<label for="info_field_' . $required_id . '_1"/', $form);
        $this->assertMatchesRegularExpression('/<div class="field">\s*<label for="info_field_' . $optional_id . '_1"/', $form);
        $display = $view->fetch('elements/display_dynamic_fields.html.twig', ['object' => $thing]);
        $this->assertStringNotContainsString('Thing color', $display);
        $this->assertStringContainsString('Thing size', $display);

        //the object decides which fields are required
        $this->assertFalse($thing->check([
            'info_field_' . $hidden_id . '_1' => '',
            'info_field_' . $required_id . '_1' => '',
            'info_field_' . $optional_id . '_1' => '',
        ]));
        $this->assertSame(['Missing required field Thing size'], $thing->getErrors());

        //a hidden field is not checked, and keeps its stored value
        $this->assertTrue($thing->check([
            'info_field_' . $hidden_id . '_1' => 'blue',
            'info_field_' . $required_id . '_1' => 'large',
            'info_field_' . $optional_id . '_1' => '',
        ]));
        $this->assertTrue($thing->store());
        $loaded = new \GaletteTest1Plugin\Thing($this->zdb, $this->login, 1);
        $this->assertSame('red', $loaded->getDynamicFields()->getValues($hidden_id)[0]['field_val']);
        $this->assertSame('large', $loaded->getDynamicFields()->getValues($required_id)[0]['field_val']);

        $this->assertTrue($loaded->remove());
        $this->login->logout();
    }

    /**
     * Test files are checked before the object is stored, and stored where the form expects them
     */
    public function testUploads(): void
    {
        $this->logSuperAdmin();
        $field_id = $this->createDynamicField('Thing pictures', type: \Galette\DynamicFields\DynamicField::FILE, repeat: 0);
        $small = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, \Galette\DynamicFields\DynamicField::FILE);
        $this->assertTrue($small->store([
            'field_name' => 'Thing icon',
            'field_perm' => (string)\Galette\Entity\FieldsConfig::USER_WRITE,
            'field_type' => (string)\Galette\DynamicFields\DynamicField::FILE,
            'field_required' => '0',
            'field_size' => '1',
            'form_name' => 'plugin1'
        ]));
        $small_id = $small->getId();

        $upload = $this->upload(...);
        $key = 'info_field_' . $field_id . '_';
        $path = fn(int $pos): string => $this->storedPath(1, $field_id, $pos);

        //two pictures
        $thing = new \GaletteTest1Plugin\Thing($this->zdb, $this->login, 1);
        $files = [$key . '1' => $upload('fixtures/galette_pro.png', 'a.png'), $key . '2' => $upload('test.gif', 'b.gif')];
        //a form always posts something
        $this->assertTrue($thing->check(['valid' => '1'], $files));
        $this->assertTrue($thing->store());
        $thing->storeFiles($files);
        $this->assertSame([], $thing->getErrors());
        $this->assertSame(filesize(GALETTE_TESTS_PATH . '/test.gif'), filesize($path(2)));

        //the first one is dropped, the second one replaced: the replacement takes its new place
        $thing = new \GaletteTest1Plugin\Thing($this->zdb, $this->login, 1);
        $files = [$key . '2' => $upload('fake_image.jpg', 'c.jpg')];
        $this->assertTrue($thing->check([$key . '1' => '1'], $files));
        $this->assertTrue($thing->store());
        $thing->storeFiles($files);
        $this->assertSame([], $thing->getErrors());
        $loaded = new \GaletteTest1Plugin\Thing($this->zdb, $this->login, 1);
        $this->assertSame(
            [['val_index' => 1, 'field_val' => 'c.jpg']],
            array_map(
                fn(array $value): array => ['val_index' => (int)$value['val_index'], 'field_val' => $value['field_val']],
                $loaded->getDynamicFields()->getValues($field_id)
            )
        );
        $this->assertSame(filesize(GALETTE_TESTS_PATH . '/fake_image.jpg'), filesize($path(1)));
        $this->assertFileDoesNotExist($path(2));

        //a file too big, or not fully received, stops the object from being stored
        $thing = new \GaletteTest1Plugin\Thing($this->zdb, $this->login, 1);
        $this->assertFalse($thing->check(
            [$key . '1' => '1'],
            ['info_field_' . $small_id . '_1' => $upload('fixtures/galette_pro.png', 'big.png')]
        ));
        $this->assertSame(
            [sprintf('File is too big. Maximum allowed size is %1$s', \Galette\Util\Filesize::fromKilobytes(1))],
            $thing->getErrors()
        );
        $this->expectLogEntry(\Analog\Analog::ERROR, 'file too large');
        $this->assertFalse($thing->check(
            [$key . '1' => '1'],
            [$key . '1' => $upload('test.gif', 'partial.gif', UPLOAD_ERR_PARTIAL)]
        ));
        $this->assertSame(['File for Thing pictures has not been received, please send it again.'], $thing->getErrors());
        $this->expectLogEntry(\Analog\Analog::ERROR, 'file upload error: ' . UPLOAD_ERR_PARTIAL);

        //files of a field the thing hides are ignored, as they are once it is stored
        $thing = new \GaletteTest1Plugin\Thing($this->zdb, $this->login, 1);
        $thing->hidden_fields = [$small_id => true];
        $files = ['info_field_' . $small_id . '_1' => $upload('fixtures/galette_pro.png', 'big.png')];
        $this->assertTrue($thing->check(['valid' => '1'], $files), implode(' ', $thing->getErrors()));
        $thing->storeFiles($files);
        $this->assertSame([], $thing->getErrors());
        $this->assertFileDoesNotExist($this->storedPath(1, $small_id));

        //files that arrive once the object is stored are reported
        $thing = new \GaletteTest1Plugin\Thing($this->zdb, $this->login, 1);
        $thing->storeFiles([$key . '2' => $upload('test.gif', 'late.gif', UPLOAD_ERR_PARTIAL)]);
        $this->assertSame(['File for Thing pictures has not been received, please send it again.'], $thing->getErrors());
        $this->expectLogEntry(\Analog\Analog::ERROR, 'file upload error: ' . UPLOAD_ERR_PARTIAL);
        $this->assertFileDoesNotExist($path(2));

        $loaded = new \GaletteTest1Plugin\Thing($this->zdb, $this->login, 1);
        $this->assertTrue($loaded->remove());
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
     * Test dynamic fields of a plugin object are replaced in texts
     */
    public function testReplacements(): void
    {
        $this->logSuperAdmin();
        $line_id = $this->createDynamicField('Thing notes');
        $choice = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, \Galette\DynamicFields\DynamicField::CHOICE);
        $this->assertTrue($choice->store([
            'field_name' => 'Thing size',
            'field_perm' => (string)\Galette\Entity\FieldsConfig::USER_WRITE,
            'field_type' => (string)\Galette\DynamicFields\DynamicField::CHOICE,
            'field_required' => '0',
            'fixed_values' => "Small\nLarge",
            'form_name' => 'plugin1'
        ]));
        $choice_id = $choice->getId();

        $thing = new \GaletteTest1Plugin\Thing($this->zdb, $this->login, 1);
        $this->assertTrue($thing->check([
            'info_field_' . $line_id . '_1' => 'R & D',
            'info_field_' . $choice_id . '_1' => '1'
        ]));
        $this->assertTrue($thing->store());
        $thing = new \GaletteTest1Plugin\Thing($this->zdb, $this->login, 1);

        $texts = new \Galette\Entity\Texts($this->preferences, $this->routeparser);
        $this->assertArrayHasKey('dynfield_' . $line_id . '_plugin1', $texts->getDynamicPatterns('plugin1'));
        $this->assertArrayHasKey('label_dynfield_' . $choice_id . '_plugin1', $texts->getDynamicPatterns('plugin1'));

        $update = $this->zdb->update(\Galette\Entity\Texts::TABLE);
        $update
            ->set([
                'tbody' => '{LABEL_DYNFIELD_' . $line_id . '_PLUGIN1}: {DYNFIELD_' . $line_id . '_PLUGIN1}'
                    . '|{LABEL_DYNFIELD_' . $choice_id . '_PLUGIN1}: {DYNFIELD_' . $choice_id . '_PLUGIN1}'
            ])
            ->where(['tref' => 'pwd', 'tlang' => \Galette\Core\I18n::DEFAULT_LANG]);
        $this->zdb->execute($update);

        $texts
            ->setMember($this->getMemberOne())
            ->setNoContribution()
            ->setDynamicObject($thing);
        $texts->getTexts('pwd', \Galette\Core\I18n::DEFAULT_LANG);
        $this->assertSame('Thing notes: R & D|Thing size: Large', $texts->getBody());

        //a field added once the thing values are loaded is replaced with nothing
        $this->assertCount(1, $thing->getDynamicFields()->getValues($line_id));
        $added_id = $this->createDynamicField('Thing weight');
        $update = $this->zdb->update(\Galette\Entity\Texts::TABLE);
        $update
            ->set(['tbody' => '{DYNFIELD_' . $line_id . '_PLUGIN1}|{LABEL_DYNFIELD_' . $added_id . '_PLUGIN1}{DYNFIELD_' . $added_id . '_PLUGIN1}'])
            ->where(['tref' => 'pwd', 'tlang' => \Galette\Core\I18n::DEFAULT_LANG]);
        $this->zdb->execute($update);
        $texts = new \Galette\Entity\Texts($this->preferences, $this->routeparser);
        $texts
            ->setMember($this->getMemberOne())
            ->setNoContribution()
            ->setDynamicObject($thing);
        $texts->getTexts('pwd', \Galette\Core\I18n::DEFAULT_LANG);
        $this->assertSame('R & D|', $texts->getBody());

        //nor are the fields the thing hides
        $thing->hidden_fields = [$line_id => true];
        $update = $this->zdb->update(\Galette\Entity\Texts::TABLE);
        $update
            ->set(['tbody' => '{LABEL_DYNFIELD_' . $line_id . '_PLUGIN1}{DYNFIELD_' . $line_id . '_PLUGIN1}|{DYNFIELD_' . $choice_id . '_PLUGIN1}'])
            ->where(['tref' => 'pwd', 'tlang' => \Galette\Core\I18n::DEFAULT_LANG]);
        $this->zdb->execute($update);
        $texts = new \Galette\Entity\Texts($this->preferences, $this->routeparser);
        $texts
            ->setMember($this->getMemberOne())
            ->setNoContribution()
            ->setDynamicObject($thing);
        $texts->getTexts('pwd', \Galette\Core\I18n::DEFAULT_LANG);
        $this->assertSame('|Large', $texts->getBody());

        //only objects with dynamic fields
        $this->expectException(\InvalidArgumentException::class);
        $texts->setDynamicObject(new \stdClass());
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

        //a required field the form lacks goes back to the form
        $test_response = $this->app->handle(
            $this->createRequest('plugin1_thing_doedit', ['id' => '1'], 'POST')
                ->withParsedBody(['info_field_' . $line_id . '_1' => 'red'])
        );
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('plugin1_thing_edit', ['id' => '1'])]],
            $test_response->getHeaders()
        );
        $this->expectFlashData(['error_detected' => ['Missing required field Thing serial']]);
        $this->assertSame(0, $this->countValues(1));

        //so does a missing required value
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
        $test_response = $this->app->handle(
            $this->createRequest('plugin1_thing_doedit', ['id' => '1'], 'POST')
                ->withParsedBody([
                    'info_field_' . $line_id . '_1' => 'red',
                    'info_field_' . $line_id . '_2' => 'blue',
                    'info_field_' . $required_id . '_1' => 'SN-42'
                ])
                ->withUploadedFiles([
                    'info_field_' . $file_id . '_1' => $this->upload('fixtures/galette_pro.png', 'galette_pro.png')
                ])
        );
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('plugin1_thing', ['id' => '1'])]],
            $test_response->getHeaders()
        );
        $this->assertFileExists($this->storedPath(1, $file_id));
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

        $line_id = $this->createDynamicField(name: 'Thing color', perm: \Galette\Entity\FieldsConfig::USER_READ);
        $content = file_get_contents(GALETTE_TESTS_PATH . '/fixtures/galette_pro.png');

        //both things get a file
        foreach ([1, 2] as $thing_id) {
            $thing = new \GaletteTest1Plugin\Thing($this->zdb, $this->login, $thing_id);
            $thing->storeFiles([$dynfile_id => $this->upload('fixtures/galette_pro.png', 'galette_pro.png')]);
            $this->assertSame([], $thing->getErrors());
            $this->assertFileExists($this->storedPath($thing_id, $field_id));
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
            $this->assertSame($content, (string)$test_response->getBody());
        }

        //an object the plugin does not know
        $test_response = $this->app->handle($this->createFileRequest(3, $field_id));
        $this->expectDenied($test_response);

        //a field that holds no file
        $test_response = $this->app->handle($this->createFileRequest(1, $line_id));
        $this->expectDenied($test_response);

        $this->login->logout();

        //a member sees the first thing only, as the plugin says
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertSame($member_one->id, $this->login->id);
        $test_response = $this->app->handle($this->createFileRequest(1, $field_id));
        $this->expectOK($test_response, $expected_headers);
        $this->assertSame($content, (string)$test_response->getBody());
        $test_response = $this->app->handle($this->createFileRequest(2, $field_id));
        $this->expectDenied($test_response);

        //and their pages follow the same rule; the edit form is for staff only
        $test_response = $this->app->handle($this->createRequest('plugin1_thing', ['id' => '1']));
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertStringNotContainsString(
            $this->routeparser->urlFor('plugin1_thing_edit', ['id' => '1']),
            (string)$test_response->getBody()
        );
        $test_response = $this->app->handle($this->createRequest('plugin1_thing', ['id' => '2']));
        $this->assertSame(404, $test_response->getStatusCode());
        $this->login->logout();

        //a file left on disk once its value is gone is not sent
        $this->logSuperAdmin();
        $thing = new \GaletteTest1Plugin\Thing($this->zdb, $this->login, 1);
        $this->assertTrue($thing->remove());
        $this->assertFileExists($this->storedPath(1, $field_id));
        $test_response = $this->app->handle($this->createFileRequest(1, $field_id));
        $this->assertSame(['Location' => [$this->routeparser->urlFor('plugin1_public_page')]], $test_response->getHeaders());
        $this->expectFlashData(['error_detected' => ['The file does not exists or cannot be read :(']]);
        $this->expectLogEntry(\Analog\Analog::WARNING, 'that does not exists.');
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
        //the message follows the language of the user
        $this->expectFlashData(['error_detected' => [_T('You do not have permission for requested URL.')]]);
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
