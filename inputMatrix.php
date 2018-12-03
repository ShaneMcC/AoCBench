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
	echo 'inputMatrix starting at: ', date('r'), "\n";

	// Load old data file.
	$data = ['results' => []];
	if (file_exists($outputResultsFile)) {
		$data = json_decode(file_get_contents($outputResultsFile), true);
	}

	// Save Data.
	function saveData() {
		global $data, $outputResultsFile, $hasRun;

		if ($hasRun || !isset($data['time'])) { $data['time'] = time(); }

		// Output results to disk.
		file_put_contents($outputResultsFile, json_encode($data));
	}

	// Get CLI Options.
	$__CLIOPTS = getopt('fp:d:h', ['force', 'participant:', 'day:', 'help', 'no-update']);

	function getOptionValue($short = NULL, $long = NULL, $default = '') {
		global $__CLIOPTS;

		if ($short !== NULL && array_key_exists($short, $__CLIOPTS)) { $val = $__CLIOPTS[$short]; }
		else if ($long !== NULL && array_key_exists($long, $__CLIOPTS)) { $val = $__CLIOPTS[$long]; }
		else { $val = $default; }

		if (is_array($val)) { $val = array_pop($val); }

		return $val;
	}

	$noUpdate = getOptionValue(NULL, 'no-update', NULL) !== NULL;
	$wantedParticipant = getOptionValue('p', 'participant', '.*');
	$wantedDay = getOptionValue('d', 'day', '.*');
	$force = getOptionValue('f', 'force', NULL) !== NULL;

	if (getOptionValue('h', 'help', NULL) !== NULL) {
		echo 'AoCBench input matrix generator.', "\n";
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
		echo '      --no-update               Do not update repos.', "\n";
		echo '', "\n";
		echo 'If not specified, day and participant both default to ".*" to match all', "\n";
		echo 'participants/days.', "\n";
		die();
	}

	// Get all inputs.
	$inputs = [];
	$cwd = getcwd();
	echo 'Getting inputs.', "\n";
	foreach ($participants as $participant) {
		$person = preg_replace('#[^A-Z0-9-_]#i', '', $participant->getName());

		echo $participant->getName() , ': ', "\n";
		$dir = $participantsDir . '/' . $person;
		if (!$noUpdate) {
			chdir($cwd);
			$participant->updateRepo($dir);
		}
		chdir($dir);

		for ($day = 1; $day <= 25; $day++) {
			$input = $participant->getInput($day);
			$inputs[$day][$person]['input'] = $input;
			$inputs[$day][$person]['version'] = $participant->getVersion($day);
		}

		echo 'Done.', "\n";
	}
	chdir($cwd);

	// Remove days with no inputs.
	for ($day = 1; $day <= 25; $day++) {
		$hasInputs = false;
		foreach ($inputs[$day] as $person => $input) {
			if (!empty($input['input'])) { $hasInputs = true; continue; }
		}
		if (!$hasInputs) { unset($inputs[$day]); }
	}

	echo "\n", 'Running.', "\n";

	foreach ($participants as $participant) {
		$person = preg_replace('#[^A-Z0-9-_]#i', '', $participant->getName());
		if (!preg_match('#^' . $wantedParticipant. '$#', $person)) { continue; }

		echo "\n", $participant->getName() , ': ', "\n";
		$dir = $participantsDir . '/' . $person;
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

			$thisDay = isset($data['results'][$person]['days'][$day]) ? $data['results'][$person]['days'][$day] : ['outputs' => []];
			echo 'Day ', $day, ':', "\n";

			$currentVersion = isset($thisDay['version']) ? $thisDay['version'] : 'Unknown';

			// Should we skip this?
			$skip = !empty($thisDay['outputs']);
			$skip &= ($currentVersion == $participant->getVersion($day));
			if ($force) {
				echo ' [Forced]', "\n";
				$skip = false;
			}

			foreach ($inputs[$day] as $inputPerson => $input) {
				echo '        ', $inputPerson, ': ';

				$thisInputVersion = isset($thisDay['outputs'][$inputPerson]['version']) ? $thisDay['outputs'][$inputPerson]['version'] : 'Unknown';

				if ($skip && $thisInputVersion == $input['version']) { echo 'Up to date.', "\n"; continue; }

				$participant->setInput($day, $input['input']);
				list($ret, $result) = $participant->run($day);
				$thisDay['outputs'][$inputPerson] = ['version' => $input['version'], 'return' => $ret, 'output' => $result];
				echo 'Done!', "\n";
			}
			echo "\n";

			// Update data
			$thisDay['version'] = $participant->getVersion($day);
			$data['results'][$person]['days'][$day] = $thisDay;

			// Save the data.
			saveData();
		}

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
	echo 'inputMatrix ended at: ', date('r'), "\n";
	echo 'Took: ', secondsToDuration($endTime - $startTime), "\n";
