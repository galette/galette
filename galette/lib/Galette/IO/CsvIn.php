<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\IO;

use Galette\Core\I18n;
use Galette\Entity\Title;
use Safe\Exceptions\FilesystemException;
use Throwable;
use Analog\Analog;
use Galette\Core\Logs;
use Galette\Core\Db;
use Galette\Core\Preferences;
use Galette\Core\History;
use Galette\Entity\Adherent;
use Galette\Entity\ImportModel;
use Galette\Entity\FieldsConfig;
use Galette\Entity\Status;
use Galette\Repository\Groups;
use Galette\Repository\Titles;
use Galette\Repository\Members;

use function Safe\fclose;
use function Safe\fopen;

/**
 * CSV imports
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class CsvIn extends Csv
{
    use FileTrait;

    public const string DEFAULT_DIRECTORY = GALETTE_IMPORTS_PATH;
    public const int DATA_IMPORT_ERROR = -10;
    //pseudo fields, members are attached to the groups they name
    public const string GROUPS_FIELD = 'groups';
    public const string MANAGED_GROUPS_FIELD = 'groups_managed';
    public const string GROUPS_SEPARATOR = '|';

    /** @var array<string> */
    protected array $extensions = ['csv', 'txt'];

    /** @var array<string> */
    private array $fields;
    /** @var array<string> */
    private array $default_fields = [
        'nom_adh',
        'prenom_adh',
        'ddn_adh',
        'adresse_adh',
        'cp_adh',
        'ville_adh',
        'pays_adh',
        'tel_adh',
        'gsm_adh',
        'email_adh',
        'prof_adh',
        'pseudo_adh',
        'societe_adh',
        'login_adh',
        'date_crea_adh',
        'id_statut',
        'info_public_adh',
        'info_adh'
    ];

    private bool $dryrun = true;

    /** @var array<string,mixed>  */
    private array $members_fields;
    /** @var array<string,mixed> */
    private array $members_fields_cats;
    /** @var array<string,bool> */
    private array $required;
    /** @var array<int, string> */
    private array $statuses;
    /** @var Title[]  */
    private array $titles;
    /** @var array<string,string> */
    private array $langs;
    /** @var array<string,int> */
    private array $emails;
    /** @var array<int, array{name: string, full_name: string}> */
    private array $groups;
    private Preferences $preferences;
    private History $history;

    /**
     * Default constructor
     *
     * @param Db     $zdb    Database
     * @param Status $status Status instance
     */
    public function __construct(
        private Db $zdb,
        private readonly Status $status
    ) {
        $this->init(
            dest: self::DEFAULT_DIRECTORY,
            extensions: $this->extensions,
            mimes: [
                'csv'    =>    'text/csv',
                'txt'    =>    'text/plain'
            ],
            maxlength: UploadSize::Imports->get()
        );

        parent::__construct(self::DEFAULT_DIRECTORY);
    }

    /**
     * Load fields list from database or from default values
     */
    private function loadFields(): void
    {
        //at last, we got the defaults
        $this->fields = $this->default_fields;

        $model = new ImportModel();
        //we go with default fields if model cannot be loaded
        if ($model->load()) {
            $this->fields = $model->getFields();
        }
    }

    /**
     * Get default fields
     *
     * @return array<string>
     */
    public function getDefaultFields(): array
    {
        return $this->default_fields;
    }

    /**
     * Import members from CSV file
     *
     * @param Db                  $zdb                 Database instance
     * @param Preferences         $preferences         Preferences instance
     * @param History             $history             History instance
     * @param string              $filename            CSV filename
     * @param array<string,mixed> $members_fields      Members fields
     * @param array<string,mixed> $members_fields_cats Members fields categories
     * @param bool                $dryrun              Run in dry run mode (do not store in database)
     */
    public function import(
        Db $zdb,
        Preferences $preferences,
        History $history,
        string $filename,
        array $members_fields,
        array $members_fields_cats,
        bool $dryrun
    ): bool|int {
        //only files from the imports directory can be imported
        if (
            $filename !== basename($filename)
            || !is_file(self::DEFAULT_DIRECTORY . '/' . $filename)
            || !is_readable(self::DEFAULT_DIRECTORY . '/' . $filename)
        ) {
            $this->addError(
                sprintf(
                    _T('File %1$s cannot be open!'),
                    $filename
                )
            );

            return self::INVALID_FILE;
        }

        $this->zdb = $zdb;
        $this->preferences = $preferences;
        $this->history = $history;
        $this->dryrun = $dryrun;

        $this->loadFields();
        $this->members_fields = $members_fields;
        $this->members_fields_cats = $members_fields_cats;

        if (!$this->check($filename)) {
            return self::INVALID_FILE;
        }

        if (!$this->storeMembers($filename)) {
            return self::DATA_IMPORT_ERROR;
        }

        return true;
    }

    /**
     * Check if input file meet requirements
     *
     * @param string $filename File name
     */
    private function check(string $filename): bool
    {
        $this->resetErrors();
        unset($this->emails);
        unset($this->groups);
        try {
            $handle = fopen(self::DEFAULT_DIRECTORY . '/' . $filename, 'r');
        } catch (FilesystemException $e) {
            Logs::exception($e, 'File ' . $filename . ' cannot be open!', Analog::ERROR);
            $this->addError(
                sprintf(
                    _T('File %1$s cannot be open!'),
                    $filename
                )
            );
            return false;
        }

        $cnt_fields = count($this->fields);

        //check required fields
        $fc = new FieldsConfig(
            zdb: $this->zdb,
            table: Adherent::TABLE,
            defaults: $this->members_fields,
            cats_defaults: $this->members_fields_cats
        );
        $config_required = $fc->getRequired();
        $this->required = [];

        foreach (array_keys($config_required) as $field) {
            if (in_array($field, $this->fields)) {
                $this->required[$field] = $field;
            }
        }

        $member = new Adherent($this->zdb);
        $dfields = [];
        $member->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $row = 0;
        while (
            ($data = fgetcsv( //@phpstan-ignore theCodingMachineSafe.function
                stream: $handle,
                length: 1000,
                separator: self::DEFAULT_SEPARATOR,
                enclosure: self::DEFAULT_QUOTE,
                escape: self::DEFAULT_ESCAPE
            )) !== false
        ) {
            //check fields count
            $count = count($data);
            if ($count != $cnt_fields) {
                $this->addError(
                    sprintf(
                        _T('Fields count mismatch... There should be %1$s fields and there are %2$s (row %3$s)'),
                        (string)$cnt_fields,
                        (string)$count,
                        (string)$row
                    )
                );
                return false;
            }

            if ($row > 0) {
                //header line is the first one. Here comes data
                $col = 0;
                foreach ($data as $column) {
                    $column = trim((string)$column);

                    //check required fields
                    if (
                        in_array($this->fields[$col], $this->required)
                        && empty($column)
                    ) {
                        $this->addError(
                            sprintf(
                                //TRANS: first parameter is a field name, second the row in error
                                _T('Field %1$s is required, but missing in row %2$s'),
                                $this->fields[$col],
                                (string)$row
                            )
                        );
                        return false;
                    }

                    if ($this->isGroupsField($this->fields[$col])) {
                        if ($this->resolveGroups($column, $row) === false) {
                            return false;
                        }
                        $col++;
                        continue;
                    }

                    //check for statuses
                    //if missing, set default one; if not check it does exist
                    if ($this->fields[$col] == Status::PK) {
                        if (empty($column)) {
                            $column = $this->preferences->pref_statut ?? Status::DEFAULT_STATUS;
                        } else {
                            if (!isset($this->statuses)) {
                                //load existing status
                                $this->statuses = $this->status->getList();
                            }
                            if (!isset($this->statuses[(int)$column])) {
                                $this->addError(
                                    sprintf(
                                        //TRANS: parameter is the status
                                        _T('Status %1$s does not exists!'),
                                        $column
                                    )
                                );
                                return false;
                            }
                        }
                    }

                    //check for title
                    if ($this->fields[$col] == 'titre_adh' && !empty($column)) {
                        if (!isset($this->titles)) {
                            //load existing titles
                            $titles = new Titles($this->zdb);
                            $this->titles = $titles->getList();
                        }
                        if (!isset($this->titles[$column])) {
                            $this->addError(
                                sprintf(
                                    //TRANS: parameter is the title
                                    _T('Title %1$s does not exists!'),
                                    $column
                                )
                            );
                            return false;
                        }
                    }

                    //check for email unicity
                    if ($this->fields[$col] == 'email_adh' && !empty($column)) {
                        if (!isset($this->emails)) {
                            //load existing emails
                            $this->emails = Members::getEmails($this->zdb);
                        }
                        if (isset($this->emails[$column])) {
                            $existing = $this->emails[$column];
                            $extra = (
                                $existing == -1
                                ? _T("from another member in import")
                                : sprintf(
                                    //TRANS: parameter is the member identifier
                                    _T('from member %1$s'),
                                    $existing
                                )
                            );
                            $this->addError(
                                sprintf(
                                    //TRANS: first parameter is the email address, second tells where it is already used
                                    _T('Email address %1$s is already used! (%2$s)'),
                                    $column,
                                    $extra
                                )
                            );
                            return false;
                        } else {
                            //add email to list
                            $this->emails[$column] = -1;
                        }
                    }

                    //check for language
                    if ($this->fields[$col] == 'pref_lang') {
                        if (!isset($this->langs)) {
                            //load existing titles
                            /** @var I18n $i18n */
                            global $i18n;
                            $this->langs = $i18n->getArrayList();
                        }
                        if (empty($column)) {
                            $column = $this->preferences->pref_lang;
                        } elseif (!isset($this->langs[$column])) {
                            $this->addError(
                                sprintf(
                                    _T('Unknown lang (%1$s)'),
                                    $column
                                )
                            );
                            return false;
                        }
                    }

                    //passwords
                    if ($this->fields[$col] == 'mdp_adh' && !empty($column)) {
                        $this->fields['mdp_adh2'] = $column;
                    }

                    if (str_starts_with((string)$this->fields[$col], 'dynfield_')) {
                        //dynamic field, keep to check later
                        $dfields[$this->getDynamicFieldKey((string)$this->fields[$col])] = $column;
                    } else {
                        //standard field
                        $member->validate($this->fields[$col], $column, $this->fields);
                    }
                    $errors = $member->getErrors();
                    if (count($errors)) {
                        foreach ($errors as $error) {
                            $this->addError($error);
                        }
                        return false;
                    }

                    $col++;
                }

                //check dynamic fields
                $member->dynamicsValidate($dfields);
                $errors = $member->getErrors();
                if (count($errors)) {
                    foreach ($errors as $error) {
                        $this->addError($error);
                    }
                    return false;
                }
            }

            $row++;
        }
        fclose($handle);

        if ($row <= 1) {
            //no data in file, just headers line
            $this->addError(
                _T("File is empty!")
            );
            return false;
        }

        return true;
    }

    /**
     * Is field one of the groups pseudo fields
     *
     * @param string $field Import model column name
     */
    private function isGroupsField(string $field): bool
    {
        return $field === self::GROUPS_FIELD || $field === self::MANAGED_GROUPS_FIELD;
    }

    /**
     * Resolve groups names from a column
     *
     * @param string $column Column value, groups separated with GROUPS_SEPARATOR
     * @param int    $row    Row number, for error messages
     *
     * @return array<int, string>|false groups full names, indexed by id; false if one cannot be found
     */
    private function resolveGroups(string $column, int $row): array|false
    {
        if (!isset($this->groups)) {
            $this->groups = Groups::getNames($this->zdb);
        }

        $resolved = [];
        foreach (explode(self::GROUPS_SEPARATOR, $column) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $found = Groups::findByName($name, $this->groups);
            if (count($found) === 0) {
                $this->addError(
                    sprintf(
                        //TRANS: first parameter is a group name, second the row in error
                        _T('Group "%1$s" does not exist (row %2$s)'),
                        $name,
                        (string)$row
                    )
                );
                return false;
            }

            if (count($found) > 1) {
                $this->addError(
                    sprintf(
                        //TRANS: first parameter is a group name, second the row in error, third a list of groups full names
                        _T('Several groups are named "%1$s" (row %2$s), use the full name of one of them: %3$s'),
                        $name,
                        (string)$row,
                        implode(', ', $found)
                    )
                );
                return false;
            }

            $resolved += $found;
        }

        return $resolved;
    }

    /**
     * Get the occurrence a dynamic field import column targets
     *
     * A column may name its occurrence (`dynfield_<id>_<n>`); one that does not
     * targets the first occurrence, which keeps existing import models working.
     *
     * @param string $field Import model column name
     */
    private function getDynamicFieldKey(string $field): string
    {
        if (count(explode('_', $field)) < 3) {
            $field .= '_1';
        }

        return $field;
    }

    /**
     * Store members in database
     *
     * @param string $filename CSV filename
     */
    private function storeMembers(string $filename): bool
    {
        $handle = fopen(self::DEFAULT_DIRECTORY . '/' . $filename, 'r');

        $row = 0;

        try {
            $this->zdb->beginTransaction();
            while (
                ($data = fgetcsv( //@phpstan-ignore theCodingMachineSafe.function
                    stream: $handle,
                    length: 1000,
                    separator: self::DEFAULT_SEPARATOR,
                    enclosure: self::DEFAULT_QUOTE,
                    escape: self::DEFAULT_ESCAPE
                )) !== false
            ) {
                if ($row > 0) {
                    $col = 0;
                    $values = [];
                    $groups = [self::GROUPS_FIELD => [], self::MANAGED_GROUPS_FIELD => []];
                    foreach ($data as $column) {
                        if ($this->isGroupsField($this->fields[$col])) {
                            //already checked, cannot fail
                            $groups[$this->fields[$col]] += $this->resolveGroups((string)$column, $row) ?: [];
                            $col++;
                            continue;
                        }

                        if (str_starts_with($this->fields[$col], 'dynfield_')) {
                            //dynamic field, keep to check later
                            $key = $this->getDynamicFieldKey($this->fields[$col]);
                            $values[str_replace('dynfield_', 'info_field_', $key)] = $column;
                            $col++;
                            continue;
                        }

                        $values[$this->fields[$col]] = $column;
                        if ($this->fields[$col] === 'societe_adh') {
                            $values['is_company'] = true;
                        }
                        //check for booleans
                        if (
                            ($this->fields[$col] == 'bool_admin_adh'
                            || $this->fields[$col] == 'bool_exempt_adh'
                            || $this->fields[$col] == 'bool_display_info'
                            || $this->fields[$col] == 'activite_adh')
                            && ($column == null || trim($column) == '')
                        ) {
                            $values[$this->fields[$col]] = 0; //defaults to 0 as in Adherent
                        }

                        if ($this->fields[$col] == Status::PK && empty(trim((string)$column))) {
                            $values[Status::PK] = $this->preferences->pref_statut ?? Status::DEFAULT_STATUS;
                        }

                        if ($this->fields[$col] == 'pref_lang' && empty(trim((string)$column))) {
                            $values[$this->fields[$col]] = $this->preferences->pref_lang;
                        }

                        $col++;
                    }
                    //import member itself
                    $member = new Adherent($this->zdb);
                    $member->setDependencies(
                        $this->preferences,
                        $this->members_fields,
                        $this->history
                    );
                    //check for empty creation date
                    if (isset($values['date_crea_adh']) && trim($values['date_crea_adh']) === '') {
                        unset($values['date_crea_adh']);
                    }
                    if (isset($values['mdp_adh'])) {
                        $values['mdp_adh2'] = $values['mdp_adh'];
                    }

                    $valid = $member->check($values, $this->required, []);
                    if ($valid === true) {
                        if ($this->dryrun === false) {
                            $store = $member->store();
                            if ($store !== true) {
                                $this->addError(
                                    sprintf(
                                        //TRANS: first parameter is row, second member name
                                        _T('An error occurred storing member at row %1$s (%2$s):'),
                                        (string)$row,
                                        $member->sname
                                    )
                                );
                                $this->zdb->rollback();
                                return false;
                            }

                            if (!$this->storeGroups($member, $groups)) {
                                $this->addError(
                                    sprintf(
                                        //TRANS: first parameter is row, second member name
                                        _T('An error occurred attaching member at row %1$s (%2$s) to its groups'),
                                        (string)$row,
                                        $member->sname
                                    )
                                );
                                $this->zdb->rollback();
                                return false;
                            }
                        }
                    } else {
                        $this->addError(
                            sprintf(
                                //TRANS: first parameter is row, second member name
                                _T('An error occurred storing member at row %1$s (%2$s):'),
                                (string)$row,
                                $member->sname
                            )
                        );
                        foreach ($valid as $e) {
                            $this->addError($e);
                        }
                        $this->zdb->rollback();
                        return false;
                    }
                }
                $row++;
            }
            $this->zdb->commit();
            return true;
        } catch (Throwable $e) {
            $this->zdb->rollback();
            Logs::exception(
                $e,
                'Members import from ' . $filename . ' failed, changes have been rolled back',
                Analog::ERROR
            );
            $this->addError($e->getMessage());
        }

        return false;
    }

    /**
     * Attach a newly stored member to groups
     *
     * @param Adherent                          $member Member
     * @param array<string, array<int, string>> $groups Groups full names indexed by id, per pseudo field
     */
    private function storeGroups(Adherent $member, array $groups): bool
    {
        $types = [
            self::GROUPS_FIELD          => false,
            self::MANAGED_GROUPS_FIELD  => true
        ];
        foreach ($types as $field => $manager) {
            if (count($groups[$field]) === 0) {
                continue;
            }

            $list = [];
            foreach ($groups[$field] as $id => $full_name) {
                $list[] = $id . '|' . $full_name;
            }
            //import already runs in a transaction: a nested one would roll back everything on its own
            if (
                !Groups::addMemberToGroups(
                    adh: $member,
                    groups: $list,
                    manager: $manager,
                    transaction: true
                )
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return textual error message
     *
     * @param int $code The error code
     *
     * @return string Localized message
     */
    public function getErrorMessage(int $code): string
    {
        $error = null;
        switch ($code) {
            case self::DATA_IMPORT_ERROR:
                $error = _T("An error occurred while importing members");
                break;
        }

        $error ??= $this->getErrorMessageFromCode($code);

        return $error;
    }
}
