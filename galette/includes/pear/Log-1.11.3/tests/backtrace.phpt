--TEST--
Log: Backtrace Vars
--FILE--
<?php

require_once 'Log.php';

$conf = array('lineFormat' => '%6$s [%8$s::%7$s] %4$s');
$logger = &Log::singleton('console', '', 'ident', $conf);

# Top-level Logger
#
$logger->log("Top-level Logger");

# Function Logger
#
function functionLog($logger)
{
	$logger->log("Function Logger");
}

functionLog($logger);

# Class Logger
#
class ClassLogger
{
	function log($logger)
	{
		$logger->log("Class Logger");
	}
}

$classLogger = new ClassLogger();
$classLogger->log($logger);

# Composite Logger
#
$composite = &Log::singleton('composite');
$composite->addChild($logger);

$composite->log("Composite Logger");

--EXPECT--
10 [::(none)] Top-level Logger
16 [::functionLog] Function Logger
27 [ClassLogger::log] Class Logger
39 [::(none)] Composite Logger
