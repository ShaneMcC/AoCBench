<?php
	require_once(__DIR__ . '/functions.php');

	$pageid = 'log';
	if (isset($_REQUEST['raw'])) {
		header('Content-Type: text/plain');
	} else {
		require_once(__DIR__ . '/header.php');
	}

	if (file_exists($logFile)) {
		if (isset($_REQUEST['raw'])) {
			echo file_get_contents($logFile);
		} else {
			echo '<h2>Last Run Log</h2>', "\n";
			echo '<small><a href="last.log">raw</a></small>', "\n";
			echo '<br><br>';
			echo '<pre>';
			echo htmlspecialchars(file_get_contents($logFile));
			echo '</pre>';
		}
	} else {
		if (isset($_REQUEST['raw'])) {
			echo '';
		} else {
			echo 'No data yet.';
		}
	}

	if (!isset($_REQUEST['raw'])) { require_once(__DIR__ . '/footer.php'); }
