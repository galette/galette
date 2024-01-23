<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette installation
 *
 * PHP version 5
 *
 * Copyright © 2013-2024 The Galette Team
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
 *
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.8 - 2013-01-09
 */

namespace Galette\Core;

use Throwable;
use Analog\Analog;
use Laminas\Db\Adapter\Adapter;

/**
 * Galette installation
 *
 * @category  Core
 * @name      Install
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.8 - 2013-01-09
 */
class Install
{
    public const STEP_CHECK = 0;
    public const STEP_TYPE = 1;
    public const STEP_DB = 2;
    public const STEP_DB_CHECKS = 3;
    public const STEP_VERSION = 4; //only for update
    public const STEP_DB_UPGRADE = 5;
    public const STEP_DB_INSTALL = 6;
    public const STEP_ADMIN = 7;
    public const STEP_TELEMETRY = 8;
    public const STEP_GALETTE_INIT = 9;
    public const STEP_END = 10;

    public const INSTALL = 'i';
    public const UPDATE = 'u';

    //db version/galette version mapper
    /** @var array<string, string> */
    private array $versions_mapper = array(
        '0.700' => '0.70',
        '0.701' => '0.71',
        '0.702' => '0.74',
        '0.703' => '0.75',
        '0.704' => '0.76'
    );

    protected int $_step;
    private ?string $_mode;
    private ?string $_installed_version;

    private string $_db_type;
    private string $_db_host;
    private string $_db_port;
    private string $_db_name;
    private string $_db_user;
    private string $_db_pass;
    private ?string $_db_prefix;

    private bool $_db_connected;
    /** @var array<string, string> */
    private array $_report;

    private string $_admin_login;
    private string $_admin_pass;

    private bool $_error;

    /**
     * Main constructor
     */
    public function __construct()
    {
        $this->_step = self::STEP_CHECK;
        $this->_mode = null;
        $this->_db_connected = false;
        $this->_db_prefix = null;
    }

    /**
     * Return current step title
     *
     * @return string
     */
    public function getStepTitle(): string
    {
        $step_title = null;
        switch ($this->_step) {
            case self::STEP_CHECK:
                $step_title = _T("Checks");
                break;
            case self::STEP_TYPE:
                $step_title = _T("Installation mode");
                break;
            case self::STEP_DB:
                $step_title = _T("Database");
                break;
            case self::STEP_DB_CHECKS:
                $step_title = _T("Database access and permissions");
                break;
            case self::STEP_VERSION:
                $step_title = _T("Previous version selection");
                break;
            case self::STEP_DB_UPGRADE:
                $step_title = _T("Datapase upgrade");
                break;
            case self::STEP_DB_INSTALL:
                $step_title = _T("Tables Creation");
                break;
            case self::STEP_ADMIN:
                $step_title = _T("Admin parameters");
                break;
            case self::STEP_TELEMETRY:
                $step_title = _T("Telemetry");
                break;
            case self::STEP_GALETTE_INIT:
                $step_title = _T("Galette initialization");
                break;
            case self::STEP_END:
                $step_title = _T("End!");
                break;
        }
        return $step_title;
    }

    /**
     * HTML validation image
     *
     * @param bool $arg Argument
     *
     * @return string html string
     */
    public function getValidationImage(bool $arg): string
    {
        $img_name = ($arg === true) ? 'green check' : 'red times';
        $alt = ($arg === true) ? _T("Ok") : _T("Ko");
        $img = '<i class="ui ' . $img_name . ' icon" aria-hidden="true"></i><span class="visually-hidden">' . $alt . '</span>';
        return $img;
    }

    /**
     * Get current mode
     *
     * @return string
     */
    public function getMode(): ?string
    {
        return $this->_mode;
    }

    /**
     * Are we installing?
     *
     * @return boolean
     */
    public function isInstall(): bool
    {
        return $this->_mode === self::INSTALL;
    }

    /**
     * Are we upgrading?
     *
     * @return boolean
     */
    public function isUpgrade(): bool
    {
        return $this->_mode === self::UPDATE;
    }

    /**
     * Set installation mode
     *
     * @param string $mode Requested mode
     *
     * @return self
     */
    public function setMode(string $mode): self
    {
        if ($mode === self::INSTALL || $mode === self::UPDATE) {
            $this->_mode = $mode;
        } else {
            throw new \UnexpectedValueException('Unknown mode "' . $mode . '"');
        }

        return $this;
    }

