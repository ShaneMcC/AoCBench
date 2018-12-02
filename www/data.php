<?php

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
				$times[] = $data['results'][$particpant]['days'][$day]['times'][0];
			}
		}
		if (empty($times)) { continue; }

		sort($times);
		$best = $times[0];

		echo '<tr>';
		echo '<th class="day">Day ', $day, '</th>';

		foreach ($particpants as $particpant) {
			$time = '&nbsp;';
			if (isset($data['results'][$particpant]['days'][$day]['times'])) {
				$time = $data['results'][$particpant]['days'][$day]['times'][0];
			}

			echo '<td class="participant ', ($time == $best ? 'table-success' : ''), '">';
			echo $time;
			echo '</td>';
		}
		echo '</tr>', "\n";
	}

	echo '</tbody>';
	echo '</table>';
