<?php
	require_once(__DIR__ . '/functions.php');

	$pageid = 'raw';
	if (isset($_REQUEST['json'])) {
		header('Content-Type: application/json');
	} else {
		require_once(__DIR__ . '/header.php');
	}

	if ($hasResults) {
		if (isset($_REQUEST['timesOnly'])) {
			$dumpData = [];
			foreach ($data['results'] as $particpant => $pdata) {
				$dumpData[$particpant] = ['days' => []];
				foreach ($pdata['days'] as $day => $ddata) {
					$dumpData[$particpant]['days'][$day] = [];
					$dumpData[$particpant]['days'][$day]['times'] = array_map(function($t) use ($timeFormat) {
						$time = parseTime($t);
						if (isset($_REQUEST['includeFormat'])) {
							return ['value' => $time, 'format' => formatTime($time, $timeFormat)];
						} else {
							return $time;
						}
					}, $ddata['times']);
					foreach (['min', 'mean', 'median', 'max', 'stddev'] as $timeType) {
						$dumpData[$particpant]['days'][$day][$timeType] = getParticipantTime($ddata['times'], $timeType);
					}
				}
			}
		} else {
			$dumpData = $data;
		}

		if (isset($_REQUEST['json'])) {
			echo json_encode($dumpData, JSON_PRETTY_PRINT);
		} else {
			echo '<h2>Raw Data</h2>', "\n";
			echo '<small><a href="raw.json">json</a></small>', "\n";
			echo '<br><br>';
			echo '<pre>';
			echo htmlspecialchars(json_encode($dumpData, JSON_PRETTY_PRINT));
			echo '</pre>';
		}
	} else {
		if (isset($_REQUEST['json'])) {
			echo '[]';
		} else {
			echo 'No results yet.';
		}
	}

	if (!isset($_REQUEST['json'])) { require_once(__DIR__ . '/footer.php'); }