    /**
     * Go back to previous step
     *
     * @return void
     */
    public function atPreviousStep(): void
    {
        if ($this->_step > 0) {
            if (
                $this->_step - 1 !== self::STEP_DB_INSTALL
                && $this->_step !== self::STEP_END
            ) {
                if ($this->_step === self::STEP_DB_INSTALL) {
                    $this->_step = self::STEP_DB_CHECKS;
                } else {
                    if ($this->_step === self::STEP_DB_UPGRADE) {
                        $this->setInstalledVersion(null);
                    }
                    $this->_step = $this->_step - 1;
                }
            } else {
                $msg = null;
                if ($this->_step === self::STEP_END) {
                    $msg = 'Ok man, install is finished already!';
                } else {
                    $msg = 'It is forbidden to rerun database install!';
                }
                Analog::log($msg, Analog::WARNING);
            }
        }
    }

    /**
     * Are we at check step?
     *
     * @return boolean
     */
    public function isCheckStep(): bool
    {
        return $this->_step === self::STEP_CHECK;
    }

    /**
     * Set step to type of installation
     *
     * @return void
     */
    public function atTypeStep(): void
    {
        $this->_step = self::STEP_TYPE;
    }

    /**
     * Are we at type step?
     *
     * @return boolean
     */
    public function isTypeStep(): bool
    {
        return $this->_step === self::STEP_TYPE;
    }

    /**
     * Set step to database information
     *
     * @return void
     */
    public function atDbStep(): void
    {
        $this->_step = self::STEP_DB;
    }

    /**
     * Are we at database step?
     *
     * @return boolean
     */
    public function isDbStep(): bool
    {
        return $this->_step === self::STEP_DB;
    }

    /**
     * Is DB step passed?
     *
     * @return boolean
     */
    public function postCheckDb(): bool
    {
        return $this->_step >= self::STEP_DB_CHECKS;
    }

    /**
     * Set database type
     *
     * @param string             $type Database type
     * @param array<int, string> $errs Errors array
     *
     * @return self
     */
    public function setDbType(string $type, array &$errs): self
    {
        switch ($type) {
            case Db::MYSQL:
            case Db::PGSQL:
                $this->_db_type = $type;
                break;
            default:
                $errs[] = _T("Database type unknown");
        }
        return $this;
    }

    /**
     * Get database type
     *
     * @return string
     */
    public function getDbType(): string
    {
        return $this->_db_type;
    }

    /**
     * Set connection information
     *
     * @param string  $host Database host
     * @param string  $port Database port
     * @param string  $name Database name
     * @param string  $user Database username
     * @param ?string $pass Database user's password
     *
     * @return void
     */
    public function setDsn(string $host, string $port, string $name, string $user, ?string $pass): void
    {
        $this->_db_host = $host;
        $this->_db_port = $port;
        $this->_db_name = $name;
        $this->_db_user = $user;
        $this->_db_pass = $pass;
    }

    /**
     * Set tables prefix
     *
     * @param string $prefix Prefix
     *
     * @return self
     */
    public function setTablesPrefix(string $prefix): self
    {
        $this->_db_prefix = $prefix;
        return $this;
    }

    /**
     * Retrieve database host
     *
     * @return string
     */
    public function getDbHost(): string
    {
        return $this->_db_host;
    }

    /**
     * Retrieve database port
     *
     * @return string
     */
    public function getDbPort(): string
    {
        return $this->_db_port;
    }

    /**
     * Retrieve database name
     *
     * @return string
     */
    public function getDbName(): string
    {
        return $this->_db_name;
    }

    /**
     * Retrieve database user
     *
     * @return string
     */
    public function getDbUser(): string
    {
        return $this->_db_user;
    }

    /**
     * Retrieve database password
     *
     * @return string
     */
    public function getDbPass(): string
    {
        return $this->_db_pass;
    }

    /**
     * Retrieve tables prefix
     *
     * @return string
     */
    public function getTablesPrefix(): string
    {
        return $this->_db_prefix;
    }

    /**
     * Set step to database checks
     *
     * @return void
     */
    public function atDbCheckStep(): void
    {
        $this->_step = self::STEP_DB_CHECKS;
    }

