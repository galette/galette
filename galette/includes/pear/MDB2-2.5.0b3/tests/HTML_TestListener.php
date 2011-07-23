<?php

class HTML_TestListener implements PHPUnit_Framework_TestListener {
    protected $_errors = 0;
    protected $_fails  = 0;

    public function addError(PHPUnit_Framework_Test $test, Exception $e, $time) {
        $this->_errors += 1;
        printf("<div class='error'>Error while running test '%s'.</div>\n", $test->getName());
        //echo("<div class=\"error\"> Error $this->_errors in ".$test->getName()." : $t</div>");
    }

    public function addFailure(PHPUnit_Framework_Test $test, PHPUnit_Framework_AssertionFailedError $e, $time) {
        $this->_fails += 1;
        if ($this->_fails == 1) {
            echo("\n<div class=\"failure\">");
        }
        printf("Test '%s' failed : %s.<br />\n<pre>%s</pre>\n", $test->getName(), htmlentities($e->getMessage()), (string)$e->getTraceAsString());
        //echo("Failure $this->_fails : $t<br>\n");
    }

    public function addIncompleteTest(PHPUnit_Framework_Test $test, Exception $e, $time) {
        printf("Test '%s' is incomplete.\n", $test->getName());
    }

    public function addSkippedTest(PHPUnit_Framework_Test $test, Exception $e, $time) {
        printf("Test '%s' has been skipped.\n", $test->getName());
    }

    public function startTest(PHPUnit_Framework_Test $test) {
        $this->_fails = 0;
        $this->_errors = 0;
        //printf("Test '%s' started.\n", $test->getName());
        echo("\n<div class=\"testcase\">".get_class($test).' : Starting '.$test->getName().' ...');
    }

    public function endTest(PHPUnit_Framework_Test $test, $time) {
        //printf("Test '%s' ended.\n", $test->getName());
        if ($this->_fails == 0 && $this->_errors == 0) {
            echo(' Test passed');
        } else {
            echo("There were $this->_fails failures for ".$test->getName()."</br>\n");
            echo("There were $this->_errors errors for ".$test->getName()."</div>\n");
        }
        echo('</div>');

    }

    public function startTestSuite(PHPUnit_Framework_TestSuite $suite) {
        printf("TestSuite '%s' started.\n", $suite->getName());
    }

    public function endTestSuite(PHPUnit_Framework_TestSuite $suite) {
        printf("TestSuite '%s' ended.\n", $suite->getName());
    }
}
