<?php

	$lockfile = __DIR__ . '/.benchlock';
	$resultsFile = __DIR__ . '/results.json';
	$outputResultsFile = __DIR__ . '/outputresults.json';
	$participantsDir = __DIR__ . '/participants/';

	$leaderboardID = '';
	$leaderboardYear = '';
	$podium = false;

	$repeatCount = 20;
	$longTimeout = 10;
	$longRepeatCount = 10;

	$sleepTime = 250000;

	$normaliseInput = true;
	$inputsDir = __DIR__ . '/inputs/';
	$ignoreResult = []; // '10' or '10/1' etc.

	$displayParticipants = [];
	$participants = [];

	// Local configuration.
	if (file_exists(dirname(__FILE__) . '/config.local.php')) {
		include(dirname(__FILE__) . '/config.local.php');
	}

	if (!function_exists('getInputAnswer')) {
		function getInputAnswer($day, $part) { return NULL; }
	}
