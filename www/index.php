<?php
	require_once(__DIR__ . '/functions.php');

	$pageid = 'index';
	require_once(__DIR__ . '/header.php');


	$method = isset($_REQUEST['method']) ? $_REQUEST['method'] : 'MEDIAN';

	if ($hasResults) {
		echo '<h2>Results</h2>', "\n";
		echo '<table class="table table-striped">';

		// Participants
		echo '<thead>';
		echo '<tr>';
		echo '<th class="day">&nbsp;</th>';
		foreach ($data['results'] as $participant => $pdata) {
			$link = '<a href="' . $pdata['repo'] . '"><img height="16px" width="16px" src="https://github.com/favicon.ico" alt="github"></a>';

			echo '<th class="participant">', $participant, ' ', $link, '</th>';
		}
		echo '</tr>', "\n";
		echo '</thead>';

		echo '<tbody>';

		for ($day = 1; $day <= 25; $day++) {
			$best = getDayBestTime($day, $method);
			if ($best === NULL) { continue; }

			echo '<tr>';
			echo '<th class="day">Day ', $day, '</th>';

			foreach ($data['results'] as $participant => $pdata) {
				$time = isset($pdata['days'][$day]['times']) ? getParticipantTime($pdata['days'][$day]['times'], $method) : '';

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
	} else {
		echo 'No results yet.';
	}

	require_once(__DIR__ . '/footer.php');
