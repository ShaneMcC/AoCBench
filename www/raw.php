<?php
	require_once(__DIR__ . '/functions.php');

	$pageid = 'raw';
	if (isset($_REQUEST['json'])) {
		header('Content-Type: application/json');
	} else {
		require_once(__DIR__ . '/header.php');
	}

	if ($hasResults) {
		if (isset($_REQUEST['json'])) {
			echo json_encode($data, JSON_PRETTY_PRINT);
		} else {
			echo '<h2>Raw Data</h2>', "\n";
			echo '<small><a href="raw.json">json</a></small>', "\n";
			echo '<br><br>';
			echo '<pre>';
			echo htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT));
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