    /**
     * Are we at database check step?
     *
     * @return boolean
     */
    public function isDbCheckStep(): bool
    {
        return $this->_step === self::STEP_DB_CHECKS;
    }

    /**
     * Test database connection
     *
     * @return boolean
     *
     * @throws Throwable
     */
    public function testDbConnexion(): bool
    {
        return Db::testConnectivity(
            $this->_db_type,
            $this->_db_user,
            $this->_db_pass,
            $this->_db_host,
            $this->_db_port,
            $this->_db_name
        );
    }

    /**
     * Is database connexion ok?
     *
     * @return boolean
     */
    public function isDbConnected(): bool
    {
        return $this->_db_connected;
    }

    /**
     * Set step to version selection
     *
     * @return void
     */
    public function atVersionSelection(): void
    {
        $this->_step = self::STEP_VERSION;
    }

    /**
     * Are we at version selection step?
     *
     * @return boolean
     */
    public function isVersionSelectionStep(): bool
    {
        return $this->_step === self::STEP_VERSION;
    }

    /**
     * Set step to database installation
     *
     * @return void
     */
    public function atDbInstallStep(): void
    {
        $this->_step = self::STEP_DB_INSTALL;
    }

    /**
     * Are we at db installation step?
     *
     * @return boolean
     */
    public function isDbinstallStep(): bool
    {
        return $this->_step === self::STEP_DB_INSTALL;
    }

    /**
     * Set step to database upgrade
     *
     * @return void
     */
    public function atDbUpgradeStep(): void
    {
        $this->_step = self::STEP_DB_UPGRADE;
    }

    /**
     * Are we at db upgrade step?
     *
     * @return boolean
     */
    public function isDbUpgradeStep(): bool
    {
        return $this->_step === self::STEP_DB_UPGRADE;
    }


    /**
     * Install/Update SQL scripts
     *
     * @param ?string $path Path to scripts (defaults to core scripts)
     *
     * @return array<string, string>
     */
    public function getScripts(string $path = null): array
    {
        if ($path === null) {
            $path = GALETTE_ROOT . '/install';
        }
        $update_scripts = array();

        if ($this->isUpgrade()) {
            $update_scripts = self::getUpdateScripts(
                $path,
                $this->_db_type,
                $this->_installed_version
            );
        } else {
            $update_scripts['current'] = $this->_db_type . '.sql';
        }

        return $update_scripts;
    }

    /**
     * List updates scripts from given path
     *
     * @param string  $path    Scripts path
     * @param string  $db_type Database type
     * @param ?string $version Previous version, defaults to null
     *
     * @return array<string, string>  If a previous version is provided, update scripts
     *                file path from this one to the latest will be returned.
     *                If no previous version is provided, that will return all
     *                updates versions known.
     */
    public static function getUpdateScripts(
        string $path,
        string $db_type = 'mysql',
        string $version = null
    ): array {
        $dh = opendir($path . '/scripts');
        $php_update_scripts = array();
        $sql_update_scripts = array();
        $update_scripts = [];
        if ($dh !== false) {
            while (($file = readdir($dh)) !== false) {
                if (preg_match("/upgrade-to-(.*).php/", $file, $ver)) {
                    if ($version === null) {
                        $php_update_scripts[$ver[1]] = $ver[1];
                    } else {
                        if ($version < $ver[1]) {
                            $php_update_scripts[$ver[1]] = $file;
                        }
                    }
                }
                if (
                    preg_match(
                        "/upgrade-to-(.*)-" . $db_type . ".sql/",
                        $file,
                        $ver
                    )
                ) {
                    if ($version === null) {
                        $sql_update_scripts[$ver[1]] = $ver[1];
                    } else {
                        if ($version < $ver[1]) {
                            $sql_update_scripts[$ver[1]] = $file;
                        }
                    }
                }
            }
            $update_scripts = array_merge($sql_update_scripts, $php_update_scripts);
            closedir($dh);
            ksort($update_scripts);
        }
        return $update_scripts;
    }

