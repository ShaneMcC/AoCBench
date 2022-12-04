<?php
	abstract class Participant {
		/**
		 * Name of Participant
		 *
		 * @return String Participant Name
		 */
		abstract function getName();

		/**
		 * URL to check out repo.
		 *
		 * @return String Repo URL
		 */
		abstract function getRepo();

		/**
		 * Subheading for participant
		 *
		 * @return String Sub heading for participant
		 */
		function getSubheading() { return ''; }

		/**
		 * Get the filename that should be versioned for this day.
		 * This can be either a file or a directory.
		 *
		 * @param int $day Day number.
		 * @return Path to version.
		 */
		public function getDayFilename($day) { return $day; }

		/**
		 * Get the command used to run the day.
		 *
		 * @param int $day Day to run.
		 * @return String command to run to start the day.
		 */
		public function getRunCommand($day) { return './run.sh ' . $day; }

		/**
		 * Prepare this participant.
		 *
		 * This will run either `docker.sh` or `run.sh` with no arguments.
		 */
		public function prepare() {
			if (file_exists('./docker.sh')) {
				exec('bash ./docker.sh 2>&1 </dev/null');
			} else if (file_exists('./run.sh')) {
				exec('bash ./run.sh 2>&1 </dev/null');
			}
		}

		/**
		 * Get the filename used for input for each day.
		 *
		 * By default this is `/input.txt` appended to `getDayFilename($day)`
		 *
		 * @param int $day Day number.
		 * @return Path to intput file.
		 */
		public function getInputFilename($day) {
			return $this->getDayFilename($day) . '/input.txt';
		}

		/**
		 * Get the latest commit id for a given path.
		 *
		 * @param String $path Path to check.
		 * @return String Git version or NULL.
		 */
		private function getGitVersion($path) {
			if (!empty(glob($path))) {
				exec('git rev-list -1 HEAD -- "' . $path . '" 2>&1', $output);
				return $output[0];
			}

			return NULL;
		}

		/**
		 * Get the version ID for the day.
		 * This defaults to the latest git commit for `getDayFilename($day)`
		 *
		 * @param int $day Day number.
		 * @return Code version for the given Day.
		 */
		public function getDayVersion($day) {
			return $this->getGitVersion($this->getDayFilename($day));
		}

		/**
		 * Check if this participant has the requested day.
		 *
		 * @param int $day Day number.
		 * @return boolean True if this day is known.
		 */
		public function hasDay($day) {
			return !empty(glob($this->getDayFilename($day))) && $this->getDayVersion($day) !== NULL;
		}

		/**
		 * Check if a day is marked as WIP
		 *
		 * @param int $day Day number.
		 * @return boolean True if the day is known but marked WIP.
		 */
		public function isWIP($day) {
			if ($this->hasDay($day)) {
				$file = glob($this->getDayFilename($day))[0];
				if (is_dir($file)) {
					return file_exists($file . '/.wip');
				} else {
					return !empty(glob($this->getDayFilename($day) . '.wip'));
				}
			}

			return FALSE;
		}

		/**
		 * Get the input for the given day from this participant.
		 *
		 * @param int $day Day number.
		 * @return String Input file contents for this day.
		 */
		public function getInput($day) {
			return file_exists($this->getInputFilename($day)) ? file_get_contents($this->getInputFilename($day)) : '';
		}

		/**
		 * Get the Version for the input file for this day.
		 *
		 * @param int $day Day number.
		 * @return String version for the given input.
		 */
		public function getInputVersion($day) {
			return $this->getGitVersion($this->getInputFilename($day));
		}

		/**
		 * Set the input for the given day to the given input.
		 *
		 * @param int $day Day number.
		 * @param String $input new input file content
		 */
		public function setInput($day, $input) {
			file_put_contents($this->getInputFilename($day), $input);
		}

		/**
		 * Get the filename to find answers for a given day.
		 *
		 * @param int $day Day number.
		 * @return String Answers file name
		 */
		public function getInputAnswerFilename($day) {
			return $this->getDayFilename($day) . '/answers.txt';
		}

		/**
		 * Get the answer for a given day/part.
		 *
		 * @param int $day Day number.
		 * @param int $part Which part of the day.
		 * @return String Expected answer for the day.
		 */
		public function getInputAnswer($day, $part) {
			$answerFile = $this->getInputAnswerFilename($day);
			if ($answerFile !== NULL && file_exists($answerFile)) {
				$answers = file($answerFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				if (isset($answers[$part - 1])) {
					return $answers[$part - 1];
				}
			}
			return NULL;
		}

		/**
		 * Get array of days to ignore for this participant.
		 *
		 * @return Array of ignored entries
		 */
		public function getIgnored() { return []; }

		/**
		 * Get array of inputs to ignore for this participant for inputMatrix
		 *
		 * @return Array of ignored entries
		 */
		public function getIgnoredInputs() { return []; }

		/**
		 * Get array of answers for inputs to ignore for this participant for inputMatrix
		 *
		 * @return Array of ignored entries
		 */
		public function getIgnoredAnswers() { return []; }

		/**
		 * Extract the time-taken from the output.
		 *
		 * @param Array Output from running the day.
		 * @return String time-taken to run this day according to the output.
		 */
		public function extractTime($output) {
			$time = '999m9.999s';
			for ($i = max(0, count($output) - 5); $i < count($output) - 1; $i++) {
				if (preg_match('#^real(.*)$#', $output[$i], $m)) {
					$time = trim($m[1]);
				}
			}

			return $time;
		}

		/**
		 * Run the given day.
		 *
		 * @param int $day Day number.
		 * @return Array Array of [returnCode, outputFromRun] where outputFromRun is an array of lines of output.
		 */
		public function run($day) {
			global $execTimeout;

			$output = [];
			$ret = -1;
			dockerTimedExec($this->getRunCommand($day) . ' 2>&1', $output, $ret, $execTimeout);

			return [$ret, $output];
		}

		/**
		 * In the given directory, either update the git repository, or clone a
		 * fresh copy.
		 *
		 * @param String $dir Directory to use for repo.
		 */
		public function updateRepo($dir) {
			if (file_exists($dir)) {
				echo 'Updating Repo.', "\n";
				chdir($dir);
				chmod($dir, 0777); // YOLO.
				exec('git reset --hard origin 2>&1');
				exec('git pull 2>&1');
			} else {
				echo 'Cloning Repo.', "\n";
				mkdir($dir, 0755, true);
				exec('git clone ' . $this->getRepo() . ' ' . $dir . ' 2>&1');
				chdir($dir);
				chmod($dir, 0777); // YOLO.
			}
		}

		/**
		 * Clean up after running the day(s).
		 */
		public function cleanup() {
			exec('git reset --hard origin 2>&1');
			exec('git clean -fx 2>&1');
			if (file_exists('./cleanup.sh')) {
				exec('bash ./cleanup.sh 2>&1 </dev/null');
			}
		}
	}
