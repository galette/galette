<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\IO;

use Safe\DateTime;
use DI\Attribute\Inject;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Entity\Status;
use Galette\Entity\Adherent;
use Galette\Entity\Group;
use Galette\Repository\DynamicFieldsSet;
use Galette\Repository\Groups;
use Galette\Repository\Titles;
use Galette\Repository\Members;
use Galette\Entity\FieldsConfig;
use Galette\Filters\MembersList;
use Safe\Exceptions\FilesystemException;
use Analog\Analog;
use Galette\Core\Logs;

use function Safe\fclose;
use function Safe\fopen;

/**
 * Members CSV exports
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class MembersCsv extends CsvOut
{
    private readonly string $filename;
    private readonly string $path;

    /**
     * Default constructor
     *
     * @param Db                  $zdb            Db instance
     * @param Login               $login          Login instance
     * @param FieldsConfig        $fields_config  FieldsConfig instance
     * @param array<string,mixed> $members_fields Members fields configuration
     * @param Status              $status         Status entity instance
     */
    public function __construct(
        private readonly Db $zdb,
        private readonly Login $login,
        private readonly FieldsConfig $fields_config,
        #[Inject("members_fields")]
        private readonly array $members_fields,
        private readonly Status $status
    ) {
        $this->filename = __('filtered_memberslist') . '.csv';
        $this->path = self::DEFAULT_DIRECTORY . $this->filename;
        parent::__construct();
    }

    /**
     * Export members CSV
     *
     * @param MembersList $filters Current filters
     */
    public function exportMembers(MembersList $filters): void
    {
        $export_fields = null;
        if (file_exists(GALETTE_CONFIG_PATH . 'local_export_fields.inc.php')) {
            include_once GALETTE_CONFIG_PATH . 'local_export_fields.inc.php';
            //@phpstan-ignore variable.undefined
            $export_fields = $fields;
        }

        // fields visibility
        $fc = $this->fields_config;
        $visibles = $fc->getVisibilities();
        //hack for id_adh and parent_id
        $hacks = ['id_adh', 'parent_id'];
        foreach ($hacks as $hack) {
            if ($visibles[$hack] == FieldsConfig::NOBODY) {
                $visibles[$hack] = FieldsConfig::MANAGER;
            }
        }
        $access_level = $this->login->getAccessLevel();
        $fields = [];
        $labels = [];
        foreach ($this->members_fields as $k => $f) {
            // skip fields blacklisted for export
            if (
                $k === 'mdp_adh'
                || !FieldsConfig::isColumn($f)
                || ($export_fields !== null
                    && (is_array($export_fields) && !in_array($k, $export_fields)))
            ) {
                continue;
            }

            // skip fields according to access control
            if (!FieldsConfig::isGranted((int)$visibles[$k], $access_level)) {
                continue;
            }

            $fields[] = $k;
            $labels[] = $f['label'];
        }

        //groups pseudo fields, same names and format as import
        $groups_fields = [];
        $groups_columns = [
            CsvIn::GROUPS_FIELD         => [Group::GROUPSUSERS_TABLE, _T('Groups')],
            CsvIn::MANAGED_GROUPS_FIELD => [Group::GROUPSMANAGERS_TABLE, _T('Managed groups')]
        ];
        foreach ($groups_columns as $k => [$table, $label]) {
            if ($export_fields !== null && is_array($export_fields) && !in_array($k, $export_fields)) {
                continue;
            }
            $groups_fields[$k] = $table;
            $labels[] = $label;
        }

        //dynamic fields the current user can see, same names as import
        $dynamic_fields = [];
        $fieldset = new DynamicFieldsSet($this->zdb, $this->login);
        foreach ($fieldset->getList('adh') as $field) {
            if (
                !$field->hasData()
                || ($export_fields !== null && is_array($export_fields)
                    && !in_array('dynfield_' . $field->getId(), $export_fields))
            ) {
                continue;
            }
            $dynamic_fields[(int)$field->getId()] = $field;
        }

        $members = new Members($filters);
        $members_list = $members->getArrayList(
            ids: $filters->selected,
            orderby: null,
            with_photos: false,
            as_members: false,
            fields: $fields,
            export: true
        );

        $ids = is_array($members_list) ? array_map(fn($member) => (int)$member->{Adherent::PK}, $members_list) : [];
        $members_groups = $this->getMembersGroups($ids, $groups_fields);
        $members_dynamics = $fieldset->getValuesFor('adh', $ids, array_keys($dynamic_fields));

        //one column per occurrence of repeatable fields, as many as the exported members use
        $dynamic_columns = [];
        foreach ($dynamic_fields as $fid => $field) {
            $label = __($field->getName());
            if (!$field->isRepeatable()) {
                $dynamic_columns['dynfield_' . $fid] = [$field, 0];
                $labels[] = $label;
                continue;
            }

            $occurrences = max([1, ...array_map(count(...), $members_dynamics[$fid] ?? [])]);
            if ($field->getRepeat() > 0) {
                $occurrences = min($occurrences, $field->getRepeat());
            }
            for ($i = 1; $i <= $occurrences; $i++) {
                $dynamic_columns['dynfield_' . $fid . '_' . $i] = [$field, $i - 1];
                //TRANS: %1$s is a dynamic field name, %2$s the occurrence number
                $labels[] = sprintf(_T('%1$s (occurrence %2$s)'), $label, (string)$i);
            }
        }

        $statuses = $this->status->getList();

        $t = new Titles($this->zdb);
        $titles = $t->getList();

        foreach ($members_list as &$member) {
            if (isset($member->id_statut)) {
                //add textual status
                $member->id_statut = $statuses[$member->id_statut];
            }

            if (isset($member->titre_adh)) {
                //add textuel title
                $member->titre_adh = $titles[$member->titre_adh]->short;
            }

            //handle dates
            if (isset($member->date_crea_adh)) {
                if (
                    $member->date_crea_adh != ''
                    && $member->date_crea_adh != '1901-01-01'
                ) {
                    $dcrea = new DateTime($member->date_crea_adh);
                    $member->date_crea_adh = $dcrea->format(__("Y-m-d"));
                } else {
                    $member->date_crea_adh = '';
                }
            }

            if (isset($member->date_modif_adh)) {
                if (
                    $member->date_modif_adh != ''
                    && $member->date_modif_adh != '1901-01-01'
                ) {
                    $dmodif = new DateTime($member->date_modif_adh);
                    $member->date_modif_adh = $dmodif->format(__("Y-m-d"));
                } else {
                    $member->date_modif_adh = '';
                }
            }

            if (isset($member->date_echeance)) {
                if (
                    $member->date_echeance != ''
                    && $member->date_echeance != '1901-01-01'
                ) {
                    $dech = new DateTime($member->date_echeance);
                    $member->date_echeance = $dech->format(__("Y-m-d"));
                } else {
                    $member->date_echeance = '';
                }
            }

            if (isset($member->ddn_adh)) {
                if (
                    $member->ddn_adh != ''
                    && $member->ddn_adh != '1901-01-01'
                ) {
                    $ddn = new DateTime($member->ddn_adh);
                    $member->ddn_adh = $ddn->format(__("Y-m-d"));
                } else {
                    $member->ddn_adh = '';
                }
            }

            if (isset($member->sexe_adh)) {
                //handle gender
                switch ($member->sexe_adh) {
                    case Adherent::MAN:
                        $member->sexe_adh = _T("Man");
                        break;
                    case Adherent::WOMAN:
                        $member->sexe_adh = _T("Woman");
                        break;
                    case Adherent::NC:
                        $member->sexe_adh = _T("Unspecified");
                        break;
                }
            }

            //handle booleans
            if (isset($member->activite_adh)) {
                $member->activite_adh
                    = ($member->activite_adh) ? _T("Yes") : _T("No");
            }
            if (isset($member->bool_admin_adh)) {
                $member->bool_admin_adh
                    = ($member->bool_admin_adh) ? _T("Yes") : _T("No");
            }
            if (isset($member->bool_exempt_adh)) {
                $member->bool_exempt_adh
                    = ($member->bool_exempt_adh) ? _T("Yes") : _T("No");
            }
            if (isset($member->bool_display_info)) {
                $member->bool_display_info
                    = ($member->bool_display_info) ? _T("Yes") : _T("No");
            }

            foreach (array_keys($groups_fields) as $k) {
                $member->$k = implode(
                    CsvIn::GROUPS_SEPARATOR,
                    $members_groups[$k][(int)$member->{Adherent::PK}] ?? []
                );
            }

            foreach ($dynamic_columns as $k => [$field, $pos]) {
                $value = $members_dynamics[(int)$field->getId()][(int)$member->{Adherent::PK}][$pos] ?? null;
                $member->$k = $value === null ? '' : DynamicFieldsSet::getTextValue($field, $value);
            }
        }

        try {
            $fp = fopen($this->path, 'w');
            $this->export(
                rs: $members_list,
                separator: self::DEFAULT_SEPARATOR,
                quote: self::DEFAULT_QUOTE,
                titles: $labels,
                file: $fp
            );
            fclose($fp);
        } catch (FilesystemException $e) {
            Logs::exception($e, 'Unable to write members CSV export to ' . $this->path, Analog::ERROR);
        }
    }

    /**
     * Get groups full names of members, limited to groups current user manages
     * if they are not admin nor staff
     *
     * @param array<int>            $ids    Members ids
     * @param array<string, string> $fields Groups pseudo fields and their table
     *
     * @return array<string, array<int, array<string>>> full names per field and member id
     */
    private function getMembersGroups(array $ids, array $fields): array
    {
        $members_groups = [];
        if (count($ids) === 0 || count($fields) === 0) {
            return $members_groups;
        }

        $managed = null;
        if (!$this->login->isAdmin() && !$this->login->isStaff()) {
            $managed = array_map(
                fn($group) => $group instanceof Group ? (int)$group->getId() : (int)$group,
                $this->login->getManagedGroups()
            );
            if (count($managed) === 0) {
                return $members_groups;
            }
        }

        $names = Groups::getNames($this->zdb);
        foreach ($fields as $k => $table) {
            $select = $this->zdb->select($table);
            $select->columns([Adherent::PK, Group::PK]);
            $select->where->in(Adherent::PK, $ids);
            if ($managed !== null) {
                $select->where->in(Group::PK, $managed);
            }

            foreach ($this->zdb->execute($select) as $row) {
                $gid = (int)$row->{Group::PK};
                if (isset($names[$gid])) {
                    $members_groups[$k][(int)$row->{Adherent::PK}][] = $names[$gid]['full_name'];
                }
            }
            foreach (array_keys($members_groups[$k] ?? []) as $id) {
                sort($members_groups[$k][$id]);
            }
        }

        return $members_groups;
    }

    /**
     * Get file path on disk
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get file name
     */
    public function getFileName(): string
    {
        return $this->filename;
    }
}
