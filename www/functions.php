<?php
	require_once(__DIR__ . '/../functions.php');

	session_start();

	// Default to 0, to disable showing banner.
	$lastScheduledRunTime = 0;

	if (file_exists(__DIR__ . '/../.doRun')) {
		$lastScheduledRunTime = filemtime(__DIR__ . '/../.doRun');
	}

	if (isset($instanceid) && !empty($instanceid) && file_exists($schedulerStateFile)) {
		$schedulerState = json_decode(file_get_contents($schedulerStateFile), true);

		$lastScheduledRunTime = $schedulerState[$instanceid]['time'] ?? 0;
	}

	$hasResults = false;
	$hasHealthCheck = false;
	if (file_exists($resultsFile)) {
		$data = json_decode(file_get_contents($resultsFile), true);
		if (isset($data['results'])) {
			$hasResults = true;
			$lastBenchEndTime = $data['finishtime'] ?? $data['time'];
			$lastBenchStartTime = $data['starttime'] ?? $data['time'];
		}
		if (isset($data['healthcheck'])) {
			$hasHealthCheck = true;
		}
	}

	$hasMatrix = false;
	if (file_exists($outputResultsFile)) {
		$matrix = json_decode(file_get_contents($outputResultsFile), true);
		if (isset($matrix['results'])) {
			$hasMatrix = true;
			$lastMatrixEndTime = $data['finishtime'] ?? $data['time'];
			$lastMatrixStartTime = $data['starttime'] ?? $data['time'];
		}
	}

	function getDayBestTimes($day, $method) {
		global $data, $displayParticipants;

		$times = [];
		foreach ($data['results'] as $participant => $pdata) {
			if (!empty($displayParticipants) && !in_array($participant, $displayParticipants)) { continue; }

			if (isset($pdata['days'][$day]['times'])) {
				$times[] = getParticipantTime($pdata['days'][$day]['times'], $method);
			}
		}

		sort($times);
		return $times;
	}

	function getParticipantTime($times, $method) {
		$parsedTimes = getSortedTimes($times, false);

		switch (strtoupper($method)) {
			case 'SPECIAL':
			case 'MEANBEST':
				$parsedTimes = array_chunk($parsedTimes, count($parsedTimes) - 5)[0];
				return array_sum($parsedTimes) / count($parsedTimes);

			case 'AVG':
			case 'MEAN':
				return array_sum($parsedTimes) / count($parsedTimes);

			case 'MIN':
			case 'MINIMUM':
				return $parsedTimes[0];

			case 'MAX':
			case 'MAXIMUM':
				return $parsedTimes[count($parsedTimes) - 1];

			case 'MEDIAN':
			default:
				$count = count($parsedTimes);
				$middle = floor(($count - 1) / 2);

				if ($count % 2) {
					return $parsedTimes[$middle];
				} else {
					$low = $parsedTimes[$middle];
					$high = $parsedTimes[$middle + 1];
					return (($low + $high) / 2);
				}
		}

	}
