<?php

	$resultsFile = __DIR__ . '/results.json';
	$participantsDir = __DIR__ . '/participants/';

	$participants = [];

	abstract class Participant {
		abstract function getName();
		abstract function getRepo();
		abstract function prepare();
		abstract function run($day);
		abstract function getVersion($day);
	}

	// Local configuration.
	if (file_exists(dirname(__FILE__) . '/config.local.php')) {
		include(dirname(__FILE__) . '/config.local.php');
	}
