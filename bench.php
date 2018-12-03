#!/usr/bin/php
<?php
	require_once(__DIR__ . '/config.php');

	if (!file_exists($lockfile)) { file_put_contents($lockfile, ''); }
	$fp = fopen($lockfile, 'r+');
	if (!flock($fp, LOCK_EX | LOCK_NB)) {
		echo 'Unable to get lock on ', $lockfile, "\n";
		exit(1);
	}

	$startTime = time();
	echo 'Bench starting at: ', date('r'), "\n";

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

			$answer1 = $input !== FALSE ? getInputAnswer($day, 1) : null;
			$answer2 = $input !== FALSE ? getInputAnswer($day, 2) : null;
		} else {
			$source = $participants[0];
			$cwd = getcwd();
			$person = preg_replace('#[^A-Z0-9-_]#i', '', $source->getName());
			chdir($participantsDir . '/' . $person);
			$input = $source->getInput($day);
			$answer1 = $input !== FALSE ? $source->getInputAnswer($day, 1) : null;
			$answer2 = $input !== FALSE ? $source->getInputAnswer($day, 2) : null;
			chdir($cwd);
		}

		return [$input, $answer1, $answer2];
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
			$data['results'][$person]['days'] = [];
		}
		$data['results'][$person]['name'] = $participant->getName();
		$data['results'][$person]['repo'] = $participant->getRepo();

		// Run day.
		for ($day = 1; $day <= 25; $day++) {
			if (!$participant->hasDay($day)) { continue; }
			if (!preg_match('#^' . $wantedDay. '$#', $day)) { continue; }

			$thisDay = isset($data['results'][$person]['days'][$day]) ? $data['results'][$person]['days'][$day] : ['times' => []];
			echo 'Day ', $day, ':';

			if ($normaliseInput) {
				[$input, $answer1, $answer2] = getInput($day);
				$checkOutput = ($answer1 !== NULL && $answer2 !== NULL);
			} else {
				$input = $answer1 = $answer2 = NULL;
				$checkOutput = FALSE;
			}

			$currentVersion = isset($thisDay['version']) ? $thisDay['version'] : 'Unknown';
			$checkedOutput = isset($thisDay['checkedOutput']) ? $thisDay['checkedOutput'] : FALSE;

			// Should we skip this?
			$skip = !empty($thisDay['times']);
			$skip &= ($currentVersion == $participant->getVersion($day));
			$skip &= (!$checkOutput || $checkOutput == $checkedOutput);

			if ($force) {
				echo ' [Forced]';
				$skip = false;
			}

			if ($skip) { echo ' Up to date.', "\n"; continue; }

			if ($input !== FALSE) {
				$participant->setInput($day, $input);
			}

			// Run the day.
			$long = false;

			// Reset the times.
			$thisDay['times'] = [];
			$failedRun = false;

			for ($i = 0; $i < ($long ? $longRepeatCount : $repeatCount); $i++) {
				$start = time();
				list($ret, $result) = $participant->run($day);
				$end = time();
				usleep(500); // Sleep a bit so that we're not constantly running.

				// Output to show the day ran.
				if ($ret != 0) {
					echo ' !';
					echo "\n";
					echo 'Exited with error.', "\n";
					echo 'Output:', "\n";
					foreach ($result as $out) { echo '        > ', $out, "\n"; }
					$failedRun = true;
					break;
				} else {
					echo ' ', $i;
					if ($checkOutput) {
						$rightAnswer = preg_match('#' . preg_quote($answer1, '#') . '.+' . preg_quote($answer2, '#') . '#', implode(' ', $result));
						if (!$rightAnswer) {
							echo 'F';
							echo "\n";
							echo 'Wanted Answers:', "\n";
							echo '                 Part 1:', $answer1, "\n";
							echo '                 Part 2:', $answer2, "\n";
							echo "\n";
							echo 'Actual Output:', "\n";
							foreach ($result as $out) { echo '        > ', $out, "\n"; }
							$failedRun = true;
							break;
						}
					}
				}

				// If this was a long-running day, run future days less often.
				if ($end - $start > $longTimeout) { $long = true; }

				// Get the `real` time output.
				$time = $participant->extractTime($result);

				$thisDay['times'][] = $time;
				$hasRun = true;
			}
			echo "\n";

			// Update data if we've actually ran enough times or if we failed.
			if ($failedRun || count($thisDay['times']) >= ($long ? $longRepeatCount : $repeatCount)) {
				// Invalidate any times if we failed or sort them for later.
				if ($failedRun) {
					unset($thisDay['times']);
				} else {
					sort($thisDay['times']);
					if ($checkOutput) {
						$thisDay['checkedOutput'] = true;
					}
				}

				$thisDay['version'] = $participant->getVersion($day);
				$data['results'][$person]['days'][$day] = $thisDay;
			}

			// Save the data.
			saveData();
		}

		echo 'Cleanup.', "\n";
		$participant->cleanup();
	}

	function secondsToDuration($seconds) {
		$result = '';
		if ($seconds > 60 * 60) {
			$hours = floor($seconds / (60 * 60));
			$seconds -= $hours * 60 * 60;
			$result .= $hours . ' hour' . ($hours != 1 ? 's' : '');
		}

		if ($seconds > 60) {
			$minutes = floor($seconds / 60);
			$seconds -= $minutes * 60;
			$result .= ' ' . $minutes . ' minute' . ($minutes != 1 ? 's' : '');
		}

		$result .= ' ' . $seconds . ' second' . ($seconds != 1 ? 's' : '');

		return trim($result);
	}

	$endTime = time();
	echo "\n";
	echo 'Bench ended at: ', date('r'), "\n";
	echo 'Took: ', secondsToDuration($endTime - $startTime), "\n";
