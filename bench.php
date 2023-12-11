#!/usr/bin/php
<?php
	require_once(__DIR__ . '/functions.php');

	getLock();

	$startTime = time();
	echo 'Bench starting at: ', date('r', $startTime), "\n";

	$data = loadData($resultsFile);

	// Set our hardware.
	$hardware = [];
	exec('lscpu 2>&1', $hardware);
	$hardware = implode("\n", $hardware);
	$data['hardware'] = $hardware;

	if (!isset($data['healthcheck'])) { $data['healthcheck'] = []; }

	$hasRun = false;

	// Get CLI Options.
	$__CLIOPTS = getopt('fp:d:h', ['force', 'participant:', 'day:', 'help', 'no-update', 'no-hyperfine', 'remove', 'debug']);

	$noUpdate = getOptionValue(NULL, 'no-update', NULL) !== NULL;
	$wantedParticipant = getOptionValue('p', 'participant', '.*');
	$wantedDay = getOptionValue('d', 'day', '.*');
	$force = getOptionValue('f', 'force', NULL) !== NULL;
	$removeMatching = getOptionValue(NULL, 'remove', NULL) !== NULL;
	$noHyperfine = getOptionValue(NULL, 'no-hyperfine', NULL) !== NULL;
	$runDebugMode = getOptionValue(NULL, 'debug', NULL) !== NULL;

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
		echo '      --no-update               Do not update repos.', "\n";
		echo '      --no-hyperfine            Do not use hyperfine even if available.', "\n";
		echo '      --remove                  Remove matching.', "\n";
		echo '      --debug                   Enable extra debugging in various places.', "\n";
		echo '', "\n";
		echo 'If not specified, day and participant both default to ".*" to match all', "\n";
		echo 'participants/days.', "\n";
		die();
	}

	// Ensure we save if we exit:
	$shutdownFunc = function() {
		global $resultsFile, $data, $hasRun;
		saveData($resultsFile, $data, $hasRun);
		die();
	};

	register_shutdown_function($shutdownFunc);
	pcntl_signal(SIGINT, $shutdownFunc);
	pcntl_signal(SIGTERM, $shutdownFunc);
	pcntl_async_signals(true);

	// Get input for a given day.
	function getInput($day) {
		global $participants, $participantsDir, $inputsDir, $ignoreResult;

		if (file_exists($inputsDir . '/' . $day . '.txt')) {
			$input = file_get_contents($inputsDir . '/' . $day . '.txt');

			$answer1 = $input !== FALSE ? getInputAnswer($day, 1) : null;
			$answer2 = $input !== FALSE ? getInputAnswer($day, 2) : null;
		} else {
			$source = $participants[0];
			$cwd = getcwd();
			$person = $source->getDirName(false);
			chdir($participantsDir . '/' . $person);
			$input = $source->getInput($day);
			$answer1 = $input !== FALSE ? $source->getInputAnswer($day, 1) : null;
			$answer2 = $input !== FALSE ? $source->getInputAnswer($day, 2) : null;

			if (in_array($day . '', $ignoreResult)) { $answer1 = NULL; $answer2 = NULL; }
			if (in_array($day . '/1', $ignoreResult)) { $answer1 = ''; }
			if (in_array($day . '/2', $ignoreResult)) { $answer2 = ''; }

			chdir($cwd);
		}

		return [$input, $answer1, $answer2];
	}

	foreach ($participants as $participant) {
		$person = $participant->getDirName(false);
		if (!preg_match('#^' . $wantedParticipant. '$#', $person)) { continue; }

		echo "\n", $participant->getName() , ': ', "\n";

		if (!isset($data['healthcheck'][$person])) { $data['healthcheck'][$person] = []; }
		$data['healthcheck'][$person]['name'] = $participant->getName();
		$data['healthcheck'][$person]['repo'] = $participant->getRepo();
		$data['healthcheck'][$person]['branch'] = $participant->getBranch();
		$data['healthcheck'][$person]['dirname'] = $person;

		$dir = $participantsDir . '/' . $person;
		if (!$noUpdate) {
			if (!$participant->updateRepo($dir)) {
				echo 'Failed to clone/update repo.', "\n";
				continue;
			}
		}
		chdir($dir);

		$valid = $participant->isValidParticipant();
		$data['healthcheck'][$person]['valid'] = ($valid === true);
		if ($valid !== true) {
			$data['healthcheck'][$person]['valid_info'] = $valid;
			echo 'Repo not valid: ', $valid, "\n";
			continue;
		}

		// Prepare.
		echo 'Preparing.', "\n";
		$prepResult = $participant->prepare();
		$data['healthcheck'][$person]['prepared'] = ($prepResult === true);
		if ($prepResult !== true) {
			$data['healthcheck'][$person]['prepare_info'] = implode("\n", $prepResult);
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

		if ($participant instanceof V2Participant) {
			$data['healthcheck'][$person]['participanttype'] = 2;
			$data['healthcheck'][$person]['config'] = $participant->getAOCBenchConfig();
			$data['healthcheck'][$person]['image'] = $participant->getImageInfo();
		} else {
			$data['healthcheck'][$person]['participanttype'] = 1;
			$data['healthcheck'][$person]['config'] = [];
			$data['healthcheck'][$person]['config']['subheading'] = $participant->getSubheading();
			$data['healthcheck'][$person]['config']['language'] = $participant->getLanguage();
			$data['healthcheck'][$person]['config']['daypath'] = $participant->getDayFilename();
			$data['healthcheck'][$person]['config']['inputfile'] = $participant->getInputFilename(1);
			$data['healthcheck'][$person]['config']['answerfile'] = $participant->getInputAnswerFilename(1);
		}

		// Run day.
		for ($day = 1; $day <= 25; $day++) {
			if (!isset($data['healthcheck'][$person]['days'])) { $data['healthcheck'][$person]['days'] = []; }
			if (!isset($data['healthcheck'][$person]['days'][$day])) { $data['healthcheck'][$person]['days'][$day] = []; }
			$data['healthcheck'][$person]['days'][$day]['version'] = $participant->getDayVersion($day);
			$data['healthcheck'][$person]['days'][$day]['exists'] = $participant->hasDay($day);
			$data['healthcheck'][$person]['days'][$day]['wip'] = $participant->isWIP($day);
			$data['healthcheck'][$person]['days'][$day]['ignored'] = in_array($day, $participant->getIgnored());

			if (!$participant->hasDay($day) || $participant->isWIP($day) || in_array($day, $participant->getIgnored())) {
				// If this day no longer exists, remove it.
				if (isset($data['results'][$person]['days'][$day])) {
					echo 'Removing missing/wip/ignored day ', $day, '.', "\n";
					$data['healthcheck'][$person]['days'][$day]['log'] = 'Removed';
					$data['healthcheck'][$person]['days'][$day]['logtime'] = time();
				} else {
					$data['healthcheck'][$person]['days'][$day]['log'] = 'Not run - missing/wip/ignored.';
					$data['healthcheck'][$person]['days'][$day]['logtime'] = time();
				}
				unset($data['results'][$person]['days'][$day]);

				unset($data['healthcheck'][$person]['days'][$day]['input']);
				unset($data['healthcheck'][$person]['days'][$day]['answers']);
				unset($data['healthcheck'][$person]['days'][$day]['runtype']);
				unset($data['healthcheck'][$person]['days'][$day]['length']);
				continue;
			}
			if (!preg_match('#^' . $wantedDay. '$#', $day)) { continue; }

			$data['healthcheck'][$person]['days'][$day]['input'] = ['version' => $participant->getInputVersion($day, false)];
			$answers = [$participant->getInputAnswer($day, 1), $participant->getInputAnswer($day, 2)];
			$data['healthcheck'][$person]['days'][$day]['answers'] = ['version' => $participant->getInputAnswerVersion($day, false),
			                                                      'part1' => (isset($answers[0]) && !empty($answers[0])),
																  'part2' => (isset($answers[1]) && !empty($answers[1]))];

			$thisDay = $data['results'][$person]['days'][$day] ?? ['times' => []];
			echo 'Day ', $day, ':';

			if ($removeMatching) {
				echo 'Removing.', "\n";
				unset($data['results'][$person]['days'][$day]);
				continue;
			}

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
			$skip &= ($currentVersion == $participant->getDayVersion($day));
			$skip &= (!$checkOutput || $checkOutput == $checkedOutput);

			if ($force) {
				echo ' [Forced]';
				$skip = false;
			}

			if ($skip) { echo ' Up to date.', "\n"; continue; }

			$data['healthcheck'][$person]['days'][$day]['log'] = '';
			$data['healthcheck'][$person]['days'][$day]['logtime'] = time();

			if ($input !== FALSE && $input !== NULL && $input !== '') {
				$participant->setInput($day, $input);
			}

			// Run the day.
			$long = false;
			$reallyLong = false;

			// Reset the times.
			$thisDay['times'] = [];
			unset($thisDay['hyperfine']);
			$failedRun = false;
			$saveResult = false;
			$allowHyperfine = !$noHyperfine && ($participant instanceof V2Participant) && $participant->useHyperfine();

			if ($participant instanceof V2Participant) {
				$thisDay['image'] = $participant->getImageInfo();
			}

			$lastRunTime = 0;

			for ($i = 0; $i <= ($reallyLong ? $reallyLongRepeatCount : ($long ? $longRepeatCount : $repeatCount)); $i++) {
				if ($i == 1 && $allowHyperfine) {
					echo ' ', $i, 'H';
					$data['healthcheck'][$person]['days'][$day]['log'] .= ' ' . $i . 'H';

					$hyperfineOpts = [];
					if ($lastRunTime > $reallyLongTimeout) {
						echo 'LL';
						$data['healthcheck'][$person]['days'][$day]['log'] .= 'LL';
						$hyperfineOpts['max'] = $reallyLongRepeatCount;
						$hyperfineOpts['warmup'] = 0;
					} else if ($lastRunTime > $longTimeout) {
						echo 'L';
						$data['healthcheck'][$person]['days'][$day]['log'] .= 'L';
						$hyperfineOpts['max'] = $longRepeatCount;
						$hyperfineOpts['warmup'] = 0;
					} else {
						$hyperfineOpts['max'] = $repeatCount;
						$hyperfineOpts['warmup'] = 1;
					}
					$hyperfineOpts['min'] = min(5, $hyperfineOpts['max']);
					$hyperfineOpts['estimated'] = $lastRunTime;

					list($ret, $result) = $participant->runHyperfine($day, $hyperfineOpts);

					// Check if hyperfine actually got complete data, if not we'll fallback to not using hyperfine.
					if (isset($result['HYPERFINEDATA']['results'][0]['times']) && count($result['HYPERFINEDATA']['results'][0]['times']) > 1 && array_sum($result['HYPERFINEDATA']['results'][0]['exit_codes']) == 0) {
						$thisDay['hyperfine'] = $result['HYPERFINEDATA']['results'][0];
						$thisDay['hyperfine']['bin'] = $result['HYPERFINEPATH'][0];
						$thisDay['hyperfine']['opts'] = $hyperfineOpts;
						$thisDay['hyperfine']['version'] = $result['HYPERFINEVERSION'][0];
						$thisDay['times'] = [];
						foreach ($thisDay['hyperfine']['times'] as $time) {
							$thisDay['times'][] = '0m' . $time . 's';
						}

						unset($thisDay['hyperfine']['times']);
						unset($thisDay['hyperfine']['exit_codes']);
						unset($thisDay['hyperfine']['command']);
						$saveResult = true;
						$hasRun = true;
						$data['healthcheck'][$person]['days'][$day]['runtype'] = 'hyperfine';
						break;
					} else {
						// Make us try again without hyperfine and behave normally.
						$allowHyperfine = true;
						echo 'F';
						$data['healthcheck'][$person]['days'][$day]['log'] .= 'F';
					}
				}

				if ($i == 0) {
					list($ret, $result) = $participant->runOnce($day);
					echo ' R';
					$data['healthcheck'][$person]['days'][$day]['log'] .= ' R';
					$thisDay['runOnce'] = $result;

					$data['healthcheck'][$person]['runonce'] = ($ret === 0);

					if ($ret != 0) {
						echo 'F';
						$data['healthcheck'][$person]['days'][$day]['log'] .= 'F';
						$data['healthcheck'][$person]['runonce_info'] = implode("\n", $out);
						echo "\n";
						echo 'RunOnce exited with error.', "\n";
						echo 'Output:', "\n";
						foreach ($result as $out) { echo '        > ', $out, "\n"; }
						$failedRun = true;
						break;
					} else if ($runDebugMode) {
						echo "\n=[DEBUG]=========\n", implode("\n", $result), "\n=========[DEBUG]=\n";
					}
				}

				$start = time();
				echo ' ', $i;
				$data['healthcheck'][$person]['days'][$day]['log'] .= ' ' . $i;
				list($ret, $result) = $participant->run($day);
				$end = time();
				$lastRunTime = ($end - $start);
				usleep($sleepTime); // Sleep a bit so that we're not constantly running.

				if ($i == 0) { $thisDay['firstRun'] = $result; }

				// Output to show the day ran.
				if ($ret != 0) {
					$data['healthcheck'][$person]['days'][$day]['runtype'] = 'fail';
					$data['healthcheck'][$person]['days'][$day]['log'] .= 'F';
					echo 'F';
					echo "\n";
					echo 'Exited with error.', "\n";
					echo 'Output:', "\n";
					foreach ($result as $out) { echo '        > ', $out, "\n"; }
					$failedRun = true;
					break;
				} else {
					$data['healthcheck'][$person]['days'][$day]['runtype'] = 'time';
					if ($checkOutput) {
						$rightAnswer = preg_match('#' . preg_quote($answer1, '#') . '.+' . preg_quote($answer2, '#') . '#i', implode(' ', $result));
						if (!$rightAnswer) {
							$data['healthcheck'][$person]['days'][$day]['runtype'] = 'incorrect';
							$data['healthcheck'][$person]['days'][$day]['log'] .= 'I';
							echo 'I';
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
					if ($runDebugMode && $i = 0) {
						echo "\n=[DEBUG]=========\n", implode("\n", $result), "\n=========[DEBUG]=\n";
					}
				}

				// If this was a long-running day that wasn't the first run,
				// run future days less often. (First run is allowed to allow
				// for compile-time)
				if ($lastRunTime > $longTimeout) {
					echo 'L';
					$data['healthcheck'][$person]['days'][$day]['log'] .= 'L';
					$long = ($i > 0);
				}

				// Same for really-long.
				if ($lastRunTime > $reallyLongTimeout) {
					echo 'L';
					$data['healthcheck'][$person]['days'][$day]['log'] .= 'L';
					$reallyLong = ($i > 0);
				}

				if ($i > 0) {
					// Get the `real` time output.
					$time = $participant->extractTime($result);

					$thisDay['times'][] = $time;
					$saveResult = true;
					$hasRun = true;
				}

				if ($reallyLong) {
					$data['healthcheck'][$person]['days'][$day]['length'] = 'long';
				} else if ($long) {
					$data['healthcheck'][$person]['days'][$day]['length'] = 'reallylong';
				} else {
					$data['healthcheck'][$person]['days'][$day]['length'] = 'normal';
				}
			}
			echo "\n";

			// Update data if we've actually ran enough times or if we failed.
			if ($failedRun || $saveResult) {
				// Invalidate any times if we failed or sort them for later.
				if ($failedRun) {
					unset($thisDay['times']);
				} else {
					$thisDay['checkedOutput'] = $checkOutput;
					$thisDay['long'] = $long;
					$thisDay['reallyLong'] = $reallyLong;
				}

				$thisDay['version'] = $participant->getDayVersion($day);
				$data['results'][$person]['days'][$day] = $thisDay;
			}

			// Save the data.
			saveData($resultsFile, $data, $hasRun);
		}

		echo 'Cleanup.', "\n";
		$participant->cleanup();

		// Save the data.
		saveData($resultsFile, $data, $hasRun);
	}

	// Save the data.
	saveData($resultsFile, $data, $hasRun);
	$endTime = time();
	echo "\n";
	echo 'Bench ended at: ', date('r'), "\n";
	echo 'Took: ', secondsToDuration($endTime - $startTime), "\n";
