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

namespace Galette\IO;

use DateTime;
use Analog\Analog;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Core\Authentication;
use Galette\Entity\Status;
use Galette\Entity\Adherent;
use Galette\Repository\Titles;
use Galette\Repository\Members;
use Galette\Entity\FieldsConfig;
use Galette\Filters\MembersList;

/**
 * Members CSV exports
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class MembersCsv extends CsvOut
{
    private string $filename;
    private string $path;
    private Db $zdb;
    private Login $login;
    /** @var array<string,mixed> */
    private array $members_fields;
    private FieldsConfig $fields_config;

    /**
     * Default constructor
     *
     * @param Db                  $zdb            Db instance
     * @param Login               $login          Login instance
     * @param array<string,mixed> $members_fields Members fields
     * @param FieldsConfig        $fields_config  Fields configuration
     */
    public function __construct(Db $zdb, Login $login, array $members_fields, FieldsConfig $fields_config)
    {
        $this->filename = __('filtered_memberslist') . '.csv';
        $this->path = self::DEFAULT_DIRECTORY . $this->filename;
        $this->zdb = $zdb;
        $this->login = $login;
        $this->members_fields = $members_fields;
        $this->fields_config = $fields_config;
        parent::__construct();
    }

    /**
     * Export members CSV
     *
     * @param MembersList $filters Current filters
     *
     * @return void
     */
    public function exportMembers(MembersList $filters): void
    {
        $export_fields = null;
        if (file_exists(GALETTE_CONFIG_PATH . 'local_export_fields.inc.php')) {
            include_once GALETTE_CONFIG_PATH . 'local_export_fields.inc.php';
            //@phpstan-ignore-next-line
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
                $k === 'mdp_adh' ||
                ($export_fields !== null &&
                    (is_array($export_fields) && !in_array($k, $export_fields)))
            ) {
                continue;
            }

            // skip fields according to access control
            if (
                $visibles[$k] == FieldsConfig::NOBODY ||
                ($visibles[$k] == FieldsConfig::ADMIN &&
                    $access_level < Authentication::ACCESS_ADMIN) ||
                ($visibles[$k] == FieldsConfig::STAFF &&
                    $access_level < Authentication::ACCESS_STAFF) ||
                ($visibles[$k] == FieldsConfig::MANAGER &&
                    $access_level < Authentication::ACCESS_MANAGER)
            ) {
                continue;
            }

            $fields[] = $k;
            $labels[] = $f['label'];
        }

        $members = new Members($filters);
        $members_list = $members->getArrayList(
            $filters->selected,
            null,
            false,
            false,
            $fields,
            true
        );

        $s = new Status($this->zdb);
        $statuses = $s->getList();

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
        }

        $fp = fopen($this->path, 'w');
        if ($fp) {
            $this->export(
                $members_list,
                self::DEFAULT_SEPARATOR,
                self::DEFAULT_QUOTE,
                $labels,
                $fp
            );
            fclose($fp);
        }
    }

    /**
     * Get file path on disk
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get file name
     *
     * @return string
     */
    public function getFileName(): string
    {
        return $this->filename;
    }
}
