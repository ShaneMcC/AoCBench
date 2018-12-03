<?php
	require_once(__DIR__ . '/functions.php');

	$pageid = 'hardware';
	require_once(__DIR__ . '/header.php');

	if ($hasResults) {
		echo '<h2>Hardware</h2>', "\n";
		echo '<pre>';
		echo $data['hardware'];
		echo '</pre>';
	} else {
		echo 'No results yet.';
	}

	require_once(__DIR__ . '/footer.php');