    /**
     * Execute SQL scripts
     *
     * @param Db      $zdb   Database instance
     * @param ?string $spath Path to scripts
     *
     * @return bool
     */
    public function executeScripts(Db $zdb, string $spath = null): bool
    {
        $fatal_error = false;
        $update_scripts = $this->getScripts($spath);
        $this->_report = array();
        $scripts_path = ($spath ?? GALETTE_ROOT . '/install') . '/scripts/';

        foreach ($update_scripts as $key => $val) {
            if (substr($val, -strlen('.sql')) === '.sql') {
                //just a SQL script, run it
                $script = fopen($scripts_path . $val, 'r');

                if ($script === false) {
                    throw new \RuntimeException(
                        'Unable to read SQL script from ' . $scripts_path . $val
                    );
                }

                $sql_query = @fread(
                    $script,
                    @filesize($scripts_path . $val)
                ) . "\n";

                $sql_res = $this->executeSql($zdb, $sql_query);
                if (!$sql_res) {
                    $fatal_error = true;
                }
            } else {
                //we got an update class
                include_once $scripts_path . $val;
                $className = '\Galette\Updates\UpgradeTo' .
                    str_replace('.', '', $key);
                $ret = array(
                    'message'   => null,
                    'res'       => false
                );
                try {
                    $updater = new $className();
                    if ($updater instanceof \Galette\Updater\AbstractUpdater) {
                        $updater->run($zdb, $this);
                        $ret = $updater->getReport();
                        $this->_report = array_merge($this->_report, $ret);
                    } else {
                        $fatal_error = true;
                        Analog::log(
                            'Update class does not extends AbstractUpdater!',
                            Analog::ERROR
                        );
                    }

                    $ret['message'] = str_replace(
                        '%version',
                        $key,
                        _T("%version script has been successfully executed :)")
                    );
                    $ret['res'] = true;
                    $this->_report[] = $ret;
                } catch (\RuntimeException $e) {
                    Analog::log(
                        $e->getMessage(),
                        Analog::ERROR
                    );
                    $ret['message'] = str_replace(
                        '%version',
                        $key,
                        _T("Unable to run %version update script :(")
                    );
                    $fatal_error = true;
                    $this->_report[] = $ret;
                }
            }

            Analog::log(
                str_replace('%s', $key, 'Upgrade to %s complete'),
                Analog::INFO
            );
        }

        return !$fatal_error;
    }

    /**
     * Executes SQL queries
     *
     * @param Db     $zdb       Database instance
     * @param string $sql_query SQL instructions
     *
     * @return boolean
     */
    public function executeSql(Db $zdb, string $sql_query): bool
    {
        $queries_results = array();
        $fatal_error = false;

        // begin : copyright (2002) the phpbb group (support@phpbb.com)
        // load in the sql parser
        include_once GALETTE_ROOT . 'includes/sql_parse.php';

        $sql_query = preg_replace('/galette_/', $this->_db_prefix, $sql_query);
        $sql_query = remove_remarks($sql_query);

        $sql_query = split_sql_file($sql_query, ';');

        $zdb->connection->beginTransaction();

        $sql_size = sizeof($sql_query);
        for ($i = 0; $i < $sql_size; $i++) {
            $query = trim($sql_query[$i]);
            if ($query != '' && $query[0] != '-') {
                //some output infos
                $ret = array(
                    'message'   => $query,
                    'res'       => false
                );

                try {
                    $zdb->db->query(
                        $query,
                        Adapter::QUERY_MODE_EXECUTE
                    );
                    $ret['res'] = true;
                } catch (Throwable $e) {
                    $log_lvl = Analog::WARNING;
                    //if error are on drop, DROP, rename or RENAME we can continue
                    $parts = explode(' ', $query, 1);
                    if (
                        (strcasecmp(trim($parts[0]), 'drop') != 0)
                        && (strcasecmp(trim($parts[0]), 'rename') != 0)
                    ) {
                        $log_lvl = Analog::ERROR;
                        $ret['debug'] = $e->getMessage();
                        $ret['query'] = $query;
                        $ret['res'] = false;
                        $fatal_error = true;
                    } else {
                        $ret['res'] = true;
                    }
                    Analog::log(
                        'Error executing query | ' . $e->getMessage(),
                        $log_lvl
                    );
                }

                $queries_results[] = $ret;
            }
        }

        if ($fatal_error) {
            try {
                $zdb->connection->rollBack();
            } catch (\PDOException $e) {
                //to avoid php8/mysql autocommit issue
                if ($zdb->isPostgres() || !str_contains($e->getMessage(), 'no active transaction')) {
                    throw $e;
                }
            }
        } else {
            try {
                $zdb->connection->commit();
            } catch (\PDOException $e) {
                //to avoid php8/mysql autocommit issue
                if ($zdb->isPostgres() || !str_contains($e->getMessage(), 'no active transaction')) {
                    throw $e;
                }
            }
        }

        $this->_report = array_merge($this->_report, $queries_results);
        return !$fatal_error;
    }

