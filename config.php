<?php

	$lockfile = __DIR__ . '/.benchlock';
	$resultsFile = __DIR__ . '/results.json';
	$outputResultsFile = __DIR__ . '/outputresults.json';
	$participantsDir = __DIR__ . '/participants/';

	$leaderboardID = '';
	$leaderboardYear = '';
	$instanceid = NULL;

	$podium = false;

	$repeatCount = 20;

	$longTimeout = 10;
	$longRepeatCount = 10;

	$reallyLongTimeout = 120;
	$reallyLongRepeatCount = 2;

	$execTimeout = 300;

	$sleepTime = 250000;

	$normaliseInput = true;
	$inputsDir = __DIR__ . '/inputs/';
	$ignoreResult = []; // '10' or '10/1' etc.

	$displayParticipants = [];
	$participants = [];

	$rabbitmq = [];
	$rabbitmq['server'] = 'localhost';
	$rabbitmq['port'] = '5672';
	$rabbitmq['username'] = 'aocbench';
	$rabbitmq['password'] = 'aocbench';
	$rabbitmq['vhost'] = 'aocbench';

	// Local configuration.
	if (file_exists(dirname(__FILE__) . '/config.local.php')) {
		include(dirname(__FILE__) . '/config.local.php');
	}

	if ($instanceid == NULL) {
		$instanceid = 'aocbench-' . $leaderboardID . '-' . $leaderboardYear;
	}

	if (!function_exists('getInputAnswer')) {
		function getInputAnswer($day, $part) { return NULL; }
	}

	if (!function_exists('handleScheduledUpdate')) {
		function handleScheduledUpdate($instance) {
			global $instanceid;
			echo 'hsu: ', $instance, "\n";
			if ($instance == $instanceid) {

			}
		}
	}
