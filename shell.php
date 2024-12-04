#!/usr/bin/php
<?php
	require_once(__DIR__ . '/functions.php');

	// Get CLI Options.
	$__CLIOPTS = getopt('fp:d:h', ['force', 'participant:', 'day:', 'help', 'no-update', 'no-hyperfine', 'remove', 'debug', 'no-lock', 'dryrun']);

	$wantedParticipant = getOptionValue('p', 'participant', '');
    $runDebugMode = getOptionValue(NULL, 'debug', NULL) !== NULL;

	if (getOptionValue('h', 'help', NULL) !== NULL) {
		echo 'AoCBench Shell.', "\n";
		echo "\n";
		echo 'Usage: ', $_SERVER['argv'][0], ' [options]', "\n";
		echo '', "\n";
		echo 'Valid options:', "\n";
		echo '  -h, --help                    Show this help output', "\n";
		echo '  -p, --participant <name>      Run shell inside container for <name>', "\n";
		echo '      --debug                   Enable extra debugging in various places.', "\n";
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

        $participant->runShell();

        echo 'Cleanup.', "\n";
		$participant->cleanup();
	}

	// Save the data.
	$endTime = time();
	echo "\n";
	echo 'Shell ended at: ', date('r'), "\n";
