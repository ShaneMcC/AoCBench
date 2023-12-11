<?php
	require_once(__DIR__ . '/functions.php');

	$pageid = 'health';
	require_once(__DIR__ . '/header.php');

	if ($hasHealthCheck) {
		echo '<h2>Health Check</h2>', "\n";

        foreach ($data['healthcheck'] as $person => $health) {
            echo '<h3 id="', $person, '">', $health['name'], '</h3>';

            echo '<pre>';
            echo json_encode($health, JSON_PRETTY_PRINT);
            echo '</pre>';
        }

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