    /**
     * Retrieve database installation report
     *
     * @return array<string, mixed>
     */
    public function getDbInstallReport(): array
    {
        return $this->_report;
    }

    /**
     * Reinitialize report array
     *
     * @return void
     */
    public function reinitReport(): void
    {
        $this->_report = array();
    }

    /**
     * Set step to super admin information
     *
     * @return void
     */
    public function atAdminStep(): void
    {
        $this->_step = self::STEP_ADMIN;
    }

    /**
     * Are we at super admin information step?
     *
     * @return boolean
     */
    public function isAdminStep(): bool
    {
        return $this->_step === self::STEP_ADMIN;
    }

    /**
     * Set super administrator information
     *
     * @param string $login Login
     * @param string $pass  Password
     *
     * @return void
     */
    public function setAdminInfos(string $login, string $pass): void
    {
        $this->_admin_login = $login;
        $this->_admin_pass = password_hash($pass, PASSWORD_BCRYPT);
    }

    /**
     * Retrieve super admin login
     *
     * @return string
     */
    public function getAdminLogin(): string
    {
        return $this->_admin_login;
    }

    /**
     * Retrieve super admin password
     *
     * @return string
     */
    public function getAdminPass(): string
    {
        return $this->_admin_pass;
    }

    /**
     * Set step to telemetry
     *
     * @return void
     */
    public function atTelemetryStep(): void
    {
        $this->_step = self::STEP_TELEMETRY;
    }

    /**
     * Are we at telemetry step?
     *
     * @return boolean
     */
    public function isTelemetryStep(): bool
    {
        return $this->_step === self::STEP_TELEMETRY;
    }

    /**
     * Set step to Galette initialization
     *
     * @return void
     */
    public function atGaletteInitStep(): void
    {
        $this->_step = self::STEP_GALETTE_INIT;
    }

    /**
     * Are we at Galette initialization step?
     *
     * @return boolean
     */
    public function isGaletteInitStep(): bool
    {
        return $this->_step === self::STEP_GALETTE_INIT;
    }

    /**
     * Load existing config
     *
     * @param array<string, string> $post_data      Data posted
     * @param array<int, string>    $error_detected Errors array
     *
     * @return void
     */
    public function loadExistingConfig(array $post_data, array &$error_detected): void
    {
        if (file_exists(GALETTE_CONFIG_PATH . 'config.inc.php')) {
            $existing = $this->loadExistingConfigFile($post_data);

            if ($existing['db_type'] !== null) {
                $this->setDbType($existing['db_type'], $error_detected);
            }

            if (
                $existing['db_host'] !== null
                || $existing['db_user'] !== null
                || $existing['db_name'] !== null
            ) {
                $this->setDsn(
                    $existing['db_host'],
                    $existing['db_port'],
                    $existing['db_name'],
                    $existing['db_user'],
                    null
                );
            }

            if ($existing['prefix'] !== null) {
                $this->setTablesPrefix(
                    $existing['prefix']
                );
            }
        }
    }

