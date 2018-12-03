#!/usr/bin/php
<?php
	require_once(__DIR__ . '/config.php');

	// Load old data file.
	$data = ['results' => []];
	if (file_exists($resultsFile)) {
		$data = json_decode(file_get_contents($resultsFile), true);
	}

	// Set our hardware.
	$hardware = [];
	exec('lscpu 2>&1', $hardware);
	$hardware = implode("\n", $hardware);
	$data['hardware'] = $hardware;

	$hasRun = false;

	// Save Data.
	function saveData() {
		global $data, $resultsFile, $hasRun;

		if ($hasRun && !isset($data['time'])) { $data['time'] = time(); }

		// Output results to disk.
		file_put_contents($resultsFile, json_encode($data));
	}

	// Get CLI Options.
	$__CLIOPTS = getopt('fp:d:', ['force', 'participant:', 'day:']);

	function getOptionValue($short = NULL, $long = NULL, $default = '') {
		global $__CLIOPTS;

		if ($short !== NULL && array_key_exists($short, $__CLIOPTS)) { $val = $__CLIOPTS[$short]; }
		else if ($long !== NULL && array_key_exists($long, $__CLIOPTS)) { $val = $__CLIOPTS[$long]; }
		else { $val = $default; }

		if (is_array($val)) { $val = array_pop($val); }

		return $val;
	}

	$wantedParticipant = getOptionValue('p', 'participant', '.*');
	$wantedDay = getOptionValue('d', 'day', '.*');
	$force = getOptionValue('f', 'force', NULL) !== NULL;

	// Ensure we save if we exit:
	$shutdownFunc = function() { saveData(); die(); };
	register_shutdown_function($shutdownFunc);
	pcntl_signal(SIGINT, $shutdownFunc);
	pcntl_signal(SIGTERM, $shutdownFunc);
	pcntl_async_signals(true);

	// Get input for a given day.
	function getInput($day) {
		global $participants, $participantsDir, $inputsDir;

		if (file_exists($inputsDir . '/' . $day . '.txt')) {
			$input = file_get_contents($inputsDir . '/' . $day . '.txt');
		} else {
			$source = $participants[0];
			$cwd = getcwd();
			chdir($participantsDir . '/' . $source->getName());
			$input = $source->getInput($day);
			chdir($cwd);
		}

		return $input;
	}

	foreach ($participants as $participant) {
		$person = $participant->getName();
		if (!preg_match('#^' . $wantedParticipant. '$#', $person)) { continue; }

		echo "\n", $person , ': ', "\n";

		$dir = $participantsDir . '/' . $person;

		$participant->updateRepo($dir);
		chdir($dir);

		// Prepare.
		echo 'Preparing.', "\n";
		$participant->prepare();

		if (!isset($data['results'][$person])) {
			$data['results'][$person] = [];
			$data['results'][$person]['name'] = $person;
			$data['results'][$person]['repo'] = $participant->getRepo();
			$data['results'][$person]['days'] = [];
		}

		// Run day.
		for ($day = 1; $day <= 25; $day++) {
			if (!$participant->hasDay($day)) { continue; }
			if (!preg_match('#^' . $wantedDay. '$#', $day)) { continue; }

			$thisDay = isset($data['results'][$person]['days'][$day]) ? $data['results'][$person]['days'][$day] : ['times' => []];
			echo 'Day ', $day, ':';

			if (isset($thisDay['version'])) {
				if ($thisDay['version'] == $participant->getVersion($day)) {
					if ($force) {
						echo ' [Forced]';
					} else {
						echo ' No changes.', "\n";
						continue;
					}
				}
			}

			if ($normaliseInput) {
				$input = getInput($day);
				if ($input !== FALSE) {
					$participant->setInput($day, $input);
				}
			}

			// Run the day.
			$long = false;
			$hasRun = false;

			for ($i = 0; $i < ($long ? $longRepeatCount : $repeatCount); $i++) {
				$start = time();
				$result = $participant->run($day);
				$end = time();
				usleep(500); // Sleep a bit so that we're not constantly running.

				// Output to show the day ran.
				if (!is_array($result) || empty($result)) { echo ' !'; break; } else { echo ' ', $i; }

				// If this was a long-running day, run future days less often.
				if ($end - $start > $longTimeout) { $long = true; }

				// Get the `real` time output.
				$time = $participant->extractTime($result);

				$thisDay['times'][] = $time;
				$hasRun = true;
			}
			echo "\n";

			// Update data if we've actually ran enough times.
			if ($hasRun && count($thisDay['times']) >= ($long ? $longRepeatCount : $repeatCount)) {
				sort($thisDay['times']);
				$thisDay['times']['version'] = $participant->getVersion($day);
				$data['results'][$person]['days'][$day] = $thisDay;
			}

			// Save the data.
			saveData();
		}
	}
