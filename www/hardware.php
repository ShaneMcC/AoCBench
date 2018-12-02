<?php
	require_once(__DIR__ . '/../config.php');

	$pageid = 'hardware';
	require_once(__DIR__ . '/header.php');

	$hasResults = false;
	if (file_exists($resultsFile)) {
		$data = json_decode(file_get_contents($resultsFile), true);
		if (isset($data['results'])) {
			$hasResults = true;

			echo '<h2>Hardware</h2>', "\n";
			echo '<pre>';
			echo $data['hardware'];
			echo '</pre>';
		}
	}

	if (!$hasResults) {
		echo 'No results yet.';
	}

	require_once(__DIR__ . '/footer.php');
