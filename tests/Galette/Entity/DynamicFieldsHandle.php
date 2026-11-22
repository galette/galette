<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Entity;

use Galette\Tests\GaletteTestCase;

use function Safe\chmod;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\fileperms;
use function Safe\mkdir;
use function Safe\rename;
use function Safe\rmdir;
use function Safe\tempnam;
use function Safe\unlink;

/**
 * Dynamic fields values handle tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DynamicFieldsHandle extends GaletteTestCase
{
    protected int $seed = 6548791325;

    /** @var array<string> Files created on disk by a test */
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
        unset($GLOBALS['preferences']);

        parent::tearDown();
    }

    /**
     * Create a dynamic field on members form
     *
     * @param int    $type     Field type
     * @param string $name     Field name
     * @param ?int   $repeat   Number of occurrences
     * @param int    $perm     Field permission
     * @param bool   $required Is field required
     * @param string $form     Form name
     */
    private function createField(
        int $type,
        string $name,
        ?int $repeat,
        int $perm = \Galette\Entity\FieldsConfig::USER_WRITE,
        bool $required = false,
        string $form = 'adh'
    ): \Galette\DynamicFields\DynamicField {
        $field = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $type);
        $stored = $field->store([
            'form_name'         => $form,
            'field_name'        => $name,
            'field_perm'        => $perm,
            'field_type'        => $type,
            'field_required'    => (int)$required,
            'field_repeat'      => $repeat
        ]);
        $this->assertTrue(
            $stored,
            implode(' ', $field->getErrors() + $field->getWarnings())
        );

        return $field;
    }

    /**
     * Get a member with its dynamic fields loaded
     */
    private function getMemberWithDynamics(): \Galette\Entity\Adherent
    {
        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            $this->adh->id,
            ['dynamics' => true] + $this->adh->deps
        );
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        return $adh;
    }

    /**
     * Get stored occurrences of a field, as `val_index => field_val`
     *
     * @param \Galette\Entity\Adherent $adh   Member
     * @param int                      $field Field id
     *
     * @return array<int, string>
     */
    private function getStoredOccurrences(\Galette\Entity\Adherent $adh, int $field): array
    {
        $occurrences = [];
        foreach ($adh->getDynamicFields()->getValues($field) as $value) {
            $occurrences[(int)$value['val_index']] = $value['field_val'];
        }
        ksort($occurrences);

        return $occurrences;
    }

    /**
     * Test values of fields a member cannot see survive their own edition
     */
    public function testHiddenFieldsValuesAreKept(): void
    {
        $this->logSuperAdmin();
        $field = $this->createField(
            type: \Galette\DynamicFields\DynamicField::LINE,
            name: 'Staff only',
            repeat: null,
            perm: \Galette\Entity\FieldsConfig::STAFF
        );
        $fid = $field->getId();

        $this->getMemberOne();
        $adh = $this->getMemberWithDynamics();
        $data = $this->dataAdherentOne() + ['info_field_' . $fid . '_1' => 'Staff note'];
        $this->assertTrue($adh->check($data, [], []), implode(' ', $adh->getErrors()));
        $this->assertTrue($adh->store());
        $adh = $this->getMemberWithDynamics();
        $this->assertSame([1 => 'Staff note'], $this->getStoredOccurrences($adh, $fid));
        $this->login->logout();

        //the member cannot see any dynamic field, and edits their own account
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $adh = $this->getMemberWithDynamics();
        $this->assertSame([], $adh->getDynamicFields()->getFields());
        $this->assertTrue($adh->check($mdata, [], []), implode(' ', $adh->getErrors()));
        $this->assertTrue($adh->store());
        $this->login->logout();

        $this->logSuperAdmin();
        $adh = $this->getMemberWithDynamics();
        $this->assertSame([1 => 'Staff note'], $this->getStoredOccurrences($adh, $fid));
    }

    /**
     * Test inaccessible fields are neither listed nor loaded, and their values survive
     */
    public function testInaccessibleFieldsAreHidden(): void
    {
        $this->logSuperAdmin();
        $field = $this->createField(
            type: \Galette\DynamicFields\DynamicField::LINE,
            name: 'Nobody',
            repeat: null,
            perm: \Galette\Entity\FieldsConfig::NOBODY
        );
        $fid = $field->getId();

        $this->getMemberOne();
        $insert = $this->zdb->insert(\Galette\Entity\DynamicFieldsHandle::TABLE);
        $insert->values([
            'item_id'       => $this->adh->id,
            'field_id'      => $fid,
            'field_form'    => 'adh',
            'val_index'     => 1,
            'field_val'     => 'Hidden value'
        ]);
        $this->zdb->execute($insert);

        $fields = new \Galette\Repository\DynamicFieldsSet($this->zdb, $this->login);
        $this->assertArrayNotHasKey($fid, $fields->getList('adh'));
        $this->assertArrayHasKey($fid, $fields->getList('adh', inaccessible: true));

        $adh = $this->getMemberWithDynamics();
        $this->assertArrayNotHasKey($fid, $adh->getDynamicFields()->getFields());

        //storing the member does not drop the value
        $this->assertTrue($adh->check($this->dataAdherentOne(), [], []), implode(' ', $adh->getErrors()));
        $this->assertTrue($adh->store());
        $select = $this->zdb->select(\Galette\Entity\DynamicFieldsHandle::TABLE);
        $select->where(['item_id' => $this->adh->id, 'field_id' => $fid]);
        $this->assertSame('Hidden value', $this->zdb->execute($select)->current()->field_val);
    }

    /**
     * Test several occurrences of a repeatable field are stored and renumbered
     */
    public function testRepeatableOccurrences(): void
    {
        $this->logSuperAdmin();
        $field = $this->createField(\Galette\DynamicFields\DynamicField::DATE, 'Dynamic dates', 3);
        $this->assertTrue($field->isRepeatable());

        $this->getMemberOne();
        $fid = $field->getId();

        $adh = $this->getMemberWithDynamics();
        $data = $this->dataAdherentOne() + [
            'info_field_' . $fid . '_1' => '2024-01-05',
            'info_field_' . $fid . '_2' => '2024-06-12',
            'info_field_' . $fid . '_3' => '2025-02-28'
        ];
        $this->assertTrue($adh->check($data, [], []), implode(' ', $adh->getErrors()));
        $this->assertTrue($adh->store());

        $adh = $this->getMemberWithDynamics();
        $this->assertSame(
            [
                1 => '2024-01-05',
                2 => '2024-06-12',
                3 => '2025-02-28'
            ],
            $this->getStoredOccurrences($adh, $fid)
        );

        //the middle occurrence is dropped from the form; the last one takes its index
        $adh = $this->getMemberWithDynamics();
        $data = $this->dataAdherentOne() + [
            'info_field_' . $fid . '_1' => '2024-01-05',
            'info_field_' . $fid . '_3' => '2025-02-28'
        ];
        $this->assertTrue($adh->check($data, [], []), implode(' ', $adh->getErrors()));
        $this->assertTrue($adh->store());

        $adh = $this->getMemberWithDynamics();
        $this->assertSame(
            [
                1 => '2024-01-05',
                2 => '2025-02-28'
            ],
            $this->getStoredOccurrences($adh, $fid)
        );
    }

    /**
     * Test a required repeatable field is satisfied by its first occurrence alone
     */
    public function testRequiredRepeatableField(): void
    {
        $this->logSuperAdmin();
        $field = \Galette\DynamicFields\DynamicField::getFieldType(
            $this->zdb,
            \Galette\DynamicFields\DynamicField::DATE
        );
        $this->assertTrue($field->store([
            'form_name'         => 'adh',
            'field_name'        => 'Required dates',
            'field_perm'        => \Galette\Entity\FieldsConfig::USER_WRITE,
            'field_type'        => \Galette\DynamicFields\DynamicField::DATE,
            'field_required'    => 1,
            'field_repeat'      => 0
        ]));

        $this->getMemberOne();
        $fid = $field->getId();

        //an empty extra occurrence does not make the field missing
        $adh = $this->getMemberWithDynamics();
        $data = $this->dataAdherentOne() + [
            'info_field_' . $fid . '_1' => '2024-01-05',
            'info_field_' . $fid . '_2' => ''
        ];
        $this->assertTrue($adh->check($data, [], []), implode(' ', $adh->getErrors()));
        $this->assertTrue($adh->store());

        $adh = $this->getMemberWithDynamics();
        $this->assertSame([1 => '2024-01-05'], $this->getStoredOccurrences($adh, $fid));

        //no occurrence at all is reported once, not once per occurrence
        $adh = $this->getMemberWithDynamics();
        $data = $this->dataAdherentOne() + [
            'info_field_' . $fid . '_1' => '',
            'info_field_' . $fid . '_2' => ''
        ];
        $this->assertSame(['Missing required field Required dates'], $adh->check($data, [], []));
        $this->expectLogEntry(
            \Analog\Analog::ERROR,
            'Some errors has been thew attempting to edit/store a member'
        );
    }

    /**
     * Test any posted value on a file occurrence deletes it
     *
     * The delete checkbox posts `on`; the form also posts a plain marker when an
     * occurrence holding a file is removed from the page, since a stored file is
     * not posted back. Neither value is looked at, and both must delete.
     */
    public function testPostedFileOccurrenceIsDeleted(): void
    {
        $this->logSuperAdmin();
        $field = $this->createField(\Galette\DynamicFields\DynamicField::FILE, 'Deletable files', 2);

        $this->getMemberOne();
        $fid = $field->getId();

        $adh = $this->getMemberWithDynamics();
        $dynamics = $adh->getDynamicFields();
        $dynamics->setValue(item: $adh->id, field: $fid, index: 1, value: 'first.txt');
        $dynamics->setValue(item: $adh->id, field: $fid, index: 2, value: 'second.txt');
        $this->assertTrue($dynamics->storeValues($adh->id));

        /** @var \Galette\DynamicFields\File $stored_field */
        $stored_field = $dynamics->getFields()[$fid];
        $first = GALETTE_FILES_PATH . $stored_field->getFileName($adh->id, 1);
        $second = GALETTE_FILES_PATH . $stored_field->getFileName($adh->id, 2);
        $this->files = [$first, $second];
        file_put_contents($first, 'first');
        file_put_contents($second, 'second');

        $adh = $this->getMemberWithDynamics();
        $data = $this->dataAdherentOne() + ['info_field_' . $fid . '_1' => '1'];
        $this->assertTrue($adh->check($data, [], []), implode(' ', $adh->getErrors()));
        $this->assertTrue($adh->store());

        $adh = $this->getMemberWithDynamics();
        $this->assertSame([1 => 'second.txt'], $this->getStoredOccurrences($adh, $fid));
        $this->assertSame('second', file_get_contents($first));
        $this->assertFileDoesNotExist($second);
    }

    /**
     * Test files follow the occurrences they belong to
     */
    public function testRepeatableFileOccurrences(): void
    {
        $this->logSuperAdmin();
        $field = $this->createField(\Galette\DynamicFields\DynamicField::FILE, 'Dynamic files', 2);
        $this->assertTrue($field->isRepeatable());

        $this->getMemberOne();
        $fid = $field->getId();

        $adh = $this->getMemberWithDynamics();
        $dynamics = $adh->getDynamicFields();
        $dynamics->setValue(item: $adh->id, field: $fid, index: 1, value: 'first.txt');
        $dynamics->setValue(item: $adh->id, field: $fid, index: 2, value: 'second.txt');
        $this->assertTrue($dynamics->storeValues($adh->id));

        /** @var \Galette\DynamicFields\File $stored_field */
        $stored_field = $dynamics->getFields()[$fid];
        $first = GALETTE_FILES_PATH . $stored_field->getFileName($adh->id, 1);
        $second = GALETTE_FILES_PATH . $stored_field->getFileName($adh->id, 2);
        $this->files = [$first, $second];
        file_put_contents($first, 'first');
        file_put_contents($second, 'second');

        //drop the first occurrence: the second one takes its index, and its file follows
        $adh = $this->getMemberWithDynamics();
        $dynamics = $adh->getDynamicFields();
        $dynamics->unsetValue($fid, 1);
        $this->assertTrue($dynamics->storeValues($adh->id));

        $adh = $this->getMemberWithDynamics();
        $this->assertSame([1 => 'second.txt'], $this->getStoredOccurrences($adh, $fid));

        $this->assertFileExists($first);
        $this->assertSame('second', file_get_contents($first));
        $this->assertFileDoesNotExist($second);
    }

    /**
     * Test a required file field is checked against uploaded and stored files
     */
    public function testRequiredFileField(): void
    {
        $this->logSuperAdmin();
        $field = $this->createField(
            type: \Galette\DynamicFields\DynamicField::FILE,
            name: 'Required file',
            repeat: null,
            required: true
        );
        $fid = $field->getId();
        $key = 'info_field_' . $fid . '_1';

        $this->getMemberOne();
        $data = $this->dataAdherentOne();
        $missing = ['Missing required field Required file'];

        //files are not known: not checked
        $adh = $this->getMemberWithDynamics();
        $this->assertTrue($adh->check($data, [], []), implode(' ', $adh->getErrors()));

        //no file sent, none stored
        $adh = $this->getMemberWithDynamics();
        $this->assertSame($missing, $adh->check(values: $data, required: [], disabled: [], files: []));
        $this->expectLogEntry(
            \Analog\Analog::ERROR,
            'Some errors has been thew attempting to edit/store a member'
        );

        //an empty file input, or a failed upload, is not a file
        foreach ([UPLOAD_ERR_NO_FILE, UPLOAD_ERR_INI_SIZE] as $error) {
            $adh = $this->getMemberWithDynamics();
            $files = [$key => new \Slim\Psr7\UploadedFile(
                fileNameOrStream: 'php://memory',
                name: '',
                size: 0,
                error: $error
            )];
            $this->assertSame($missing, $adh->check(values: $data, required: [], disabled: [], files: $files));
            $this->expectLogEntry(
                \Analog\Analog::ERROR,
                'Some errors has been thew attempting to edit/store a member'
            );
        }

        //a file is sent
        $tmp = tempnam(sys_get_temp_dir(), 'galette');
        $this->files[] = $tmp;
        file_put_contents($tmp, 'content');
        $files = [$key => new \Slim\Psr7\UploadedFile(
            fileNameOrStream: $tmp,
            name: 'sent.txt',
            type: 'text/plain',
            size: 7,
            error: UPLOAD_ERR_OK
        )];
        $adh = $this->getMemberWithDynamics();
        $this->assertTrue($adh->check(values: $data, required: [], disabled: [], files: $files), implode(' ', $adh->getErrors()));

        //a file is stored
        $adh = $this->getMemberWithDynamics();
        $dynamics = $adh->getDynamicFields();
        $dynamics->setValue(item: $adh->id, field: $fid, index: 1, value: 'stored.txt');
        $this->assertTrue($dynamics->storeValues($adh->id));

        $adh = $this->getMemberWithDynamics();
        $this->assertTrue($adh->check(values: $data, required: [], disabled: [], files: []), implode(' ', $adh->getErrors()));

        //the stored file is deleted, and nothing replaces it: it is kept
        $adh = $this->getMemberWithDynamics();
        $this->assertSame($missing, $adh->check(values: $data + [$key => '1'], required: [], disabled: [], files: []));
        $this->expectLogEntry(
            \Analog\Analog::ERROR,
            'Some errors has been thew attempting to edit/store a member'
        );
        $this->assertSame([1 => 'stored.txt'], $this->getStoredOccurrences($adh, $fid));
    }

    /**
     * Test files a web server could run are refused on file fields
     */
    public function testUnsafeFileIsRefused(): void
    {
        $this->logSuperAdmin();
        $field = $this->createField(\Galette\DynamicFields\DynamicField::FILE, 'A file', 1);
        $fid = $field->getId();
        $key = 'info_field_' . $fid . '_1';

        $this->getMemberOne();
        /** @var \Galette\DynamicFields\File $stored_field */
        $stored_field = $this->getMemberWithDynamics()->getDynamicFields()->getFields()[$fid];
        $stored = GALETTE_FILES_PATH . $stored_field->getFileName($this->adh->id, 1);
        $this->files[] = $stored;

        //no allowed list: the files a web server could run are refused all the same
        $this->preferences->pref_upload_extensions = '';
        $GLOBALS['preferences'] = $this->preferences;

        foreach (['shell.php' => 'content', 'notes.txt' => '<?php echo "pwned";'] as $name => $content) {
            $tmp = tempnam(sys_get_temp_dir(), 'galette');
            $this->files[] = $tmp;
            file_put_contents($tmp, $content);
            $adh = $this->getMemberWithDynamics();
            $this->assertSame(
                ['File does not comply with requirements.'],
                $adh->handleFiles([$key => new \Slim\Psr7\UploadedFile(
                    fileNameOrStream: $tmp,
                    name: $name,
                    size: strlen($content),
                    error: UPLOAD_ERR_OK
                )])
            );
            $this->assertFileDoesNotExist($stored);
            $this->expectLogEntry(\Analog\Analog::ERROR, 'Refused file ' . $name . ', a web server could run it.');
            $this->expectLogEntry(\Analog\Analog::ERROR, 'Some errors has been thew attempting to edit/store a member files');
        }

        //any other file is stored
        $tmp = tempnam(sys_get_temp_dir(), 'galette');
        $this->files[] = $tmp;
        file_put_contents($tmp, 'content');
        $adh = $this->getMemberWithDynamics();
        $this->assertTrue($adh->handleFiles([$key => new \Slim\Psr7\UploadedFile(
            fileNameOrStream: $tmp,
            name: 'notes.txt',
            size: 7,
            error: UPLOAD_ERR_OK
        )]));
        $this->assertFileExists($stored);
        $this->expectNoLogEntry();
        unlink($stored);

        //a file the allowed list does not name is refused
        $this->preferences->pref_upload_extensions = 'pdf odt';
        $tmp = tempnam(sys_get_temp_dir(), 'galette');
        $this->files[] = $tmp;
        file_put_contents($tmp, 'content');
        $adh = $this->getMemberWithDynamics();
        $this->assertSame(
            ['File extension is not allowed, only pdf, odt files are.'],
            $adh->handleFiles([$key => new \Slim\Psr7\UploadedFile(
                fileNameOrStream: $tmp,
                name: 'notes.txt',
                size: 7,
                error: UPLOAD_ERR_OK
            )])
        );
        $this->assertFileDoesNotExist($stored);
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Refused file notes.txt, its extension is not allowed.');
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Some errors has been thew attempting to edit/store a member files');
    }

    /**
     * Test a required file field a member cannot change is not checked
     */
    public function testRequiredReadOnlyFileField(): void
    {
        $this->logSuperAdmin();
        $this->createField(
            type: \Galette\DynamicFields\DynamicField::FILE,
            name: 'Read only file',
            repeat: null,
            perm: \Galette\Entity\FieldsConfig::USER_READ,
            required: true
        );
        $this->getMemberOne();
        $this->login->logout();

        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $adh = $this->getMemberWithDynamics();
        $this->assertTrue($adh->check(values: $mdata, required: [], disabled: [], files: []), implode(' ', $adh->getErrors()));
        $this->login->logout();
    }

    /**
     * Store a file occurrence for an object, and write its file on disk
     *
     * @param object                      $object Object (member, contribution, ...)
     * @param \Galette\DynamicFields\File $field  File field
     * @param int                         $index  Value index
     *
     * @return string Path of the file on disk
     */
    private function storeFile(object $object, \Galette\DynamicFields\File $field, int $index): string
    {
        $dynamics = new \Galette\Entity\DynamicFieldsHandle($this->zdb, $this->login, $object);
        $dynamics->setValue(item: $object->id, field: $field->getId(), index: $index, value: 'file' . $index . '.txt');
        $this->assertTrue($dynamics->storeValues($object->id));

        $file = GALETTE_FILES_PATH . $field->getFileName($object->id, $index);
        $this->files[] = $file;
        file_put_contents($file, 'content');

        return $file;
    }

    /**
     * Test the files of a member are removed with the member
     */
    public function testFilesRemovedWithMember(): void
    {
        $this->logSuperAdmin();
        /** @var \Galette\DynamicFields\File $field */
        $field = $this->createField(\Galette\DynamicFields\DynamicField::FILE, 'Member files', 2);

        $two = $this->getMemberTwo();
        $kept = $this->storeFile($two, $field, 1);

        $one = $this->getMemberOne();
        $first = $this->storeFile($one, $field, 1);
        $second = $this->storeFile($one, $field, 2);
        //files uploaded before Galette 1.2 are prefixed with "member" whatever the form; same name here
        $this->assertStringContainsString('member_' . $one->id . '_field_', $first);

        $members = new \Galette\Repository\Members();
        $this->assertTrue($members->removeMembers($one->id));

        $this->assertFileDoesNotExist($first);
        $this->assertFileDoesNotExist($second);
        $this->assertFileExists($kept);
    }

    /**
     * Test the files of a contribution are removed with the contribution,
     * including those named the legacy way
     */
    public function testFilesRemovedWithContribution(): void
    {
        $this->logSuperAdmin();
        /** @var \Galette\DynamicFields\File $field */
        $field = $this->createField(
            type: \Galette\DynamicFields\DynamicField::FILE,
            name: 'Contribution files',
            repeat: 2,
            form: 'contrib'
        );

        $this->getMemberOne();
        $this->createContribution();
        $contrib = $this->contrib;

        $current = $this->storeFile($contrib, $field, 1);
        $this->assertStringContainsString('contrib_' . $contrib->id . '_field_', $current);

        //a file uploaded before Galette 1.2 is prefixed with "member"
        $legacy = GALETTE_FILES_PATH . $field->getFileName($contrib->id, 2, 'member');
        $this->storeFile($contrib, $field, 2);
        rename(GALETTE_FILES_PATH . $field->getFileName($contrib->id, 2), $legacy);
        $this->files[] = $legacy;

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, $contrib->id);
        $this->assertTrue($contrib->remove());

        $this->assertFileDoesNotExist($current);
        $this->assertFileDoesNotExist($legacy);
    }

    /**
     * Test the files of a field are removed with the field
     */
    public function testFilesRemovedWithField(): void
    {
        $this->logSuperAdmin();
        /** @var \Galette\DynamicFields\File $field */
        $field = $this->createField(\Galette\DynamicFields\DynamicField::FILE, 'Removed files', 2);
        /** @var \Galette\DynamicFields\File $other */
        $other = $this->createField(\Galette\DynamicFields\DynamicField::FILE, 'Kept files', 1);

        $two = $this->getMemberTwo();
        $two_file = $this->storeFile($two, $field, 1);

        $one = $this->getMemberOne();
        $one_file = $this->storeFile($one, $field, 2);
        $kept = $this->storeFile($one, $other, 1);

        $this->assertTrue($field->remove());

        $this->assertFileDoesNotExist($one_file);
        $this->assertFileDoesNotExist($two_file);
        $this->assertFileExists($kept);
    }

    /**
     * Test files of a contribution removed within a transaction wait for it to be committed
     */
    public function testFilesRemovedOnCommit(): void
    {
        $this->logSuperAdmin();
        /** @var \Galette\DynamicFields\File $field */
        $field = $this->createField(
            type: \Galette\DynamicFields\DynamicField::FILE,
            name: 'Committed files',
            repeat: 1,
            form: 'contrib'
        );

        $this->getMemberOne();
        $this->createContribution();
        $file = $this->storeFile($this->contrib, $field, 1);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, $this->contrib->id);
        $this->assertTrue($contrib->remove(transaction: false));
        $this->assertFileExists($file);

        $this->zdb->commit();
        $this->assertFileDoesNotExist($file);
    }

    /**
     * Test files of a contribution removed within a transaction that is rolled back are kept
     */
    public function testFilesKeptOnRollback(): void
    {
        $this->logSuperAdmin();
        /** @var \Galette\DynamicFields\File $field */
        $field = $this->createField(
            type: \Galette\DynamicFields\DynamicField::FILE,
            name: 'Rolled back files',
            repeat: 1,
            form: 'contrib'
        );

        $this->getMemberOne();
        $this->createContribution();
        $file = $this->storeFile($this->contrib, $field, 1);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, $this->contrib->id);
        $this->assertTrue($contrib->remove(transaction: false));

        //the caller fails after the removal: everything is rolled back
        $this->zdb->rollback();
        $this->assertFileExists($file);

        //and nothing is left waiting for a later commit
        $this->zdb->beginTransaction();
        $this->zdb->commit();
        $this->assertFileExists($file);
    }

    /**
     * Store two files on a repeatable field, and drop the first occurrence within a transaction
     *
     * @return array<string> First and second files
     */
    private function dropFirstFileOccurrence(string $name): array
    {
        $this->logSuperAdmin();
        $field = $this->createField(\Galette\DynamicFields\DynamicField::FILE, $name, 2);

        $this->getMemberOne();
        $fid = $field->getId();

        $adh = $this->getMemberWithDynamics();
        $dynamics = $adh->getDynamicFields();
        $dynamics->setValue(item: $adh->id, field: $fid, index: 1, value: 'first.txt');
        $dynamics->setValue(item: $adh->id, field: $fid, index: 2, value: 'second.txt');
        $this->assertTrue($dynamics->storeValues($adh->id));

        /** @var \Galette\DynamicFields\File $stored_field */
        $stored_field = $dynamics->getFields()[$fid];
        $first = GALETTE_FILES_PATH . $stored_field->getFileName($adh->id, 1);
        $second = GALETTE_FILES_PATH . $stored_field->getFileName($adh->id, 2);
        $this->files = [$first, $second];
        file_put_contents($first, 'first');
        file_put_contents($second, 'second');

        $adh = $this->getMemberWithDynamics();
        $dynamics = $adh->getDynamicFields();
        $dynamics->unsetValue($fid, 1);
        $this->assertTrue($dynamics->storeValues($adh->id, transaction: true));

        //nothing is touched on disk while the transaction is pending
        $this->assertSame('first', file_get_contents($first));
        $this->assertSame('second', file_get_contents($second));

        return [$first, $second];
    }

    /**
     * Test files of occurrences removed from the form wait for the transaction to be committed
     */
    public function testOccurrenceFilesUpdatedOnCommit(): void
    {
        [$first, $second] = $this->dropFirstFileOccurrence('Committed occurrences');

        $this->zdb->commit();
        $this->assertSame('second', file_get_contents($first));
        $this->assertFileDoesNotExist($second);
    }

    /**
     * Test files of occurrences removed from the form are kept when the transaction is rolled back
     */
    public function testOccurrenceFilesKeptOnRollback(): void
    {
        [$first, $second] = $this->dropFirstFileOccurrence('Rolled back occurrences');

        $this->zdb->rollback();
        $this->zdb->beginTransaction();
        $this->zdb->commit();
        $this->assertSame('first', file_get_contents($first));
        $this->assertSame('second', file_get_contents($second));
    }

    /**
     * Test files are left untouched when the first one cannot be removed once committed
     *
     * Renumbering after a failure would overwrite files that have not been moved.
     */
    public function testOccurrenceFilesStopOnFailure(): void
    {
        $this->logSuperAdmin();
        $field = $this->createField(\Galette\DynamicFields\DynamicField::FILE, 'Failing occurrences', 3);

        $this->getMemberOne();
        $fid = $field->getId();

        $adh = $this->getMemberWithDynamics();
        $dynamics = $adh->getDynamicFields();
        $dynamics->setValue(item: $adh->id, field: $fid, index: 1, value: 'first.txt');
        $dynamics->setValue(item: $adh->id, field: $fid, index: 2, value: 'second.txt');
        $dynamics->setValue(item: $adh->id, field: $fid, index: 3, value: 'third.txt');
        $this->assertTrue($dynamics->storeValues($adh->id));

        /** @var \Galette\DynamicFields\File $stored_field */
        $stored_field = $dynamics->getFields()[$fid];
        //a directory cannot be unlinked
        $first = GALETTE_FILES_PATH . $stored_field->getFileName($adh->id, 1);
        $second = GALETTE_FILES_PATH . $stored_field->getFileName($adh->id, 2);
        $third = GALETTE_FILES_PATH . $stored_field->getFileName($adh->id, 3);
        $this->files = [$second, $third];
        mkdir($first);
        file_put_contents($second, 'second');
        file_put_contents($third, 'third');

        try {
            $adh = $this->getMemberWithDynamics();
            $dynamics = $adh->getDynamicFields();
            $dynamics->unsetValue($fid, 1);
            $this->assertTrue($dynamics->storeValues($adh->id, transaction: true));
            $this->zdb->commit();

            $this->assertDirectoryExists($first);
            $this->assertSame('second', file_get_contents($second));
            $this->assertSame('third', file_get_contents($third));
            $this->expectLogEntry(\Analog\Analog::ERROR, 'Unable to update files of removed dynamic fields values');
        } finally {
            rmdir($first);
        }
    }

    /**
     * Test occurrences are not removed when their files cannot be renumbered
     */
    public function testOccurrenceRemovalRefusedOnReadOnlyDirectory(): void
    {
        if (posix_geteuid() === 0) {
            $this->markTestSkipped('Directories are always writable for root.');
        }

        $this->logSuperAdmin();
        $field = $this->createField(\Galette\DynamicFields\DynamicField::FILE, 'Read-only occurrences', 2);

        $this->getMemberOne();
        $fid = $field->getId();

        $adh = $this->getMemberWithDynamics();
        $dynamics = $adh->getDynamicFields();
        $dynamics->setValue(item: $adh->id, field: $fid, index: 1, value: 'first.txt');
        $dynamics->setValue(item: $adh->id, field: $fid, index: 2, value: 'second.txt');
        $this->assertTrue($dynamics->storeValues($adh->id));

        /** @var \Galette\DynamicFields\File $stored_field */
        $stored_field = $dynamics->getFields()[$fid];
        $first = GALETTE_FILES_PATH . $stored_field->getFileName($adh->id, 1);
        $second = GALETTE_FILES_PATH . $stored_field->getFileName($adh->id, 2);
        $this->files = [$first, $second];
        file_put_contents($first, 'first');
        file_put_contents($second, 'second');

        $adh = $this->getMemberWithDynamics();
        $dynamics = $adh->getDynamicFields();
        $dynamics->unsetValue($fid, 1);

        $mode = fileperms(GALETTE_FILES_PATH) & 0o777;
        chmod(GALETTE_FILES_PATH, 0o555);
        try {
            $dynamics->storeValues($adh->id, transaction: true);
            $this->fail('Removal should have been refused');
        } catch (\RuntimeException $e) {
            $this->assertSame(
                'Files directory ' . GALETTE_FILES_PATH . ' is not writable, dynamic files cannot be updated.',
                $e->getMessage()
            );
        } finally {
            chmod(GALETTE_FILES_PATH, $mode);
        }
        $this->expectLogEntry(\Analog\Analog::ERROR, 'An error occurred storing dynamic field. Form name: adh');

        //the caller rolls its transaction back, and nothing is left waiting for a later commit
        $this->zdb->rollback();
        $this->zdb->beginTransaction();
        $this->zdb->commit();
        $this->assertSame('first', file_get_contents($first));
        $this->assertSame('second', file_get_contents($second));
    }
}
