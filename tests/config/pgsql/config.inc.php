<?php
define("TYPE_DB", "pgsql");

if (file_exists(__DIR__ . '/local_config.inc.php')) {
    include_once __DIR__ . '/local_config.inc.php';
}
if (!defined('HOST_DB')) {
    define("HOST_DB", "localhost");
}
if (!defined('PORT_DB')) {
    define("PORT_DB", "5432");
}
if (!defined('USER_DB')) {
    define("USER_DB", "postgres");
}
if (!defined('PWD_DB')) {
    define("PWD_DB", "");
}
if (!defined('NAME_DB')) {
    define("NAME_DB", "galette_tests");
}
if (!defined('PREFIX_DB')) {
    define("PREFIX_DB", "galette_");
}
