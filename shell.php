#!/usr/bin/php
<?php
	require_once(__DIR__ . '/functions.php');

	// Get CLI Options.
	$__CLIOPTS = getopt('p:d:s:h', ['participant:', 'day:', 'script:', 'section:', 'help', 'debug', 'noclean'], $restIndex);

	$wantedParticipant = getOptionValue('p', 'participant', '');
	$runDebugMode = getOptionValue(NULL, 'debug', NULL) !== NULL;
	$wantedScript = getOptionValueAll('s', 'script', 'shell');
	$wantedDay = getOptionValue('d', 'day', '0');
	$restArgs = array_slice($argv, $restIndex);
	$noClean = getOptionValue(NULL, 'noclean', NULL) !== NULL;
	$wantedSection = getOptionValue(NULL, 'section', '.*');

	if (getOptionValue('h', 'help', NULL) !== NULL) {
		echo 'AoCBench Shell.', "\n";
		echo "\n";
		echo 'Usage: ', $_SERVER['argv'][0], ' [options][ -- args]', "\n";
		echo '', "\n";
		echo 'Valid options:', "\n";
		echo '  -h, --help                    Show this help output', "\n";
		echo '  -p, --participant <name>      Run shell inside container for <name>', "\n";
		echo '  -s, --script <script>         Which built-in script to run? (Default: "shell")', "\n";
		echo '                                Some valid options: shell, hyperfine, time, runonce', "\n";
		echo '      --section <regex>         When out putting script results, only display sections <regex>.', "\n";
		echo '  -d, --day <day>               Some script types need a day, specify it here. (Default: 1)', "\n";
		echo '      --debug                   Enable extra debugging in various places.', "\n";
		echo '      --noclean                 Do not run clean() after our command.', "\n";
		echo '', "\n";
		echo 'Any additional args passed will be passed to the script but may be ignored.', "\n";
		echo '', "\n";
		die();
	}

	$startTime = time();
	echo 'Shell starting at: ', date('r', $startTime), "\n";

	foreach ($participants as $participant) {
		$person = $participant->getDirName(false);
		if (!preg_match('#^' . $wantedParticipant. '$#', $person)) { continue; }

		echo "\n", $participant->getName() , ': ', "\n";
		$dir = $participantsDir . '/' . $person;
		if (!file_exists($dir)) {
			if (!$participant->updateRepo($dir)) {
				echo 'Failed to clone/update repo.', "\n";
				continue;
			}
		}
		chdir($dir);
		$valid = $participant->isValidParticipant();
		if ($valid !== true) {
			echo 'Repo not valid: ', $valid, "\n";
			break;
		}

		if (!($participant instanceof V2Participant)) {
			echo 'This command can only enter a shell for V2 Participants', "\n";
			break;
		}

		// Prepare.
		echo 'Preparing.', "\n";
		$prepResult = $participant->prepare();
		if ($prepResult !== true) {
			echo "\n=[Failed to prepare]=========\n", implode("\n", $prepResult), "\n==========\n";
			break;
		}

		if (!is_array($wantedScript)) { $wantedScript = [$wantedScript]; }

		foreach ($wantedScript as $ws) {
			if ($ws == 'bulkinput') {
				// Treat args as a list of participant matches.
				$opts = ['files' => []];

				foreach ($restArgs as $arg) {
					foreach ($participants as $participant2) {
						$person2 = $participant2->getDirName(false);
						if (!preg_match('#^' . $arg. '$#', $person2)) { continue; }

						$opts['files'][$person2] = $participant2->getInput($wantedDay);
					}
				}
			} else {
				$opts = ['args' => $restArgs];
			}

			$result = $participant->doRun($wantedDay, $ws, $opts);

			foreach ($result[1] as $section => $output) {
				if (preg_match('#^' . $wantedSection . '$#', $section)) {
					echo "\n=[Output: ", $section, "]=========\n", implode("\n", $output), "\n==========\n";
				}
			}
		}

		if ($noClean) {
			echo 'Skipping cleanup.', "\n";
		} else {
			echo 'Cleanup.', "\n";
			$participant->cleanup();
		}
	}

	// Save the data.
	$endTime = time();
	echo "\n";
	echo 'Shell ended at: ', date('r'), "\n";
