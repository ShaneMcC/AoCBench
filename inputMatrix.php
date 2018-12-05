#!/usr/bin/php
<?php
	require_once(__DIR__ . '/functions.php');

	getLock();

	$startTime = time();
	echo 'inputMatrix starting at: ', date('r', $startTime), "\n";

	$data = loadData($outputResultsFile);

	$hasRun = false;

	// Get CLI Options.
	$__CLIOPTS = getopt('fp:d:i:h', ['force', 'participant:', 'day:', 'input:', 'help', 'no-update']);

	$noUpdate = getOptionValue(NULL, 'no-update', NULL) !== NULL;
	$wantedParticipant = getOptionValue('p', 'participant', '.*');
	$wantedDay = getOptionValue('d', 'day', '.*');
	$wantedInput = getOptionValue('i', 'input', '.*');
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
		echo '  -i, --input <regex>           Only run inputs for participants matching <regex> (This', "\n";
		echo '                                is automatically anchored start/end)', "\n";
		echo '  -p, --participant <regex>     Only look at participants matching <regex> (This', "\n";
		echo '                                is automatically anchored start/end)', "\n";
		echo '  -d, --day <regex>             Only look at days matching <regex> (This is', "\n";
		echo '                                automatically anchored start/end)', "\n";
		echo '      --no-update               Do not update repos.', "\n";
		echo '', "\n";
		echo 'If not specified, day, participant, input all default to ".*" to match all', "\n";
		echo 'participants/days/inputs.', "\n";
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
			if (empty($input)) { continue; }

			$inputs[$day][$person]['input'] = $input;
			$inputs[$day][$person]['version'] = $participant->getInputVersion($day);
			$inputs[$day][$person]['answer1'] = $participant->getInputAnswer($day, 1);
			$inputs[$day][$person]['answer2'] = $participant->getInputAnswer($day, 2);
		}

		echo 'Done.', "\n";
	}
	chdir($cwd);

	// Remove days with no inputs.
	for ($day = 1; $day <= 25; $day++) {
		$hasInputs = false;
		if (isset($inputs[$day])) {
			foreach ($inputs[$day] as $person => $input) {
				if (!empty($input['input'])) { $hasInputs = true; continue; }
			}
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
			$skip &= ($currentVersion == $participant->getDayVersion($day));
			if ($force) {
				echo ' [Forced]', "\n";
				$skip = false;
			}

			foreach ($inputs[$day] as $inputPerson => $input) {
				if (!preg_match('#^' . $wantedInput. '$#', $inputPerson)) { continue; }

				echo '        ', $inputPerson, ': ';

				$thisInputVersion = isset($thisDay['outputs'][$inputPerson]['version']) ? $thisDay['outputs'][$inputPerson]['version'] : 'Unknown';

				if ($input['answer1'] !== NULL && $input['answer2'] !== NULL && !isset($thisDay['outputs'][$inputPerson]['correct'])) {
					$skip = false;
				}

				if ($skip && $thisInputVersion == $input['version']) { echo 'Up to date.', "\n"; continue; }

				$participant->setInput($day, $input['input']);
				list($ret, $result) = $participant->run($day);
				$hasRun = true;

				$thisDay['outputs'][$inputPerson] = ['version' => $input['version'], 'return' => $ret, 'output' => $result];

				if ($input['answer1'] !== NULL && $input['answer2'] !== NULL) {
					$rightAnswer = preg_match('#' . preg_quote($input['answer1'], '#') . '.+' . preg_quote($input['answer2'], '#') . '#', implode(' ', $result));
					$thisDay['outputs'][$inputPerson]['correct'] = $rightAnswer;
				}

				echo 'Done!', "\n";
			}
			echo "\n";

			// Update data
			$thisDay['version'] = $participant->getDayVersion($day);
			$data['results'][$person]['days'][$day] = $thisDay;

			// Save the data.
			saveData($outputResultsFile, $data, $hasRun);
		}

		echo 'Cleanup.', "\n";
		$participant->cleanup();
	}



	$endTime = time();
	echo "\n";
	echo 'inputMatrix ended at: ', date('r'), "\n";
	echo 'Took: ', secondsToDuration($endTime - $startTime), "\n";
