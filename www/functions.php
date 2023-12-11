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
			$lastBenchEndTime = $data['finishtime'] ?? time();
			$lastBenchStartTime = $data['starttime'] ?? time();
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
			$lastMatrixEndTime = $matrix['finishtime'] ?? time();
			$lastMatrixStartTime = $matrix['starttime'] ?? time();
		}
	}

	function getDayParticipantTimes($day, $method, $format = null) {
		global $data, $displayParticipants;

		$times = [];
		foreach ($data['results'] as $participant => $pdata) {
			if (!empty($displayParticipants) && !in_array($participant, $displayParticipants)) { continue; }

			if (isset($pdata['days'][$day]['times'])) {
				$times[$participant] = ['timeraw' => getParticipantTime($pdata['days'][$day]['times'], $method)];

				if ($format == null) {
					$times[$participant]['time'] = $times[$participant]['timeraw'];
				} else {
					$times[$participant]['time'] = formatTime($times[$participant]['timeraw'], $format);
				}

				$times[$participant]['at'] = $pdata['days'][$day]['time'] ?? 0;
			}
		}

		uksort($times, function($a, $b) use ($times) {
			$r = $times[$a]['timeraw'] <=> $times[$b]['timeraw'];
			if ($r == 0) { $r = $times[$a]['at'] <=> $times[$b]['at']; }
			if ($r == 0) { $r = $a <=> $b; }
			return $r;
		});

		$lastTime = null;
		$displayRank = $lastRank = 0;
		foreach ($times as &$data) {
			if ($lastTime !== $data['time']) { $lastRank++; }
			$displayRank++;

			$data['rank'] = $lastRank;
			$data['displayRank'] = $displayRank;
			$lastTime = $data['time'];
		}

		return $times;
	}

	function getDayBestTimes($day, $method) {
		return array_column(getDayParticipantTimes($day, $method), 'time');
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
