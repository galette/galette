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

namespace Galette\Core\test\units;

use atoum;
use PHPUnit\Framework\TestCase;

/**
 * Update tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Install extends TestCase
{
    private \Galette\Core\Db $zdb;
    /** @var array<string> */
    private array $flash_data;
    private \Slim\Flash\Messages $flash;
    private \DI\Container $container;
    private string $latest_prefix = 'latest_galette_';
    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        setlocale(LC_ALL, 'en_US');

        $flash_data = [];
        $this->flash_data = &$flash_data;
        $this->flash = new \Slim\Flash\Messages($flash_data);

        $gapp =  new \Galette\Core\SlimApp();
        $app = $gapp->getApp();
        $plugins = new \Galette\Core\Plugins();
        require GALETTE_BASE_PATH . '/includes/dependencies.php';
        $container = $app->getContainer();
        $_SERVER['HTTP_HOST'] = '';

        $container->set('flash', $this->flash);
        $container->set(Slim\Flash\Messages::class, $this->flash);

        $this->container = $container;

        $this->zdb = $container->get('zdb');
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        if (TYPE_DB === 'mysql') {
            $this->assertSame([], $this->zdb->getWarnings());
        }
    }

    /**
     * Test if current database version is supported
     *
     * @return void
     */
    public function testDbSupport(): void
    {
        $this->assertTrue($this->zdb->isEngineSUpported());
    }

    /**
     * Test updates
     *
     * @return void
     */
    public function testUpdates(): void
    {
        $install = new \Galette\Core\Install();
        $update_scripts = \Galette\Core\Install::getUpdateScripts(
            GALETTE_BASE_PATH . '/install',
            $this->zdb->type_db,
            '0.6'
        );
        $this->assertGreaterThan(5, count($update_scripts));

        $install->setMode(\Galette\Core\Install::UPDATE);
        $errors = [];
        $install->setDbType($this->zdb->type_db, $errors);
        $this->assertSame([], $errors);

        $install->setInstalledVersion('0.60');
        $install->setTablesPrefix(PREFIX_DB);
        $exec = $install->executeScripts($this->zdb, GALETTE_BASE_PATH . '/install');

        $report = $install->getInitializationReport();
        foreach ($report as $entry) {
            $this->assertTrue(
                $entry['res'],
                ($entry['debug'] ?? '') . "\n" . ($entry['query'] ?? '')
            );
        }

        $this->assertTrue($exec);
        $this->assertSame(GALETTE_DB_VERSION, $this->zdb->getDbVersion());
    }

    /**
     * Test updated database schema against fresh installed one
     *
     * @return void
     */
    public function testUpdatedDatabase(): void
    {
        // Last database version is installed with `latest_galette_` prefix
        // Update database uses `galette_` prefix. Let's compare those two.

        $latest_prefix = $this->latest_prefix;
        $latest_db = new \Galette\Core\Db();
        $latest_metadata = \Laminas\Db\Metadata\Source\Factory::createSourceFromAdapter($latest_db->db);
        $latest_tables = $latest_db->getTables($latest_prefix);

        $db = new \Galette\Core\Db();
        $metadata = \Laminas\Db\Metadata\Source\Factory::createSourceFromAdapter($db->db);
        $tables = $db->getTables();

        //tables order does not matter
        sort($latest_tables);
        sort($tables);

        //make sure all tables are present
        $this->assertEquals(
            array_map(
                function ($table) use ($latest_prefix) {
                    //table prefix differs
                    return str_replace($latest_prefix, PREFIX_DB, $table);
                },
                $latest_tables
            ),
            $tables
        );

        foreach ($latest_tables as $latest_table_name) {
            $latest_table = $latest_metadata->getTable($latest_table_name);
            $latest_columns = $latest_table->getColumns();

            //table prefix differs
            $table_name = str_replace($latest_prefix, PREFIX_DB, $latest_table_name);

            foreach ($latest_columns as $latest_column) {
                try {
                    $column = $metadata->getColumn($latest_column->getName(), $table_name);
                } catch (\Exception $e) {
                    $this->fail($latest_column->getName() . ' | ' . $e->getMessage());
                }

                //table name differs
                $latest_column->setTableName($table_name);
                if ($default = $column->getColumnDefault()) {
                    $latest_column->setColumnDefault(str_replace($latest_prefix, PREFIX_DB, $default));
                }
                //position does not matter
                $column->setOrdinalPosition($latest_column->getOrdinalPosition());

                //Q&D fixes... :'(
                if (
                    !$db->isPostgres() &&
                    $table_name === 'galette_cotisations' &&
                    (
                        $latest_column->getName() === 'id_type_cotis' ||
                        $latest_column->getName() === 'type_paiement_cotis'
                    )
                ) {
                    //dunno why default is not correct, 1.15-mysql upgrade does contain the correct statement.
                    $column->setColumnDefault(null);
                }

                $this->assertEquals(
                    $latest_column,
                    $column,
                    sprintf(
                        'Column %s.%s differs from latest version',
                        $table_name,
                        $latest_column->getName()
                    )
                );
            }

            //check constraints
            $latest_constraints = $latest_metadata->getConstraints($latest_table_name);
            $constraints = $metadata->getConstraints($table_name);

            $this->assertSame(
                count($latest_constraints),
                count($constraints),
                sprintf('Constraints count differs on %s!', $table_name) . print_r($constraints, true) . print_r($latest_constraints, true)
            );

            //constraint naming in mysql is not explicit, so we can't rely on it
            $install_constraints = [];
            foreach ($latest_constraints as $latest_constraint) {
                //not null postgresql constraints name may change - nullable is checked from DB column anyway.
                if ($this->zdb->isPostgres() && str_ends_with($latest_constraint->getName(), '_not_null')) {
                    continue;
                }

                $key = $this->buildConstraintKey($latest_constraint);
                $this->assertArrayNotHasKey(
                    $key,
                    $install_constraints,
                    sprintf(
                        'Constraint %s already exists :/',
                        $key
                    )
                );
                $install_constraints[$key] = $latest_constraint;
            }

            $fail_mapping = [];
            if ($this->zdb->isPostgres()) {
                //query to retrieve FKEY from information schema fails on updated database (while everything is OK looking at "\d table" command... :/
                $fail_mapping = [
                    'FOREIGN KEY-galette_adherents-id_statut--' => 'FOREIGN KEY-galette_adherents-id_statut-galette_statuts-id_statut',
                    'FOREIGN KEY-galette_adherents-parent_id--' => 'FOREIGN KEY-galette_adherents-parent_id-galette_adherents-id_adh',
                    'FOREIGN KEY-galette_cotisations-id_adh--' => 'FOREIGN KEY-galette_cotisations-id_adh-galette_adherents-id_adh',
                    'FOREIGN KEY-galette_cotisations-id_type_cotis--' => 'FOREIGN KEY-galette_cotisations-id_type_cotis-galette_types_cotisation-id_type_cotis',
                    'FOREIGN KEY-galette_cotisations-trans_id--' => 'FOREIGN KEY-galette_cotisations-trans_id-galette_transactions-trans_id',
                    'FOREIGN KEY-galette_dynamic_fields-field_id--' => 'FOREIGN KEY-galette_dynamic_fields-field_id-galette_field_types-field_id',
                    'FOREIGN KEY-galette_groups_managers-id_adh--' => 'FOREIGN KEY-galette_groups_managers-id_adh-galette_adherents-id_adh',
                    'FOREIGN KEY-galette_groups_members-id_adh--' => 'FOREIGN KEY-galette_groups_members-id_adh-galette_adherents-id_adh',
                    'FOREIGN KEY-galette_mailing_history-mailing_sender--' => 'FOREIGN KEY-galette_mailing_history-mailing_sender-galette_adherents-id_adh',
                    'FOREIGN KEY-galette_payments_schedules-id_cotis--' => 'FOREIGN KEY-galette_payments_schedules-id_cotis-galette_cotisations-id_cotis',
                    'FOREIGN KEY-galette_reminders-reminder_dest--' => 'FOREIGN KEY-galette_reminders-reminder_dest-galette_adherents-id_adh',
                    'FOREIGN KEY-galette_searches-id_adh--' => 'FOREIGN KEY-galette_searches-id_adh-galette_adherents-id_adh',
                    'FOREIGN KEY-galette_socials-id_adh--' => 'FOREIGN KEY-galette_socials-id_adh-galette_adherents-id_adh',
                    'FOREIGN KEY-galette_tmppasswds-id_adh--' => 'FOREIGN KEY-galette_tmppasswds-id_adh-galette_adherents-id_adh',
                    'FOREIGN KEY-galette_transactions-id_adh--' => 'FOREIGN KEY-galette_transactions-id_adh-galette_adherents-id_adh'
                ];
            }
            $rules_fails = [];
            if (!$this->zdb->isPostgres()) {
                $rules_fails = [
                    'FOREIGN KEY-galette_fields_config-id_field_category-galette_fields_categories-id_field_category', // galette_fields_config_ibfk_1
                    'FOREIGN KEY-galette_groups-parent_group-galette_groups-id_group', // galette_groups_ibfk_1
                    'FOREIGN KEY-galette_groups_managers-id_group-galette_groups-id_group', // galette_groups_managers_ibfk_2
                    'FOREIGN KEY-galette_groups_members-id_group-galette_groups-id_group' // galette_groups_members_ibfk_2
                ];
            }
            foreach ($constraints as $constraint) {
                if ($this->zdb->isPostgres() && str_ends_with($constraint->getName(), '_not_null')) {
                    continue;
                }
                $key = $this->buildConstraintKey($constraint);
                if (isset($fail_mapping[$key])) {
                    $key = $fail_mapping[$key];
                }
                $this->assertArrayHasKey(
                    $key,
                    $install_constraints,
                    sprintf(
                        'Constraint %s not found :/',
                        $key
                    )
                );
                $latest_constraint = $install_constraints[$key];
                unset($install_constraints[$key]);

                $this->assertSame($constraint->getType(), $latest_constraint->getType());
                $this->assertSame($constraint->getColumns(), $latest_constraint->getColumns());

                if (!in_array($key, $fail_mapping)) {
                    $this->assertSame(
                        $constraint->getReferencedTableName() ?? '',
                        str_replace($latest_prefix, PREFIX_DB, $latest_constraint->getReferencedTableName() ?? ''),
                        sprintf(
                            'Constraint %1$s incorrect',
                            $key
                        )
                    );
                    $this->assertSame($constraint->getReferencedColumns(), $latest_constraint->getReferencedColumns());
                }

                if (in_array($key, $rules_fails)) {
                    //with mysql, some constraints created in the same update process are not yet available
                    //(not only in information_schema) when upgrading to 1.20... Key would be created twice.
                    //see commented lines in upgrade-to-1.20-mysql.sql
                    continue;
                }

                $this->checkFkeysRules($latest_constraint, $constraint, 'delete');
                $this->checkFkeysRules($latest_constraint, $constraint, 'update');
            }
        }
    }

    /**
     * Build constraint key since we can not rely on mysql ones
     *
     * @param \Laminas\Db\Metadata\Object\ConstraintObject $constraint Constraint from which key must be built
     *
     * @return string
     */
    private function buildConstraintKey(\Laminas\Db\Metadata\Object\ConstraintObject $constraint): string
    {
        if ($constraint->isPrimaryKey() || $constraint->isUnique()) {
            return sprintf(
                '%s-%s-%s',
                $constraint->getType(),
                str_replace($this->latest_prefix, PREFIX_DB, $constraint->getTableName()),
                implode('|', $constraint->getColumns())
            );
        }
        if ($constraint->isForeignKey()) {
            return sprintf(
                '%s-%s-%s-%s-%s',
                $constraint->getType(),
                str_replace($this->latest_prefix, PREFIX_DB, $constraint->getTableName()),
                implode('|', $constraint->getColumns()),
                str_replace($this->latest_prefix, PREFIX_DB, $constraint->getReferencedTableName() ?? ''),
                implode('|', $constraint->getReferencedColumns())
            );
        }

        throw new \RuntimeException('Unsupported constraint type ' . $constraint->getType());
    }

    /**
     * Check forgeign keys rules
     *
     * @param \Laminas\Db\Metadata\Object\ConstraintObject $latest_constraint Constraint from installed database
     * @param \Laminas\Db\Metadata\Object\ConstraintObject $constraint        Constraint from updated database
     * @param string                                       $rule_type         Rule type (either 'update' or 'delete')
     *
     * @return void
     */
    private function checkFkeysRules(
        \Laminas\Db\Metadata\Object\ConstraintObject $latest_constraint,
        \Laminas\Db\Metadata\Object\ConstraintObject $constraint,
        string $rule_type
    ): void {
        $method = 'get' . ucfirst($rule_type) . 'Rule';
        $rule = $constraint->$method();
        $latest_rule = $latest_constraint->$method();

        if (!$this->zdb->isPostgres() && $rule === \Laminas\Db\Metadata\Object\ConstraintKeyObject::FK_RESTRICT) {
            $rule = \Laminas\Db\Metadata\Object\ConstraintKeyObject::FK_NO_ACTION;
        }
        if (!$this->zdb->isPostgres() && $latest_rule === \Laminas\Db\Metadata\Object\ConstraintKeyObject::FK_RESTRICT) {
            $latest_rule = \Laminas\Db\Metadata\Object\ConstraintKeyObject::FK_NO_ACTION;
        }

        $this->assertSame(
            $rule,
            $latest_rule,
            sprintf(
                '%s constraint %s differs: %s - %s',
                $rule_type,
                $this->buildConstraintKey($constraint),
                $constraint->$method(),
                $latest_constraint->$method()
            )
        );
        $this->assertSame($constraint->getMatchOption(), $latest_constraint->getMatchOption());
        $this->assertSame($constraint->getCheckClause(), $latest_constraint->getCheckClause());
    }
}
