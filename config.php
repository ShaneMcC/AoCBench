<?php

	$globalLockfile = '/tmp/.aocbench-runlock';
	$lockfile = __DIR__ . '/.benchlock';
	$resultsFile = __DIR__ . '/results/results.json';
	$outputResultsFile = __DIR__ . '/results/outputresults.json';
	$participantsDir = __DIR__ . '/participants/';
	$logFile = __DIR__ . '/results/run.log';
	$schedulerStateFile = __DIR__ . '/results/aocbench-scheduler.json';

	$leaderboardID = '';
	$leaderboardYear = '';
	$instanceid = NULL;

	$podium = true;

	$repeatCount = 20;

	$longTimeout = 10;
	$longRepeatCount = 10;

	$reallyLongTimeout = 60;
	$reallyLongRepeatCount = 2;

	$execTimeout = 300;

	$sleepTime = 0;

	$localHyperfine = file_exists('/usr/bin/hyperfine') ? '/usr/bin/hyperfine' : null;
	$localTranscrypt = file_exists('/usr/local/bin/transcrypt') ? '/usr/local/bin/transcrypt' : null;

	$localBashStatic = null;
	if (file_exists('/usr/bin/bash-static')) {
		$localBashStatic = '/usr/bin/bash-static';
	} else if (file_exists('/bin/bash-static')) {
		$localBashStatic = '/bin/bash-static';
	}

	$normaliseInput = true;
	$inputsDir = __DIR__ . '/inputs/';
	$ignoreResult = []; // '10' or '10/1' etc.

	$runDebugMode = false;

	$displayParticipants = [];
	$participants = [];

	$enableScheduledUpdates = false;
	$rabbitmq = [];
	$rabbitmq['server'] = 'localhost';
	$rabbitmq['port'] = '5672';
	$rabbitmq['username'] = 'aocbench';
	$rabbitmq['password'] = 'aocbench';
	$rabbitmq['vhost'] = 'aocbench';

	$yamlParser = 'Symphony';

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
			if ($instance == $instanceid) {
				echo date('r'), ' - Got run request for our instanceid: ', $instanceid, "\n";
				touch(__DIR__ . '/.doRun');
			}
		}
	}
