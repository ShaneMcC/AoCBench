<?php
	require_once(__DIR__ . '/../config.php');

	$hasResults = false;
	if (file_exists($resultsFile)) {
		$data = json_decode(file_get_contents($resultsFile), true);
		if (isset($data['results'])) {
			$hasResults = true;
		}
	}

	$hasMatrix = false;
	if (file_exists($outputResultsFile)) {
		$matrix = json_decode(file_get_contents($outputResultsFile), true);
		if (isset($matrix['results'])) {
			$hasMatrix = true;
		}
	}

	function parseTime($time) {
		if (preg_match('#^([0-9]+)m\s?([0-9]+).([0-9]+)s$#', $time, $match)) {
			list($all, $m, $s, $ms) = $match;

			$ms = str_pad($ms, 3, '0');
			$time = $ms + ($s * 1000) + ($m * 60 * 1000);

			return $time;
		}

		return NULL;
	}

	function getDayBestTime($day, $method) {
		global $data;

		$times = [];
		foreach ($data['results'] as $participant => $pdata) {
			if (isset($pdata['days'][$day]['times'])) {
				$times[] = getParticipantTime($pdata['days'][$day]['times'], $method);
			}
		}
		if (empty($times)) { return NULL; }

		sort($times);
		return $times[0];
	}

	function getParticipantTime($times, $method) {
		$parsedTimes = [];
		foreach ($times as $time) {
			$time = parseTime($time);
			if ($time !== NULL) {
				$parsedTimes[] = $time;
			}
		}
		sort($parsedTimes);

		switch ($method) {
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

	function formatTime($time) {
		if (empty($time)) { return ''; }

		$m = $s = $ms = 0;

		if ($time > 60 * 1000) {
			$m = floor($time / (60 * 1000));
			$time -= $m * 60 * 1000;
		}

		if ($time > 1000) {
			$s = floor($time / (1000));
			$time -= $s * 1000;
		}

		$ms = $time;

		return sprintf('%dm%d.%03ds', $m, $s, $ms);
	}
