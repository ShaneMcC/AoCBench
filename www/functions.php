<?php
	require_once(__DIR__ . '/../functions.php');

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
