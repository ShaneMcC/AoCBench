<?php
	require_once(__DIR__ . '/../config.php');

	$pageid = 'index';
	require_once(__DIR__ . '/header.php');

	function getTime($times, $method = 'MIN') {
		$parsedTimes = [];
		foreach ($times as $time) {
			if (preg_match('#^([0-9]+)m([0-9]+).([0-9]+)s$#', $time, $match)) {
				list($all, $m, $s, $ms) = $match;

				$time = $ms + ($s * 1000) + ($m * 60 * 1000);
				$parsedTimes[] = $time;
			}
		}

		switch ($method) {
			case 'AVG':
				return array_sum($parsedTimes) / count($parsedTimes);

			case 'MIN':
			default:
				return $parsedTimes[0];
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

	$method = isset($_REQUEST['method']) ? $_REQUEST['method'] : 'AVG';

	$hasResults = false;
	if (file_exists($resultsFile)) {
		$data = json_decode(file_get_contents($resultsFile), true);
		if (isset($data['results'])) {
			$hasResults = true;

			// Dump a table of benchmark results.
			$particpants = array_keys($data['results']);

			echo '<h2>Results</h2>', "\n";
			echo '<table class="table table-striped">';

			// Participants
			echo '<thead>';
			echo '<tr>';
			echo '<th class="day">&nbsp;</th>';
			foreach ($particpants as $particpant) {
				echo '<th class="particpant">', $particpant, '</th>';
			}
			echo '</tr>', "\n";
			echo '</thead>';

			echo '<tbody>';
			// Best time for each day.
			for ($day = 1; $day <= 25; $day++) {
				// Calculate best time.
				$times = [];
				foreach ($particpants as $particpant) {
					if (isset($data['results'][$particpant]['days'][$day]['times'])) {
						$times[] = getTime($data['results'][$particpant]['days'][$day]['times'], $method);
					}
				}
				if (empty($times)) { continue; }

				sort($times);
				$best = $times[0];

				echo '<tr>';
				echo '<th class="day">Day ', $day, '</th>';

				foreach ($particpants as $particpant) {
					$time = '';
					if (isset($data['results'][$particpant]['days'][$day]['times'])) {
						$time = getTime($data['results'][$particpant]['days'][$day]['times'], $method);
					}

					echo '<td class="participant ', ($time == $best ? 'table-success' : ''), '">';
					echo formatTime($time);
					echo '</td>';
				}
				echo '</tr>', "\n";
			}

			echo '</tbody>';
			echo '</table>';
			if (isset($data['time'])) {
				echo '<p class="text-muted text-right">';
				echo '<small>Last updated: ', date('r', $data['time']), '</small>';
				echo '</p>';
			}
		}
	}

	if (!$hasResults) {
		echo 'No results yet.';
	}

	require_once(__DIR__ . '/footer.php');