    /**
     * Load contents from existing config file
     *
     * @param array<string, string> $post_data Data posted
     * @param boolean               $pass      Retrieve password
     *
     * @return array<string, ?string>
     */
    private function loadExistingConfigFile(array $post_data = array(), bool $pass = false): array
    {
        $existing = array(
            'db_type'   => null,
            'db_host'   => null,
            'db_port'   => null,
            'db_user'   => null,
            'db_name'   => null,
            'prefix'    => null
        );

        if (file_exists(GALETTE_CONFIG_PATH . 'config.inc.php')) {
            $conf = file_get_contents(GALETTE_CONFIG_PATH . 'config.inc.php');
            if ($conf !== false) {
                if (!isset($post_data['install_dbtype'])) {
                    preg_match(
                        '/TYPE_DB["\'], ["\'](.*)["\']\);/',
                        $conf,
                        $matches
                    );
                    if (isset($matches[1])) {
                        $existing['db_type'] = $matches[1];
                    }
                }
                if (!isset($post_data['install_dbhost'])) {
                    preg_match(
                        '/HOST_DB["\'], ["\'](.*)["\']\);/',
                        $conf,
                        $matches
                    );
                    if (isset($matches[1])) {
                        $existing['db_host'] = $matches[1];
                    }
                }
                if (!isset($post_data['install_dbport'])) {
                    preg_match(
                        '/PORT_DB["\'], ["\'](.*)["\']\);/',
                        $conf,
                        $matches
                    );
                    if (isset($matches[1])) {
                        $existing['db_port'] = $matches[1];
                    }
                }
                if (!isset($post_data['install_dbuser'])) {
                    preg_match(
                        '/USER_DB["\'], ["\'](.*)["\']\);/',
                        $conf,
                        $matches
                    );
                    if (isset($matches[1])) {
                        $existing['db_user'] = $matches[1];
                    }
                }
                if (!isset($post_data['install_dbname'])) {
                    preg_match(
                        '/NAME_DB["\'], ["\'](.*)["\']\);/',
                        $conf,
                        $matches
                    );
                    if (isset($matches[1])) {
                        $existing['db_name'] = $matches[1];
                    }
                }


                if (!isset($post_data['install_dbprefix'])) {
                    preg_match(
                        '/PREFIX_DB["\'], ["\'](.*)["\']\);/',
                        $conf,
                        $matches
                    );
                    if (isset($matches[1])) {
                        $existing['prefix'] = $matches[1];
                    }
                }

                if ($pass === true) {
                    preg_match(
                        '/PWD_DB["\'], ["\'](.*)["\']\);/',
                        $conf,
                        $matches
                    );
                    if (isset($matches[1])) {
                        $existing['pwd_db'] = $matches[1];
                    }
                }
            }
        }

        return $existing;
    }

    /**
     * Write configuration file to disk
     *
     * @return boolean
     */
    public function writeConfFile(): bool
    {
        $error = false;
        $ret = array(
            'message'   => _T("Write configuration file"),
            'res'       => false
        );

        //if config file is already up-to-date, nothing to write
        $existing = $this->loadExistingConfigFile(array(), true);

        if (
            isset($existing['db_type'])
            && $existing['db_type'] == $this->_db_type
            && isset($existing['db_host'])
            && $existing['db_host'] == $this->_db_host
            && isset($existing['db_port'])
            && $existing['db_port'] == $this->_db_port
            && isset($existing['db_user'])
            && $existing['db_user'] == $this->_db_user
            && isset($existing['pwd_db'])
            && $existing['pwd_db'] == $this->_db_pass
            && isset($existing['db_name'])
            && $existing['db_name'] == $this->_db_name
            && isset($existing['prefix'])
            && $existing['prefix'] == $this->_db_prefix
        ) {
            Analog::log(
                'Config file is already up-to-date, nothing to do.',
                Analog::INFO
            );

            $this->_report[] = array(
                'message'   => _T("Config file already exists and is up to date"),
                'res'       => true
            );
            return true;
        }

        $conffile = GALETTE_CONFIG_PATH . 'config.inc.php';
        if (
            is_writable(GALETTE_CONFIG_PATH)
            && (!file_exists($conffile) || is_writable($conffile))
            && $fd = @fopen($conffile, 'w')
        ) {
                $data = "<?php
define('TYPE_DB', '" . $this->_db_type . "');
define('HOST_DB', '" . $this->_db_host . "');
define('PORT_DB', '" . $this->_db_port . "');
define('USER_DB', '" . $this->_db_user . "');
define('PWD_DB', '" . $this->_db_pass . "');
define('NAME_DB', '" . $this->_db_name . "');
define('PREFIX_DB', '" . $this->_db_prefix . "');
";
            fwrite($fd, $data);
            fclose($fd);
            $ret['res'] = true;
            Analog::log('Configuration file written on disk', Analog::INFO);
        } else {
            $str = str_replace(
                '%path',
                $conffile,
                _T("Unable to create configuration file (%path)")
            );
            Analog::log($str, Analog::WARNING);
            $ret['error'] = $str;
            $error = true;
        }
        $this->_report[] = $ret;
        return !$error;
    }

