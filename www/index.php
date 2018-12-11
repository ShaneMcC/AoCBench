<?php
	require_once(__DIR__ . '/functions.php');

	$pageid = 'index';
	require_once(__DIR__ . '/header.php');


	$method = isset($_REQUEST['method']) ? $_REQUEST['method'] : 'MEDIAN';

	if ($hasResults) {
		echo '<h2>Results</h2>', "\n";

		foreach (['MEDIAN' => 'Median', 'MIN' => 'Minimum', 'Mean' => 'Mean', 'SPECIAL' => 'MeanBest', 'MAX' => 'Maximum'] as $m => $title) {
			$link = '<a href="?method=' . $m . '">' . $title . '</a>';
			if ($m == $method) { $link = '<strong>' . $link . '</strong>'; }

			$links[] = $link;
		}
		echo '<p class="text-muted text-right">';
		echo '<small>', implode(' - ', $links), '</small>';
		echo '</p>';

		echo '<table class="table table-striped">';

		// Participants
		echo '<thead>';
		echo '<tr>';
		echo '<th class="day">&nbsp;</th>';
		$p = 1;
		if (empty($displayParticipants)) { $displayParticipants = array_keys($data['results']); }
		foreach ($displayParticipants as $participant) {
			if (!isset($data['results'][$participant])) { continue; }
			$pdata = $data['results'][$participant];

			if (isset($pdata['repo'])) {
				$link = '<a href="' . $pdata['repo'] . '"><img height="16px" width="16px" src="https://github.com/favicon.ico" alt="github"></a>';
			} else {
				$link = '';
			}

			$name = $name = isset($_REQUEST['anon']) ? 'Participant ' . $p++ : $pdata['name'];
			echo '<th class="participant">', $name, ' ', $link, '</th>';
		}
		echo '</tr>', "\n";
		echo '</thead>';

		echo '<tbody>';

		$podiumPoints = ['first' => 4, 'second' => 3, 'third' => 2];
		$personPoints = [];

		for ($day = 1; $day <= 25; $day++) {
			$best = getDayBestTimes($day, $method);
			if (empty($best)) { continue; }

			$podiumTime['first'] = array_shift($best);
			$podiumTime['second'] = array_shift($best);
			$podiumTime['third'] = array_shift($best);

			echo '<tr>';
			echo '<th class="day"><a class="daylink" href="./matrix.php?day=', $day, '">Day ', $day, '</a></th>';

			foreach ($displayParticipants as $participant) {
				if (!isset($data['results'][$participant])) { continue; }
				if ($podium && !isset($podiumCounts[$participant])) { $podiumCounts[$participant] = ['first' => 0, 'second' => 0, 'third' => 0]; }

				$pdata = $data['results'][$participant];
				$time = isset($pdata['days'][$day]['times']) ? getParticipantTime($pdata['days'][$day]['times'], $method) : FALSE;

				if ($time !== FALSE) {
					$min = isset($pdata['days'][$day]['times']) ? getParticipantTime($pdata['days'][$day]['times'], 'MIN') : '';
					$max = isset($pdata['days'][$day]['times']) ? getParticipantTime($pdata['days'][$day]['times'], 'MAX') : '';

					$tooltip = 'Min: ' . formatTime($min) . '<br>' . 'Max: ' . formatTime($max);

					$classes = ['participant', 'time'];
					if ($podium) {
						$onPodium = false;
						if (!isset($personPoints[$participant])) { $personPoints[$participant] = 0; }

						foreach (['first', 'second', 'third'] as $pos) {
							if (isset($podiumTime[$pos]) && $time == $podiumTime[$pos]) {
								$classes[] = 'table-' . $pos;
								$personPoints[$participant] += $podiumPoints[$pos];
								$onPodium = true;
								break;
							}
						}
						if (!$onPodium) {
							$personPoints[$participant] += 1;
						}
					}

					if (!$podium && $time == $first) { $classes[] = 'table-best'; }

					echo '<td class="', implode(' ', $classes), '" data-ms="', $time ,'" data-toggle="tooltip" data-placement="bottom" data-html="true" title="', htmlspecialchars($tooltip), '">';
					echo formatTime($time);
					echo '</td>';
				} else {
					echo '<td class="participant">&nbsp;</td>';
				}
			}
			echo '</tr>', "\n";
		}

		if ($podium) {
			$pointsBest = array_values($personPoints);
			rsort($pointsBest);

			$points = [];
			if (!empty($pointsBest)) {
				$points['first'] = array_shift($pointsBest);
				$points['second'] = array_shift($pointsBest);
				$points['third'] = array_shift($pointsBest);
			}

			var_dump($pointsBest);

			echo '<tr><td colspan=', count($displayParticipants) + 1, '></td></tr>';
			echo '<tr>';
			echo '<th class="day">Points</th>';
			foreach ($displayParticipants as $participant) {
				$count = $personPoints[$participant];

				$classes = [];
				foreach (['first', 'second', 'third'] as $pos) {
					if (isset($points[$pos]) && $count == $points[$pos]) {
						$classes[] = 'table-' . $pos;
						break;
					}
				}

				echo '<td class="participant ', implode(' ', $classes), '">', $count, '</td>';
			}
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';

		echo '<p class="text-muted text-right">';
		if (isset($data['time'])) {
			echo '<small>Last updated: ', date('r', $data['time']), '</small>';
		}
		echo '</p>';

		echo '<script src="./index.js"></script>';
	} else {
		echo 'No results yet.';
	}

	require_once(__DIR__ . '/footer.php');
