#!/usr/bin/php
<?php

	require_once(__DIR__ . '/config.php');

	$hasRun = false;

	$data = [];
	$results = [];
	if (file_exists($resultsFile)) {
		$data = json_decode(file_get_contents($resultsFile), true);
		if (isset($data['results'])) {
			$results = $data['results'];
		}
	}

	// Hardware Data
	$hardware = [];
	exec('lscpu 2>&1', $hardware);
	$hardware = implode("\n", $hardware);
	$data['hardware'] = $hardware;

	function saveData() {
		global $data, $results, $resultsFile, $hasRun;

		$data['results'] = $results;
		if ($hasRun || !isset($data['time'])) { $data['time'] = time(); }

		// Output Results.
		file_put_contents($resultsFile, json_encode($data));
	}

	// Setup signal handlers etc.
	$shutdownFunc = function() {
		saveData();
		die();
	};
	register_shutdown_function($shutdownFunc);
	pcntl_signal(SIGINT, $shutdownFunc);
	pcntl_signal(SIGTERM, $shutdownFunc);
	pcntl_async_signals(true);

	foreach ($participants as $participant) {
		$person = $participant->getName();
		echo $person , ': ', "\n";

		$dir = $participantsDir . '/' . $person;

		if (file_exists($dir)) {
			echo 'Updating Repo.', "\n";
			chdir($dir);
			exec('git pull 2>&1');
		} else {
			echo 'Cloning Repo.', "\n";
			mkdir($dir, 0755, true);
			$output = [];
			exec('git clone ' . $participant->getRepo() . ' ' . $dir . ' 2>&1', $output);
			chdir($dir);
		}

		// Prepare.
		echo 'Preparing.', "\n";
		$participant->prepare();

		if (!isset($results[$person])) {
			$results[$person] = [];
			$results[$person]['name'] = $person;
			$results[$person]['repo'] = $participant->getRepo();
			$results[$person]['days'] = [];
		}

		// Run day.
		for ($day = 1; $day <= 25; $day++) {
			echo 'Day ', $day, ': ';

			if (isset($results[$person]['days'][$day]['version'])) {
				if ($results[$person]['days'][$day]['version'] == $participant->getVersion($day)) {
					echo 'No changes.', "\n";
					continue;
				}
			}

			$results[$person]['days'][$day] = ['times' => []];

			// Run 10 times.
			$long = false;
			$hasRun = false;
			for ($i = 0; $i < ($long ? $longRepeatCount : $repeatCount); $i++) {
				$start = time();
				$result = $participant->run($day);
				$end = time();

				// Long-Running days, run less times.
				if ($end - $start > $longTimeout) { $long = true; }
				if ($result === NULL) { echo '!'; break; } else { echo $i; }

				// Get the `real` time output.
				$time = $participant->extractTime($result);

				$results[$person]['days'][$day]['times'][] = $time;
				$hasRun = true;
			}
			echo "\n";

			// Only save if we've actually ran enough times.
			if ($hasRun && count($results[$person]['days'][$day]['times']) >= ($long ? $longRepeatCount : $repeatCount)) {
				sort($results[$person]['days'][$day]['times']);
				$results[$person]['days'][$day]['version'] = $participant->getVersion($day);

				saveData();
			} else {
				unset($results[$person]['days'][$day]);
				break;
			}
		}
	}

	saveData();
