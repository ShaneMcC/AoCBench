#!/usr/bin/php
<?php
	require_once(__DIR__ . '/config.php');

	if (!file_exists($lockfile)) { file_put_contents($lockfile, ''); }
	$fp = fopen($lockfile, 'r+');
	if (!flock($fp, LOCK_EX | LOCK_NB)) {
		echo 'Unable to get lock on ', $lockfile, "\n";
		exit(1);
	}

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

		if ($hasRun || !isset($data['time'])) { $data['time'] = time(); }

		// Output results to disk.
		file_put_contents($resultsFile, json_encode($data));
	}

	// Get CLI Options.
	$__CLIOPTS = getopt('fp:d:h', ['force', 'participant:', 'day:', 'help']);

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

	if (getOptionValue('h', 'help', NULL) !== NULL) {
		echo 'AoCBench Benchmarker.', "\n";
		echo "\n";
		echo 'Usage: ', $_SERVER['argv'][0], ' [options]', "\n";
		echo '', "\n";
		echo 'Valid options:', "\n";
		echo '  -h, --help                    Show this help output', "\n";
		echo '  -f, --force                   Force run matching participants/days that would', "\n";
		echo '                                otherwise be ignored due to lack of changes.', "\n";
		echo '  -p, --participant <regex>     Only look at participants matching <regex> (This', "\n";
		echo '                                is automatically anchored start/end)', "\n";
		echo '  -d, --day <regex>             Only look at days matching <regex> (This is', "\n";
		echo '                                automatically anchored start/end)', "\n";
		echo '', "\n";
		echo 'If not specified, day and participant both default to ".*" to match all', "\n";
		echo 'participants/days.', "\n";
		die();
	}

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
			$person = preg_replace('#[^A-Z0-9-_]#i', '', $source->getName());
			chdir($participantsDir . '/' . $person);
			$input = $source->getInput($day);
			chdir($cwd);
		}

		return $input;
	}

	foreach ($participants as $participant) {
		$person = preg_replace('#[^A-Z0-9-_]#i', '', $participant->getName());
		if (!preg_match('#^' . $wantedParticipant. '$#', $person)) { continue; }

		echo "\n", $participant->getName() , ': ', "\n";

		$dir = $participantsDir . '/' . $person;
		$participant->updateRepo($dir);
		chdir($dir);

		// Prepare.
		echo 'Preparing.', "\n";
		$participant->prepare();

		if (!isset($data['results'][$person])) {
			$data['results'][$person] = [];
			$data['results'][$person]['name'] = $participant->getName();
			$data['results'][$person]['repo'] = $participant->getRepo();
			$data['results'][$person]['days'] = [];
		}

		// Run day.
		for ($day = 1; $day <= 25; $day++) {
			if (!$participant->hasDay($day)) { continue; }
			if (!preg_match('#^' . $wantedDay. '$#', $day)) { continue; }

			$thisDay = isset($data['results'][$person]['days'][$day]) ? $data['results'][$person]['days'][$day] : ['times' => [], 'version' => 'Unknown'];
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

			// Reset the times.
			$thisDay['times'] = [];

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
			if (count($thisDay['times']) >= ($long ? $longRepeatCount : $repeatCount)) {
				sort($thisDay['times']);
				$thisDay['version'] = $participant->getVersion($day);
				$data['results'][$person]['days'][$day] = $thisDay;
			}

			// Save the data.
			saveData();
		}

		$participant->cleanup();
	}
