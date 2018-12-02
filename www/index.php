<?php
	require_once(__DIR__ . '/../config.php');

	$pageid = 'index';
	require_once(__DIR__ . '/header.php');

	$hasResults = false;
	if (file_exists($resultsFile)) {
		$data = json_decode(file_get_contents($resultsFile), true);
		if (isset($data['results'])) {
			$hasResults = true;

			require_once(__DIR__ . '/data.php');
		}
	}

	if (!$hasResults) {
		echo 'No results yet.';
	}

	require_once(__DIR__ . '/footer.php');
