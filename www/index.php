<?php
	require_once(__DIR__ . '/functions.php');

	$pageid = 'index';
	$fluid = count($data['results']) > 4;
	require_once(__DIR__ . '/header.php');


	$method = isset($_REQUEST['method']) ? $_REQUEST['method'] : 'MEDIAN';
	$lang = isset($_REQUEST['lang']) ? (is_array($_REQUEST['lang']) ? $_REQUEST['lang'] : [$_REQUEST['lang']]) : True;

	if ($hasResults) {
		echo '<h2>Results</h2>', "\n";

		$langLink = '';
		if ($lang !== True) {
			foreach ($lang as $l) {
				$langLink .= '&lang[]=' . urlencode($l);
			}
		}

		foreach (['MEDIAN' => 'Median', 'MIN' => 'Minimum', 'Mean' => 'Mean', 'SPECIAL' => 'MeanBest', 'MAX' => 'Maximum'] as $m => $title) {
			$link = '<a href="?method=' . $m . $langLink . '">' . $title . '</a>';
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
		if (empty($displayParticipants)) {
			if ($lang === True) {
				$displayParticipants = array_keys($data['results']);
			} else {
				$displayParticipants = [];
				foreach ($data['results'] as $person => $pdata) {
					if (!isset($pdata['language']) || empty($pdata['language'])) { continue; }
					$langList = is_array($pdata['language']) ? $pdata['language'] : [$pdata['language']];
					foreach ($langList as $thisLang) {
						if (in_array($thisLang, $lang)) {
							$displayParticipants[] = $person;
							break;
						}
					}
				}
			}
		}
		foreach ($displayParticipants as $participant) {
			if (!isset($data['results'][$participant])) { continue; }
			$pdata = $data['results'][$participant];

			if (isset($pdata['repo']) && !empty($pdata['repo'])) {
				$link = '<a href="' . $pdata['repo'] . '"><img height="16px" width="16px" src="github.ico" alt="github"></a>';
			} else {
				$link = '';
			}

			if (isset($pdata['subheading']) && !empty($pdata['subheading'])) {
				$subheading = '<br><small>' . $pdata['subheading'] . '</small>';
			} else {
				$subheading = '';
			}
			if (isset($pdata['language']) && !empty($pdata['language'])) {
				$language = '<br><small>';
				$langList = is_array($pdata['language']) ? $pdata['language'] : [$pdata['language']];
				foreach ($langList as $l) {
					$language .= '<a href="?method=' . urlencode($method) . '&lang[]=' . urlencode($l) . '">' . $l . '</a> / ';
				}
				$language = rtrim($language, ' /');
				$language .= '</small>';
			} else {
				$language = '';
			}

			$name = $name = isset($_REQUEST['anon']) ? 'Participant ' . $p++ : $pdata['name'];
			echo '<th class="participant">', $name, ' ', $link, ' ', $subheading, ' ', $language, '</th>';
		}
		echo '</tr>', "\n";
		echo '</thead>';

		echo '<tbody>';

		$podiumPoints = ['first' => 4, 'second' => 3, 'third' => 2];
		$personPoints = [];

		$personTotalTime = [];

		$maxValidDays = 0;
		$completedDays = [];

		for ($day = 1; $day <= 25; $day++) {
			$best = getDayBestTimes($day, $method);
			if (empty($best)) { continue; }
			$maxValidDays++;

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
					if (!isset($personTotalTime[$participant])) { $personTotalTime[$participant] = 0; }
					$personTotalTime[$participant] += $time;
					$completedDays[$participant] = ($completedDays[$participant] ?? 0) + 1;

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

					if (!$podium && $time == $podiumTime['first']) { $classes[] = 'table-best'; }

					echo '<td class="', implode(' ', $classes), '" data-ms="', $time ,'" data-toggle="tooltip" data-placement="bottom" data-html="true" title="', htmlspecialchars($tooltip), '">';
					echo formatTime($time);
					echo '</td>';
				} else {
					echo '<td class="participant">&nbsp;</td>';
				}
			}
			echo '</tr>', "\n";
		}

		$allPersonPoints = $personPoints;
		$allPersonTotalTime = $personTotalTime;
		foreach ($completedDays as $participant => $days) {
			if ($days != $maxValidDays) {
				unset($personPoints[$participant]);
				unset($personTotalTime[$participant]);
			}
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

			echo '<tr><td colspan=', count($displayParticipants) + 1, '></td></tr>';
			echo '<tr>';
			echo '<th class="day">Points</th>';
			foreach ($displayParticipants as $participant) {
				$count = $allPersonPoints[$participant];

				$classes = [];
				if (isset($personPoints[$participant])) {
					foreach (['first', 'second', 'third'] as $pos) {
						if (isset($points[$pos]) && $count == $points[$pos]) {
							$classes[] = 'table-' . $pos;
							break;
						}
					}
				}

				echo '<td class="participant ', implode(' ', $classes), '">', $count, '</td>';
			}
			echo '</tr>';
		}

		$totalTimesBest = array_values($personTotalTime);
		sort($totalTimesBest);

		$totalTimes = [];
		if (!empty($totalTimesBest)) {
			$totalTimes['first'] = array_shift($totalTimesBest);
			$totalTimes['second'] = array_shift($totalTimesBest);
			$totalTimes['third'] = array_shift($totalTimesBest);
		}

		echo '<tr><td colspan=', count($displayParticipants) + 1, '></td></tr>';
		echo '<tr>';
		echo '<th class="day">Runtime</th>';
		foreach ($displayParticipants as $participant) {
			$time = $allPersonTotalTime[$participant];

			$classes = [];

			if (isset($personTotalTime[$participant])) {
				if (!$podium && $time == $totalTimes['first']) {
					$classes[] = 'table-best';
				} else if ($podium) {
					foreach (['first', 'second', 'third'] as $pos) {
						if (isset($totalTimes[$pos]) && $time == $totalTimes[$pos]) {
							$classes[] = 'table-' . $pos;
							break;
						}
					}
				}
			}

			echo '<td class="participant ', implode(' ', $classes), '">', formatTime($time), '</td>';
		}
		echo '</tr>';

		echo '</tbody>';
		echo '</table>';

		echo '<p class="text-muted text-right"><small>';
		if (isset($data['time'])) {
			echo ' <span>Last updated: ', date('r', $data['time']), '</span>';
		}
		if (!empty($logFile) && file_exists($logFile)) {
			echo ' <span><a href="log.php">log</a></span>';
		}
		echo '</small></p>';

		echo '<script src="./index.js"></script>';
	} else {
		echo 'No results yet.';
	}

	require_once(__DIR__ . '/footer.php');
