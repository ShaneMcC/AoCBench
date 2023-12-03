<?php
	require_once(__DIR__ . '/functions.php');

	$pageid = 'matrix';
	$fluid = true;
	require_once(__DIR__ . '/header.php');

	$lang = isset($_REQUEST['lang']) ? (is_array($_REQUEST['lang']) ? $_REQUEST['lang'] : [$_REQUEST['lang']]) : True;

	if ($hasMatrix) {
		for ($day = 1; $day <= 25; $day++) {
			// Build day matrix.
			$dayMatrix = [];

			$dayParticipants = [];

			if (empty($displayParticipants)) {
				if ($lang === True) {
					$displayParticipants = array_keys($matrix['results']);
				} else {
					$displayParticipants = [];
					foreach ($matrix['results'] as $person => $pdata) {
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
				if (!isset($matrix['results'][$participant])) { continue; }
				$dayParticipants[] = $participant;
			}

			$dayInputs = $dayParticipants;

			$hasDay = false;
			foreach ($dayParticipants as $p1) {
				if (isset($matrix['results'][$p1]['days'][$day])) {
					foreach (array_keys($matrix['results'][$p1]['days'][$day]['outputs']) as $p2) {
						$hasDay = true;
						$dayMatrix[$p1][$p2] = $matrix['results'][$p1]['days'][$day]['outputs'][$p2];

						if (!in_array($p2, $dayInputs)) {
							$dayInputs[] = $p2;
						}
					}
				}
			}

			if (isset($_REQUEST['day'])) {
				if ($_REQUEST['day'] == $day) {
					$hasDay = true;
				} else {
					continue;
				}
			}

			if (!$hasDay) { continue; }

			echo '<h2>Day ', $day, '</h2>', "\n";
			if (isset($leaderboardYear) && !empty($leaderboardYear)) {
				echo '<a href="https://adventofcode.com/', (isset($leaderboardYear) ? $leaderboardYear : date('Y')), '/day/', $day, '">Problem</a><br><br>';
			}

			echo '<table class="table table-striped table-bordered">';
			// Participants
			echo '<thead>';
			echo '<tr>';
			echo '<th class="who">Input \ Participant</th>';
			$p = 1;
			foreach ($dayParticipants as $participant) {
				$pdata = $matrix['results'][$participant];

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
				echo '<th class="output">', $name, ' ', $link, ' ', $subheading, ' ', $language, '</th>';
			}
			echo '</tr>', "\n";

			$p = 1;
			foreach ($dayInputs as $p2) {
				echo '<tr>';
				if (isset($matrix['results'][$p2])) {
					$pdata = $matrix['results'][$p2];
					$name = isset($_REQUEST['anon']) ? 'Participant ' . $p++ : $pdata['name'];
				} else {
					$name = ucwords(preg_replace('#^custom-#', 'custom input: ', $p2));
				}
				echo '<th class="who">', $name, '</th>';
				foreach ($dayParticipants as $p1) {
					$classes = ['output'];

					if (isset($dayMatrix[$p1][$p2])) {
						if ($dayMatrix[$p1][$p2]['return'] != '0') {
							$classes[] = 'table-warning';
						} else if ($p1 == $p2) {
							$classes[] = 'table-primary';
							if (isset($dayMatrix[$p1][$p2]['correct']) && !$dayMatrix[$p1][$p2]['correct']) {
								$classes[] = 'table-danger';
							}
						} else if (isset($dayMatrix[$p1][$p2]['correct']) && $dayMatrix[$p1][$p2]['correct']) {
							$classes[] = 'table-success';
						} else if (isset($dayMatrix[$p1][$p2]['correct']) && !$dayMatrix[$p1][$p2]['correct']) {
							$classes[] = 'table-danger';
						}
					} else {
						$classes[] = 'table-secondary';
					}

					$output = isset($dayMatrix[$p1][$p2]['output']) ? implode("\n", $dayMatrix[$p1][$p2]['output']) : '';
					echo '<td class="', implode(' ', $classes),'"><pre>', htmlspecialchars($output), '</pre></td>';
				}
				echo '</tr>';
			}

			echo '</thead>';

			echo '<tbody>';
			echo '</tbody>';
			echo '</table>';
		}

		echo '<p class="text-muted text-right"><small>';
		if (isset($matrix['time'])) {
			echo ' <span>Last updated: ', date('r', $matrix['time']), '</span>';
		}
		if (!empty($logFile) && file_exists($logFile)) {
			echo ' <span><a href="log.php">log</a></span>';
		}
		echo '</small></p>';

		echo '<script src="./index.js"></script>';
	} else {
		echo 'No data yet.';
	}

	require_once(__DIR__ . '/footer.php');
