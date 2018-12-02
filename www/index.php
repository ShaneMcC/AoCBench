<?php
	require_once(__DIR__ . '/../config.php');

	if (!file_exists($resultsFile)) { die('No results yet.'); }

	$data = json_decode(file_get_contents($resultsFile), true);

	// Dump a table of benchmark results.
	$particpants = array_keys($data['results']);

	echo '<h2>Results</h2>', "\n";
	echo '<table>';

	// Participants
	echo '<tr>';
	echo '<th>&nbsp;</th>';
	foreach ($particpants as $particpant) {
		echo '<th>', $particpant, '</th>';
	}
	echo '</tr>', "\n";

	// Best time for each day.
	for ($day = 1; $day <= 25; $day++) {
		echo '<tr>';
		echo '<th>Day ', $day, '</th>';

		foreach ($particpants as $particpant) {
			echo '<td>';
			if (isset($data['results'][$particpant]['days'][$day])) {
				echo $data['results'][$particpant]['days'][$day][0];
			} else {
				echo '&nbsp;';
			}
			echo '</td>';
		}
		echo '</tr>', "\n";
	}

	echo '</table>';

	echo '<h2>Hardware</h2>', "\n";
	echo '<pre>';
	echo $data['hardware'];
	echo '</pre>';

	echo '<h2>Raw Data</h2>', "\n";
	echo '<pre>';
	echo htmlspecialchars(json_encode($data['results'], JSON_PRETTY_PRINT));
	echo '</pre>';