    /**
     * Initialize Galette relevant objects
     *
     * @param I18n  $i18n  I18n
     * @param Db    $zdb   Database instance
     * @param Login $login Logged in instance
     *
     * @return boolean
     */
    public function initObjects(I18n $i18n, Db $zdb, Login $login): bool
    {
        if ($this->isInstall()) {
            $preferences = new Preferences($zdb, false);
            $ct = new \Galette\Entity\ContributionsTypes($zdb);
            $status = new \Galette\Entity\Status($zdb);
            include_once '../includes/fields_defs/members_fields.php';
            include_once '../includes/fields_defs/members_fields_cats.php';
            $fc = new \Galette\Entity\FieldsConfig(
                $zdb,
                \Galette\Entity\Adherent::TABLE,
                //@phpstan-ignore-next-line
                $members_fields,
                //@phpstan-ignore-next-line
                $members_fields_cats,
                true
            );

            global $login;
            $login = new \Galette\Core\Login($zdb, $i18n);
            //$fc = new \Galette\Entity\FieldsCategories();
            $texts = new \Galette\Entity\Texts($preferences);
            $titles = new \Galette\Repository\Titles($zdb);

            $models = new \Galette\Repository\PdfModels($zdb, $preferences, $login);

            $this->_error = false;

            //Install preferences
            $res = $preferences->installInit(
                $i18n->getID(),
                $this->getAdminLogin(),
                $this->getAdminPass()
            );
            $this->proceedReport(_T("Settings"), $res);

            //Install contributions types
            $res = $ct->installInit();
            $this->proceedReport(_T("Contributions types"), $res);

            //Install statuses
            $res = $status->installInit();
            $this->proceedReport(_T("Status"), $res);

            //Install fields configuration and categories
            $res = $fc->installInit();
            $this->proceedReport(_T("Fields config and categories"), $res);

            //Install texts
            $res = $texts->installInit(false);
            $this->proceedReport(_T("Mails texts"), $res);

            //Install titles
            $res = $titles->installInit();
            $this->proceedReport(_T("Titles"), $res);

            //Install PDF models
            $res = $models->installInit(false);
            $this->proceedReport(_T("PDF models"), $res);

            return !$this->_error;
        } elseif ($this->isUpgrade()) {
            $preferences = new Preferences($zdb);
            $preferences->store();
            $this->proceedReport(_T("Update preferences"), true);

            $models = new \Galette\Repository\PdfModels($zdb, $preferences, new Login($zdb, $i18n));
            $models->installInit(true);
            $this->proceedReport(_T("Update models"), true);

            $texts = new \Galette\Entity\Texts($preferences);
            $texts->installInit(true);
            $this->proceedReport(_T("Mails texts"), true);

            return true;
        }
        return false;
    }

    /**
     * Proceed installation report for each Entity/Repository
     *
     * @param string         $msg Report message title
     * @param bool|Throwable $res Initialization result
     *
     * @return void
     */
    private function proceedReport(string $msg, bool|Throwable $res): void
    {
        $ret = array(
            'message'   => $msg,
            'res'       => false
        );

        if ($res instanceof \Exception) {
            $ret['debug'] = $res->getMessage();
            $this->_error = true;
        } else {
            $ret['res'] = true;
        }
        $this->_report[] = $ret;
    }
    /**
     * Retrieve galette initialization report
     *
     * @return array<string, mixed>
     */
    public function getInitializationReport(): array
    {
        return $this->_report;
    }

    /**
     * Set step to database installation
     *
     * @return void
     */
    public function atEndStep(): void
    {
        $this->_step = self::STEP_END;
    }

    /**
     * Are we at end step?
     *
     * @return boolean
     */
    public function isEndStep(): bool
    {
        return $this->_step === self::STEP_END;
    }

    /**
     * Set installed version if we're upgrading
     *
     * @param ?string $version Installed version
     *
     * @return void
     */
    public function setInstalledVersion(?string $version): void
    {
        $this->_installed_version = $version;
    }

    /**
     * Current Galette installed version, according to database
     *
     * @param Db $zdb Database instance
     *
     * @return string|false
     */
    public function getCurrentVersion(Db $zdb): string|false
    {
        try {
            $db_ver = $zdb->getDbVersion(true);
            if (isset($this->versions_mapper[$db_ver])) {
                return $this->versions_mapper[$db_ver];
            } else {
                return $db_ver;
            }
        } catch (\LogicException $e) {
            return false;
        }
    }

    /**
     * Check if step is passed
     *
     * @param int $step Step
     *
     * @return boolean
     */
    public function isStepPassed($step): bool
    {
        return $this->_step > $step;
    }
}
