#!/usr/bin/php -q
<?php

/*

Make executable:
chmod +x run-selenium.php

Look at arguments:
./run-selenium.php

Run against Google:
./run-selenium.php ./tests/ ./junit-reports/ http://www.google.com/ selenium.server.com 4444

*/


require __DIR__ . '/services.php';

if(!isset($argv[5])) {
	echo 'Arguments from shell not found.'          . PHP_EOL;
	echo './run-selenium.php [1] [2] [3] [4] [5]'   . PHP_EOL;
	echo '[1] = Where to discover tests'            . PHP_EOL;
	echo '[2] = Where to leave jUnit XML results'   . PHP_EOL;
	echo '[3] = The host to run the tests against'  . PHP_EOL;
	echo '[4] = The hostname for Selenium RC'       . PHP_EOL;
	echo '[5] = The port number for Selenium RC'    . PHP_EOL;
	exit;
}

// Load arguments from the shell.
$tests_directory   = $argv[1]; // Where to discover tests
$results_directory = $argv[2]; // Where to leave jUnit XML results
$base_url          = $argv[3]; // The host to run the tests against
$selenium_host     = $argv[4]; // The hostname for Selenium RC
$selenium_port     = $argv[5]; // The port number for Selenium RC


// System settings
$running_ssh = false;


// Prepare a clean environment.

// Ensure a clean destination for results exists.
exec('rm -rf ' . $results_directory);
mkdir($results_directory);

// Start job-wide services.
$selenium_is_running = selenium_is_running($selenium_host, $selenium_port);
$xvfb = NULL;
$selenium = NULL;
if ($selenium_is_running) {
	echo 'Selenium is already running. Using the existing service.' . PHP_EOL;
	$selenium = new SeleniumExternalService($selenium_host, $selenium_port);
}
else {
	echo 'Selenium is not running. Starting a new, local service.' . PHP_EOL;
	// Use the Selenium port for the X display number, too.
	$x_display_number = $selenium_port;
	$xvfb = new XvfbBackgroundService($x_display_number, 1200, 2000);
	$selenium = new SeleniumBackgroundService($xvfb, $selenium_port);
}

// Run tests.
$tests = selenium_get_all_tests($tests_directory, $selenium, $base_url);
if (!empty($tests)) {
	foreach ($tests as $test) {
		echo PHP_EOL;
		echo '####################################################################' . PHP_EOL;
		echo '## Running test: ' . $test->getTestClassName()                        . PHP_EOL;
		echo '####################################################################' . PHP_EOL;

		$junit_file = $results_directory . '/' . $test->getTestClassName() . '.xml';

		// Record a screencast if there's a valid X buffer.
		if (isset($xvfb)) {
			//$screencast_file = $results_directory . '/' . $test->getTestClassName() . '.mp4';
			$screencast_file = $test->getTestClassName() . '.mp4';
			$screencast = new ScreencastBackgroundService($xvfb, $screencast_file);
		}

		// Run the test and store the output.
		$test->run($junit_file);

		// Stop the screencast if one is active.
		if (isset($screencast)) {
			unset($screencast);
		}

		// Unload the test.
		unset($test);
	}
}
else {
	echo 'No tests found.' . PHP_EOL;
}
