#!/usr/bin/php
<?php

	require_once(__DIR__ . '/config.php');

	$hasRun = false;

	$results = [];
	if (file_exists($resultsFile)) {
		$data = json_decode(file_get_contents($resultsFile), true);
		if (isset($data['results'])) {
			$results = $data['results'];
		}
	}

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
			for ($i = 0; $i < ($long ? 4 : 10); $i++) {
				$start = time();
				$result = $participant->run($day);
				$end = time();

				// Long-Running days, run less times.
				if ($end - $start > 30) { $long = true; }
				if ($result === NULL) { echo '!'; break; } else { echo $i; }

				// Get the `real` time output.
				$time = $result[count($result) - 3];
				$time = trim(preg_replace('#^real#', '', $time));

				$results[$person]['days'][$day]['times'][] = $time;
				$hasRun = true;
			}
			echo "\n";

			if (empty($results[$person]['days'][$day]['times'])) {
				unset($results[$person]['days'][$day]);
				break;
			} else {
				sort($results[$person]['days'][$day]['times']);
				$results[$person]['days'][$day]['version'] = $participant->getVersion($day);
			}
		}
	}

	// Hardware Data
	$hardware = [];
	exec('lscpu 2>&1', $hardware);
	$hardware = implode("\n", $hardware);

	$data = [];
	$data['hardware'] = $hardware;
	$data['results'] = $results;
	if ($hasRun || !isset($data['time'])) { $data['time'] = time(); }

	// Output Results.
	file_put_contents($resultsFile, json_encode($data));
