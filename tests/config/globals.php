<?php

$config_file = getenv('CONFIG_FILE');
if (!$config_file) {
    $config_file = __DIR__ . '/test.conf.json';
}
$GLOBALS['CONFIG'] = json_decode(file_get_contents($config_file), true);

// get credentials from env variable
$GLOBALS['BROWSERSTACK_USERNAME'] = getenv('BROWSERSTACK_USERNAME');
$GLOBALS['BROWSERSTACK_ACCESS_KEY'] = getenv('BROWSERSTACK_ACCESS_KEY');

// if env variables not set, get from config file
/*if (!$GLOBALS['BROWSERSTACK_USERNAME']) {
    $GLOBALS['BROWSERSTACK_USERNAME'] = $GLOBALS['CONFIG']['user'];
}
if (!$GLOBALS['BROWSERSTACK_ACCESS_KEY']) {
    $GLOBALS['BROWSERSTACK_ACCESS_KEY'] = $GLOBALS['CONFIG']['key'];
}*/