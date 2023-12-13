#!/usr/bin/php
<?php
	require_once(__DIR__ . '/functions.php');

	// Get CLI Options.
	$__CLIOPTS = getopt('fp:d:i:h', ['force', 'participant:', 'day:', 'input:', 'help', 'no-update', 'remove', 'debug', 'no-lock', 'dryrun']);

	$noUpdate = getOptionValue(NULL, 'no-update', NULL) !== NULL;
	$wantedParticipant = getOptionValue('p', 'participant', '.*');
	$wantedDay = getOptionValue('d', 'day', '.*');
	$wantedInput = getOptionValue('i', 'input', '.*');
	$force = getOptionValue('f', 'force', NULL) !== NULL;
	$removeMatching = getOptionValue(NULL, 'remove', NULL) !== NULL;
	$runDebugMode = getOptionValue(NULL, 'debug', NULL) !== NULL;
	$noLock = getOptionValue(NULL, 'no-lock', NULL) !== NULL;
	$dryRun = getOptionValue(NULL, 'dryrun', NULL) !== NULL;

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
		echo '      --remove                  Remove matching.', "\n";
		echo '      --debug                   Enable extra debugging in various places.', "\n";
		echo '      --no-lock                 Disable grabbing lock file lock.', "\n";
		echo '      --dryrun                  Do not save result to disk.', "\n";
		echo '', "\n";
		echo 'If not specified, day, participant, input all default to ".*" to match all', "\n";
		echo 'participants/days/inputs.', "\n";
		die();
	}

	if (!$noLock) {	getLock(); } else { echo 'Skipping lock file acquire', "\n"; }
	if ($dryRun) { echo 'Dry run enabled.', "\n"; }

	$startTime = time();
	echo 'inputMatrix starting at: ', date('r', $startTime), "\n";

	$data = loadData($outputResultsFile);
	$data['starttime'] = time();

	$hasRun = false;

	// Ensure we save if we exit:
	$shutdownFunc = function() {
		global $outputResultsFile, $data, $hasRun;
		saveData($outputResultsFile, $data, $hasRun);
		die();
	};

	register_shutdown_function($shutdownFunc);
	pcntl_signal(SIGINT, $shutdownFunc);
	pcntl_signal(SIGTERM, $shutdownFunc);
	pcntl_async_signals(true);


	// Get all inputs.
	$inputs = [];
	$cwd = getcwd();
	echo 'Getting inputs.', "\n";
	foreach ($participants as $participant) {
		$person = $participant->getDirName(false);

		echo $participant->getName() , ': ', "\n";
		$dir = $participantsDir . '/' . $person;
		if (!$noUpdate) {
			chdir($cwd);
			if (!$participant->updateRepo($dir)) {
				echo 'Failed to clone/update repo.', "\n";
				continue;
			}
		}
		chdir($dir);

		$valid = $participant->isValidParticipant();
		if ($valid !== true) {
			echo 'Repo not valid: ', $valid, "\n";
		}

		for ($day = 1; $day <= 25; $day++) {
			$input = $participant->getInput($day);
			if (empty($input)) { continue; }
			if (in_array($day, $participant->getIgnoredInputs())) { continue; }

			$thisIgnoreResult = array_merge($ignoreResult, $participant->getIgnoredAnswers());

			$inputs[$day][$person]['input'] = $input;
			$inputs[$day][$person]['version'] = $participant->getInputVersion($day);
			if (in_array($day . '', $thisIgnoreResult)) {
				$inputs[$day][$person]['answer1'] = null;
				$inputs[$day][$person]['answer2'] = null;
			} else {
				$inputs[$day][$person]['answer1'] = in_array($day . '/1', $thisIgnoreResult) ? '' : $participant->getInputAnswer($day, 1);
				$inputs[$day][$person]['answer2'] = in_array($day . '/2', $thisIgnoreResult) ? '' : $participant->getInputAnswer($day, 2);
			}
		}

		echo 'Done.', "\n";
	}

	// Load any custom inputs.
	for ($day = 1; $day <= 25; $day++) {
		$customDir = $inputsDir . '/custom/' . $day;
		if (file_exists($customDir)) {
			foreach (scandir($customDir) as $dir) {
				if ($dir[0] == '.') { continue; }
				if (file_exists($customDir . '/' . $dir . '/input.txt') && file_exists($customDir . '/' . $dir . '/answers.txt')) {
					$answers = file($customDir . '/' . $dir . '/answers.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
					$input = file_get_contents($customDir . '/' . $dir . '/input.txt');
					if (!isset($answers[1])) { continue; }

					$inputs[$day]['custom-' . $dir]['input'] = $input;
					$inputs[$day]['custom-' . $dir]['version'] = sha1($input . implode("\n", $answers));

					if (in_array($day . '', $ignoreResult)) {
						$inputs[$day]['custom-' . $dir]['answer1'] = null;
						$inputs[$day]['custom-' . $dir]['answer2'] = null;
					} else {
						$inputs[$day]['custom-' . $dir]['answer1'] = in_array($day . '/1', $ignoreResult) ? '' : $answers[0];
						$inputs[$day]['custom-' . $dir]['answer2'] = in_array($day . '/2', $ignoreResult) ? '' : $answers[1];
					}
				}
			}
		}
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
		$person = $participant->getDirName(false);
		if (!preg_match('#^' . $wantedParticipant. '$#', $person)) { continue; }

		echo "\n", $participant->getName() , ': ', "\n";
		$dir = $participantsDir . '/' . $person;
		chdir($dir);

		$valid = $participant->isValidParticipant();
		if ($valid !== true) {
			echo 'Repo not valid: ', $valid, "\n";
		}

		// Prepare.
		echo 'Preparing.', "\n";
		$prepResult = $participant->prepare();
		if ($prepResult !== true) {
			echo "\n=[Failed to prepare]=========\n", implode("\n", $prepResult), "\n==========\n";
			continue;
		}

		if (!isset($data['results'][$person])) {
			$data['results'][$person] = [];
			$data['results'][$person]['days'] = [];
		}
		$data['results'][$person]['name'] = $participant->getName();
		$data['results'][$person]['repo'] = $participant->getRepo();
		$data['results'][$person]['subheading'] = $participant->getSubheading();
		$data['results'][$person]['language'] = $participant->getLanguage();

		// Run day.
		for ($day = 1; $day <= 25; $day++) {
			if (!$participant->hasDay($day) || $participant->isWIP($day) || in_array($day, $participant->getIgnored())) {
				// If this day no longer exists, remove it.
				if (isset($data['results'][$person]['days'][$day])) {
					echo 'Removing missing/wip/ignored day ', $day, '.', "\n";
				}
				unset($data['results'][$person]['days'][$day]);
				continue;
			}
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

			$needsRunOnce = true;
			foreach ($inputs[$day] as $inputPerson => $input) {
				if (!preg_match('#^' . $wantedInput. '$#', $inputPerson)) { continue; }

				echo '        ', $inputPerson, ': ';

				if ($removeMatching) {
					echo 'Removing.', "\n";
					unset($thisDay['outputs'][$inputPerson]);
					continue;
				}

				$thisInputVersion = isset($thisDay['outputs'][$inputPerson]['version']) ? $thisDay['outputs'][$inputPerson]['version'] : 'Unknown';

				if ($input['answer1'] !== NULL && $input['answer2'] !== NULL && !isset($thisDay['outputs'][$inputPerson]['correct'])) {
					$skip = false;
				}

				if ($skip && $thisInputVersion == $input['version']) { echo 'Up to date.', "\n"; continue; }

				$participant->setInput($day, $input['input']);
				if ($needsRunOnce) {
					list($ret, $result) = $participant->runOnce($day);
					$needsRunOnce = False;

					if ($ret != 0) {
						echo 'RunOnce error.', "\n";
						break;
					}
				}
				list($ret, $result) = $participant->run($day);
				usleep($sleepTime); // Sleep a bit so that we're not constantly running.
				$hasRun = true;

				if ($ret == 124) {
					// We timed-out, abort.
					echo 'Timeout.', "\n";
					break;
				}

				if ($ret == 0) {
					$thisDay['outputs'][$inputPerson] = ['version' => $input['version'], 'return' => $ret, 'output' => $result, 'time' => time()];

					if ($input['answer1'] !== NULL && $input['answer2'] !== NULL) {
						$rightAnswer = preg_match('#' . preg_quote($input['answer1'], '#') . '.+' . preg_quote($input['answer2'], '#') . '#i', implode(' ', $result));
						$thisDay['outputs'][$inputPerson]['correct'] = $rightAnswer;
					}
				}

				echo 'Done - ', ($rightAnswer ? 'correct' : ($ret == 0 ? 'wrong' : 'error')), '!', "\n";
			}

			// Clear out any inputs that no longer exist
			foreach (array_keys($thisDay['outputs']) as $inputName) {
				if (!isset($inputs[$day][$inputName])) {
					echo '        ', $inputName, ': Removed.', "\n";
					unset($thisDay['outputs'][$inputName]);
				}
			}

			echo "\n";

			// Update data
			$thisDay['version'] = $participant->getDayVersion($day);
			$thisDay['time'] = time();
			$data['results'][$person]['days'][$day] = $thisDay;

			// Save the data.
			saveData($outputResultsFile, $data, $hasRun);
		}

		echo 'Cleanup.', "\n";
		$participant->cleanup();

		// Save the data.
		saveData($outputResultsFile, $data, $hasRun);
	}

	// Save the data.
	$data['finishtime'] = time();
	saveData($outputResultsFile, $data, $hasRun);

	$endTime = time();
	echo "\n";
	echo 'inputMatrix ended at: ', date('r'), "\n";
	echo 'Took: ', secondsToDuration($endTime - $startTime), "\n";
