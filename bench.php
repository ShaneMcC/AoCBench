#!/usr/bin/php
<?php

	require_once(__DIR__ . '/config.php');

	$results = [];

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
		$participant->prepare();

		$results[$person] = [];
		$results[$person]['name'] = $person;
		$results[$person]['repo'] = $participant->getRepo();
		$results[$person]['days'] = [];

		// Run day.
		for ($day = 1; $day <= 25; $day++) {
			echo 'Day ', $day, ': ';

			$results[$person]['days'][$day] = [];

			// Run 10 times.
			for ($i = 0; $i < 10; $i++) {
				$result = $participant->run($day);
				if ($result === NULL) { echo '!'; break; } else { echo $i; }

				// Get the `real` time output.
				$time = $result[count($result) - 3];
				$time = trim(preg_replace('#^real#', '', $time));

				$results[$person]['days'[]$day][] = $time;
			}
			echo "\n";

			if (empty($results[$person]['days'][$day])) {
				unset($results[$person]['days'][$day]);
				break;
			} else {
				sort($results[$person]['days'][$day]);
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

	// Output Results.
	file_put_contents($resultsFile, json_encode($data));
