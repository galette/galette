<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette installation
 *
 * PHP version 5
 *
 * Copyright © 2013-2014 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
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
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
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
    public const STEP_GALETTE_INIT = 8;
    public const STEP_END = 9;

    public const INSTALL = 'i';
    public const UPDATE = 'u';

    //db version/galette version mapper
    private $versions_mapper = array(
        '0.700' => '0.70',
        '0.701' => '0.71',
        '0.702' => '0.74',
        '0.703' => '0.75',
        '0.704' => '0.76'
    );

    private $_step;
    private $_mode;
    private $_version;
    private $_installed_version;

    private $_db_type;
    private $_db_host;
    private $_db_port;
    private $_db_name;
    private $_db_user;
    private $_db_pass;

    private $_db_connected;
    private $_report;

    private $_admin_login;
    private $_admin_pass;

    private $_error;

    /**
     * Main constructor
     */
    public function __construct()
    {
        $this->_step = self::STEP_CHECK;
        $this->_mode = null;
        $this->_version = str_replace('v', '', GALETTE_VERSION);
        $this->_db_connected = false;
        $this->_db_prefix = null;
    }

    /**
     * Return current step title
     *
     * @return string
     */
    public function getStepTitle()
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
     * @param boolean $arg Argument
     *
     * @return html string
     */
    public function getValidationImage($arg)
    {
        $img_name = ($arg === true) ? 'valid' : 'invalid';
        $src = GALETTE_THEME_DIR . 'images/icon-' . $img_name . '.png';
        $alt = ($arg === true) ? _T("Ok") : _T("Ko");
        $img = '<img src="' . $src . '" alt="' . $alt . '"/>';
        return $img;
    }

    /**
     * Get current mode
     *
     * @return char
     */
    public function getMode()
    {
        return $this->_mode;
    }

    /**
     * Are we installing?
     *
     * @return boolean
     */
    public function isInstall()
    {
        return $this->_mode === self::INSTALL;
    }

    /**
     * Are we upgrading?
     *
     * @return boolean
     */
    public function isUpgrade()
    {
        return $this->_mode === self::UPDATE;
    }

    /**
     * Set installation mode
     *
     * @param char $mode Requested mode
     *
     * @return void
     */
    public function setMode($mode)
    {
        if ($mode === self::INSTALL || $mode === self::UPDATE) {
            $this->_mode = $mode;
        } else {
            throw new \UnexpectedValueException('Unknown mode "' . $mode . '"');
        }
    }

    /**
     * Go back to previous step
     *
     * @return void
     */
    public function atPreviousStep()
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
    public function isCheckStep()
    {
        return $this->_step === self::STEP_CHECK;
    }

    /**
     * Set step to type of installation
     *
     * @return void
     */
    public function atTypeStep()
    {
        $this->_step = self::STEP_TYPE;
    }

    /**
     * Are we at type step?
     *
     * @return boolean
     */
    public function isTypeStep()
    {
        return $this->_step === self::STEP_TYPE;
    }

    /**
     * Set step to database information
     *
     * @return void
     */
    public function atDbStep()
    {
        $this->_step = self::STEP_DB;
    }

    /**
     * Are we at database step?
     *
     * @return boolean
     */
    public function isDbStep()
    {
        return $this->_step === self::STEP_DB;
    }

    /**
     * Is DB step passed?
     *
     * @return boolean
     */
    public function postCheckDb()
    {
        return $this->_step > self::STEP_DB_CHECKS;
    }

    /**
     * Set database type
     *
     * @param string $type Database type
     * @param array  $errs Errors array
     *
     * @return boolean
     */
    public function setDbType($type, &$errs)
    {
        switch ($type) {
            case Db::MYSQL:
            case Db::PGSQL:
                $this->_db_type = $type;
                break;
            default:
                $errs[] = _T("Database type unknown");
        }
    }

    /**
     * Get database type
     *
     * @return string
     */
    public function getDbType()
    {
        return $this->_db_type;
    }

    /**
     * Set connection information
     *
     * @param string $host Database host
     * @param string $port Database port
     * @param string $name Database name
     * @param string $user Database user name
     * @param string $pass Database user's password
     *
     * @return void
     */
    public function setDsn($host, $port, $name, $user, $pass)
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
     * @return void
     */
    public function setTablesPrefix($prefix)
    {
        $this->_db_prefix = $prefix;
    }

    /**
     * Retrieve database host
     *
     * @return string
     */
    public function getDbHost()
    {
        return $this->_db_host;
    }

    /**
     * Retrieve database port
     *
     * @return string
     */
    public function getDbPort()
    {
        return $this->_db_port;
    }

    /**
     * Retrieve database name
     *
     * @return string
     */
    public function getDbName()
    {
        return $this->_db_name;
    }

    /**
     * Retrieve database user
     *
     * @return string
     */
    public function getDbUser()
    {
        return $this->_db_user;
    }

    /**
     * Retrieve database password
     *
     * @return string
     */
    public function getDbPass()
    {
        return $this->_db_pass;
    }

    /**
     * Retrieve tables prefix
     *
     * @return string
     */
    public function getTablesPrefix()
    {
        return $this->_db_prefix;
    }

    /**
     * Set step to database checks
     *
     * @return void
     */
    public function atDbCheckStep()
    {
        $this->_step = self::STEP_DB_CHECKS;
    }

    /**
     * Are we at database check step?
     *
     * @return boolean
     */
    public function isDbCheckStep()
    {
        return $this->_step === self::STEP_DB_CHECKS;
    }

    /**
     * Test database connection
     *
     * @return true|array true if connection was successfull,
     * an array with some infos otherwise
     */
    public function testDbConnexion()
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
    public function isDbConnected()
    {
        return $this->_db_connected;
    }

    /**
     * Set step to version selection
     *
     * @return void
     */
    public function atVersionSelection()
    {
        $this->_step = self::STEP_VERSION;
    }

    /**
     * Are we at version selection step?
     *
     * @return boolean
     */
    public function isVersionSelectionStep()
    {
        return $this->_step === self::STEP_VERSION;
    }

    /**
     * Set step to database installation
     *
     * @return void
     */
    public function atDbInstallStep()
    {
        $this->_step = self::STEP_DB_INSTALL;
    }

    /**
     * Are we at db installation step?
     *
     * @return boolean
     */
    public function isDbinstallStep()
    {
        return $this->_step === self::STEP_DB_INSTALL;
    }

    /**
     * Set step to database upgrade
     *
     * @return void
     */
    public function atDbUpgradeStep()
    {
        $this->_step = self::STEP_DB_UPGRADE;
    }

    /**
     * Are we at db upgrade step?
     *
     * @return boolean
     */
    public function isDbUpgradeStep()
    {
        return $this->_step === self::STEP_DB_UPGRADE;
    }


    /**
     * Install/Update SQL scripts
     *
     * @param string $path Path to scripts (defaults to core scripts)
     *
     * @return array
     */
    public function getScripts($path = null)
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
     * @param string $path    Scripts path
     * @param string $db_type Database type
     * @param string $version Previous version, defaults to null
     *
     * @return array If a previous version is provided, update scripts
     *               file path from this one to the latest will be returned.
     *               If no previous version is provided, that will return all
     *               updates versions known.
     */
    public static function getUpdateScripts(
        $path,
        $db_type = 'mysql',
        $version = null
    ) {
        $dh = opendir($path . '/scripts');
        $php_update_scripts = array();
        $sql_update_scripts = array();
        if ($dh !== false) {
            while (($file = readdir($dh)) !== false) {
                if (preg_match("/upgrade-to-(.*).php/", $file, $ver)) {
                    if ($version === null) {
                        $php_update_scripts[$ver[1]] = $ver[1];
                    } else {
                        if ($version <= $ver[1]) {
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
                        if ($version <= $ver[1]) {
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
     * @param Galette\Core\Db $zdb Database instance
     *
     * @return boolean
     */
    public function executeScripts($zdb)
    {
        $queries_results = array();
        $fatal_error = false;
        $update_scripts = $this->getScripts();
        $sql_query = '';
        $this->_report = array();
        $scripts_path = GALETTE_ROOT . '/install/scripts/';

        foreach ($update_scripts as $key => $val) {
            if (substr($val, -strlen('.sql')) === '.sql') {
                //just a SQL script, run it
                $script = fopen($scripts_path . $val, 'r');

                if ($script === false) {
                    throw new \RuntimeException(
                        'Unable to read SQL script from ' . $scripts_path . $val
                    );
                }

                $sql_query .= @fread(
                    $script,
                    @filesize($scripts_path . $val)
                ) . "\n";
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
        }

        if ($sql_query !== '') {
            $sql_res = $this->executeSql($zdb, $sql_query);
            $fatal_error = !$sql_res;
        }
        return !$fatal_error;
    }

    /**
     * Executes SQL queries
     *
     * @param Db     $zdb       Database instance
     * @param string $sql_query SQL instructions
     *
     * @return boolean;
     */
    public function executeSql($zdb, $sql_query)
    {
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
            $zdb->connection->rollBack();
        } else {
            $zdb->connection->commit();
        }

        $this->_report = array_merge($this->_report, $queries_results);
        return !$fatal_error;
    }

    /**
     * Retrieve database installation report
     *
     * @return array
     */
    public function getDbInstallReport()
    {
        return $this->_report;
    }

    /**
     * Reinitialize report array
     *
     * @return void
     */
    public function reinitReport()
    {
        $this->_report = array();
    }

    /**
     * Set step to super admin information
     *
     * @return void
     */
    public function atAdminStep()
    {
        $this->_step = self::STEP_ADMIN;
    }

    /**
     * Are we at super admin information step?
     *
     * @return boolean
     */
    public function isAdminStep()
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
    public function setAdminInfos($login, $pass)
    {
        $this->_admin_login = $login;
        $this->_admin_pass = password_hash($pass, PASSWORD_BCRYPT);
    }

    /**
     * Retrieve super admin login
     *
     * @return string
     */
    public function getAdminLogin()
    {
        return $this->_admin_login;
    }

    /**
     * Retrieve super admin password
     *
     * @return string
     */
    public function getAdminPass()
    {
        return $this->_admin_pass;
    }

    /**
     * Set step to Galette initialization
     *
     * @return void
     */
    public function atGaletteInitStep()
    {
        $this->_step = self::STEP_GALETTE_INIT;
    }

    /**
     * Are we at Galette initialization step?
     *
     * @return boolean
     */
    public function isGaletteInitStep()
    {
        return $this->_step === self::STEP_GALETTE_INIT;
    }

    /**
     * Load existing config
     *
     * @param array $post_data      Data posted
     * @param array $error_detected Errors array
     *
     * @return void
     */
    public function loadExistingConfig($post_data, &$error_detected)
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
     * @param array   $post_data Data posted
     * @param boolean $pass      Retrieve password
     *
     * @return array
     */
    private function loadExistingConfigFile($post_data = array(), $pass = false)
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
                    $res = preg_match(
                        '/TYPE_DB["\'], ["\'](.*)["\']\);/',
                        $conf,
                        $matches
                    );
                    if (isset($matches[1])) {
                        $existing['db_type'] = $matches[1];
                    }
                }
                if (!isset($post_data['install_dbhost'])) {
                    $res = preg_match(
                        '/HOST_DB["\'], ["\'](.*)["\']\);/',
                        $conf,
                        $matches
                    );
                    if (isset($matches[1])) {
                        $existing['db_host'] = $matches[1];
                    }
                }
                if (!isset($post_data['install_dbport'])) {
                    $res = preg_match(
                        '/PORT_DB["\'], ["\'](.*)["\']\);/',
                        $conf,
                        $matches
                    );
                    if (isset($matches[1])) {
                        $existing['db_port'] = $matches[1];
                    }
                }
                if (!isset($post_data['install_dbuser'])) {
                    $res = preg_match(
                        '/USER_DB["\'], ["\'](.*)["\']\);/',
                        $conf,
                        $matches
                    );
                    if (isset($matches[1])) {
                        $existing['db_user'] = $matches[1];
                    }
                }
                if (!isset($post_data['install_dbname'])) {
                    $res = preg_match(
                        '/NAME_DB["\'], ["\'](.*)["\']\);/',
                        $conf,
                        $matches
                    );
                    if (isset($matches[1])) {
                        $existing['db_name'] = $matches[1];
                    }
                }


                if (!isset($post_data['install_dbprefix'])) {
                    $res = preg_match(
                        '/PREFIX_DB["\'], ["\'](.*)["\']\);/',
                        $conf,
                        $matches
                    );
                    if (isset($matches[1])) {
                        $existing['prefix'] = $matches[1];
                    }
                }

                if ($pass === true) {
                    $res = preg_match(
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
    public function writeConfFile()
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
            && (!file_exists($conffile) || file_exists($conffile) && is_writable($conffile))
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
     * @param Login $login Loged in instance
     *
     * @return boolean
     */
    public function initObjects(I18n $i18n, Db $zdb, Login $login)
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
                $members_fields,
                $members_fields_cats,
                true
            );

            global $login;
            $login = new \Galette\Core\Login($zdb, $i18n);
            //$fc = new \Galette\Entity\FieldsCategories();
            $texts = new \Galette\Entity\Texts($preferences);
            $titles = new \Galette\Repository\Titles();

            $models = new \Galette\Repository\PdfModels($zdb, $preferences, $login);

            $this->_error = false;

            //Install preferences
            $res = $preferences->installInit(
                $i18n->getID(),
                $this->getAdminLogin(),
                $this->getAdminPass()
            );
            $this->proceedReport(_T("Preferences"), $res);

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
            $res = $titles->installInit($zdb);
            $this->proceedReport(_T("Titles"), $res);

            //Install PDF models
            $res = $models->installInit(false);
            $this->proceedReport(_T("PDF Models"), $res);

            return !$this->_error;
        } elseif ($this->isUpgrade()) {
            $preferences = new Preferences($zdb);
            $preferences->store();
            $this->proceedReport(_T("Update preferences"), true);

            $models = new \Galette\Repository\PdfModels($zdb, $preferences, new Login($zdb, $i18n));
            $res = $models->installInit(true);
            $this->proceedReport(_T("Update models"), true);

            $texts = new \Galette\Entity\Texts($preferences);
            $res = $texts->installInit(true);
            $this->proceedReport(_T("Mails texts"), true);

            return true;
        }
    }

    /**
     * Proceed installation report for each Entity/Repository
     *
     * @param string $msg Report message title
     * @param mixed  $res Initialialization result
     *
     * @return void
     */
    private function proceedReport($msg, $res)
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
     * @return array
     */
    public function getInitializationReport()
    {
        return $this->_report;
    }

    /**
     * Set step to database installation
     *
     * @return void
     */
    public function atEndStep()
    {
        $this->_step = self::STEP_END;
    }

    /**
     * Are we at end step?
     *
     * @return boolean
     */
    public function isEndStep()
    {
        return $this->_step === self::STEP_END;
    }

    /**
     * Set installed version if we're upgrading
     *
     * @param string $version Installed version
     *
     * @return void
     */
    public function setInstalledVersion($version)
    {
        $this->_installed_version = $version;
    }

    /**
     * Current Galette installed version, according to database
     *
     * @param Db $zdb Database instance
     *
     * @return string
     */
    public function getCurrentVersion($zdb)
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
    public function isStepPassed($step)
    {
        return $this->_step > $step;
    }
}
