<?php
	require_once(__DIR__ . '/functions.php');

	$pageid = 'matrix';
	$fluid = true;
	require_once(__DIR__ . '/header.php');


	if ($hasMatrix) {
		for ($day = 1; $day <= 25; $day++) {

			// Build day matrix.
			$dayMatrix = [];
			$dayParticipants = array_keys($matrix['results']);

			$hasDay = false;
			foreach ($dayParticipants as $p1) {
				foreach ($dayParticipants as $p2) {
					if (isset($matrix['results'][$p1]['days'][$day]['outputs'][$p2])) {
						$hasDay = true;

						$dayMatrix[$p1][$p2] = $matrix['results'][$p1]['days'][$day]['outputs'][$p2];
					}
				}
			}

			if (!$hasDay) { continue; }

			echo '<h2>Day ', $day, '</h2>', "\n";

			echo '<table class="table table-striped table-bordered">';
			// Participants
			echo '<thead>';
			echo '<tr>';
			echo '<th class="who">Input \ Participant</th>';
			$p = 1;
			foreach ($dayParticipants as $participant) {
				$pdata = $matrix['results'][$participant];

				if (isset($pdata['repo'])) {
					$link = '<a href="' . $pdata['repo'] . '"><img height="16px" width="16px" src="https://github.com/favicon.ico" alt="github"></a>';
				} else {
					$link = '';
				}

				if (isset($_REQUEST['anon'])) { $participant = 'Participant ' . $p++; }
				echo '<th class="output">', $participant, ' ', $link, '</th>';
			}
			echo '</tr>', "\n";

			foreach ($dayParticipants as $p2) {
				echo '<tr>';
				echo '<th class="who">', $p2, '</th>';
				foreach ($dayParticipants as $p1) {
					$classes = ['output'];

					if (isset($dayMatrix[$p1][$p2])) {
						if ($dayMatrix[$p1][$p2]['return'] != '0') {
							$classes[] = 'table-warning';
						} else if ($p1 == $p2) {
							$classes[] = 'table-primary';
						} else if (isset($dayMatrix[$p1][$p2]['correct']) && $dayMatrix[$p1][$p2]['correct']) {
							$classes[] = 'table-success';
						} else if (isset($dayMatrix[$p1][$p2]['correct']) && !$dayMatrix[$p1][$p2]['correct']) {
							$classes[] = 'table-danger';
						}
					}

					echo '<td class="', implode(' ', $classes),'"><pre>', htmlspecialchars(implode("\n", $dayMatrix[$p1][$p2]['output'])), '</pre></td>';
				}
				echo '</tr>';
			}

			echo '</thead>';

			echo '<tbody>';
			echo '</tbody>';
			echo '</table>';
		}

		echo '<p class="text-muted text-right">';
		if (isset($data['time'])) {
			echo '<small>Last updated: ', date('r', $data['time']), '</small>';
		}
		echo '</p>';

		echo '<script src="./index.js"></script>';
	} else {
		echo 'No data yet.';
	}

	require_once(__DIR__ . '/footer.php');
