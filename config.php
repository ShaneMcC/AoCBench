<?php

	$resultsFile = __DIR__ . '/results.json';
	$participantsDir = __DIR__ . '/participants/';

	$repeatCount = 20;
	$longTimeout = 30;
	$longRepeatCount = 10;

	$normaliseInput = true;
	$inputsDir = __DIR__ . '/inputs/';

	$participants = [];

	abstract class Participant {
		abstract function getName();
		abstract function getRepo();
		abstract function prepare();
		public function hasDay($day) { return $this->getVersion($day) !== NULL; }
		abstract function run($day);
		abstract function getVersion($day);
		abstract function getInput($day);
		abstract function setInput($day, $input);

		function extractTime($output) {
			$time = $output[count($output) - 3];
			$time = trim(preg_replace('#^real#', '', $time));
			return $time;
		}
	}

	// Local configuration.
	if (file_exists(dirname(__FILE__) . '/config.local.php')) {
		include(dirname(__FILE__) . '/config.local.php');
	}
