<?php

	$lockfile = __DIR__ . '/.benchlock';
	$resultsFile = __DIR__ . '/results.json';
	$outputResultsFile = __DIR__ . '/outputresults.json';
	$participantsDir = __DIR__ . '/participants/';

	$repeatCount = 20;
	$longTimeout = 10;
	$longRepeatCount = 10;

	$normaliseInput = true;
	$inputsDir = __DIR__ . '/inputs/';

	$participants = [];

	abstract class Participant {
		abstract function getName();
		abstract function getRepo();
		public function prepare() {
			if (file_exists('./docker.sh')) {
				exec('bash ./docker.sh 2>&1');
			} else if (file_exists('./run.sh')) {
				exec('bash ./run.sh 2>&1');
			}
		}
		abstract function getInputFilename($day);
		abstract function getVersion($day);
		abstract function run($day);

		public function hasDay($day) { return $this->getVersion($day) !== NULL; }

		public function getInput($day) {
			return file_exists($this->getInputFilename($day)) ? file_get_contents($this->getInputFilename($day)) : '';
		}

		public function getInputVersion($day) {
			$file = $this->getInputFilename($day);
			if (file_exists($file)) {
				exec('git rev-list -1 HEAD -- "' . $file . '" 2>&1', $output);
				return $output[0];
			}

			return NULL;
		}

		public function setInput($day, $input) {
			file_put_contents($this->getInputFilename($day), $input);
		}

		public function getInputAnswer($day, $part) { return NULL; }

		public function extractTime($output) {
			$time = $output[count($output) - 3];
			$time = trim(preg_replace('#^real#', '', $time));
			return $time;
		}

		protected function doRun($cmd) {
			$output = [];
			$ret = -1;
			exec($cmd, $output, $ret);

			return [$ret, $output];
		}

		public function updateRepo($dir) {
			if (file_exists($dir)) {
				echo 'Updating Repo.', "\n";
				chdir($dir);
				exec('git reset --hard origin 2>&1');
				exec('git pull 2>&1');
			} else {
				echo 'Cloning Repo.', "\n";
				mkdir($dir, 0755, true);
				exec('git clone ' . $this->getRepo() . ' ' . $dir . ' 2>&1');
				chdir($dir);
			}
		}

		public function cleanup() { exec('git reset --hard origin 2>&1'); }
	}

	// Local configuration.
	if (file_exists(dirname(__FILE__) . '/config.local.php')) {
		include(dirname(__FILE__) . '/config.local.php');
	}

	if (!function_exists('getInputAnswer')) {
		function getInputAnswer($day, $part) { return NULL; }
	}
