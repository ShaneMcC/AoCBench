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
		foreach ($data['results'] as $participant => $pdata) {
			if (isset($pdata['repo'])) {
				$link = '<a href="' . $pdata['repo'] . '"><img height="16px" width="16px" src="https://github.com/favicon.ico" alt="github"></a>';
			} else {
				$link = '';
			}

			if (isset($_REQUEST['anon'])) { $participant = 'Participant ' . $p++; }
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

				if (!empty($time)) {
					$min = isset($pdata['days'][$day]['times']) ? getParticipantTime($pdata['days'][$day]['times'], 'MIN') : '';
					$max = isset($pdata['days'][$day]['times']) ? getParticipantTime($pdata['days'][$day]['times'], 'MAX') : '';

					$tooltip = 'Min: ' . formatTime($min) . '<br>' . 'Max: ' . formatTime($max);

					echo '<td class="participant time ', ($time == $best ? 'table-success' : ''), '" data-toggle="tooltip" data-placement="bottom" data-html="true" title="', htmlspecialchars($tooltip), '">';
					echo formatTime($time);
					echo '</td>';
				} else {
					echo '<td class="participant">&nbsp;</td>';
				}
			}
			echo '</tr>', "\n";
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
