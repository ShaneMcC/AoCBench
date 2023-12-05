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
		 * Language for participant
		 *
		 * @return String Languages for participant
		 */
		function getLanguage() { return ''; }

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
				//if (preg_match('#^real(.*)$#', $output[$i], $m)) {
				// Look specifically for bash's 3dp time output
				if (preg_match('#^real\s+([0-9]+m[0-9]+\.[0-9]{3}s)$#', trim($output[$i]), $m)) {
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
			dockerTimedExec(null, $this->getRunCommand($day) . ' 2>&1', $output, $ret, $execTimeout);

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

	abstract class V2Participant extends Participant {
		private $yaml = null;
		private $canary = null;

		public function useHyperfine() {
			return $this->getAOCBenchConfig()['hyperfine'] ?? true;
		}

		public final function getAOCBenchConfig() {
			global $participantsDir;

			if ($this->yaml === null) {
				if (file_exists($participantsDir . '/' . $this->getName() . '.yaml')) {
					$filename = $participantsDir . '/' . $this->getName() . '.yaml';
				} else if (file_exists('.aocbench.yaml')) {
					$filename = $participantsDir . '/' . $this->getName() . '/.aocbench.yaml';
				}

				$this->yaml = $filename != null ? spyc_load_file($filename) : [];
			}

			return $this->yaml;
		}

		function getLanguage() { return $this->getAOCBenchConfig()['language'] ?? ''; }

		final function getRunCommand($day) {
			/* Do Nothing, but don't allow this to be overridden anymore. */
		}

		public function getDockerfile() {
			$locations = $this->getAOCBenchConfig()['dockerfile'] ?? [];

			if (empty($locations)) {
				$locations[] = '.docker/Dockerfile';
				$locations[] = 'docker/Dockerfile';
				$locations[] = 'Dockerfile';
				$locations[] = '.docker/Containerfile';
				$locations[] = 'docker/Containerfile';
				$locations[] = 'Containerfile';
			} else if (!is_array($locations)) {
				$locations = [$locations];
			}

			foreach ($locations as $l) {
				if (file_exists($l)) {
					return $l;
				}
			}

			return null;
		}

		public final function getDockerImageName() {
			$imageName = $this->getAOCBenchConfig()['image'] ?? null;

			if ($imageName == null) {
				$dockerFile = $this->getDockerfile();
				if ($dockerFile != null) {
					$imageName = 'aocbench-' . strtolower($this->getName()) . '-' . crc32($this->getRepo()) . ':' . crc32($dockerFile) . '-' . filemtime($dockerFile);
				}
			}

			return $imageName;
		}

		/**
		 * Prepare this participant.
		 *
		 * This will build the required docker container for the participant.
		 */
		public function prepare() {
			$imageName = $this->getAOCBenchConfig()['image'] ?? null;
			$dockerFile = $this->getDockerfile();
			if ($imageName != null) {
				exec('docker pull ' . escapeshellarg($imageName) . ' >/dev/null 2>&1');
			}

			if ($imageName == null && $dockerFile != null) {
				$imageName = $this->getDockerImageName();
				$out = [];
				$ret = -1;
				exec('docker image inspect ' . escapeshellarg($imageName) . ' >/dev/null 2>&1', $out, $ret);
				if ($ret != 0) {
					$pwd = getcwd();
					chdir(dirname($dockerFile));
					exec('docker build . -t ' . escapeshellarg($imageName) . ' --file ' . escapeshellarg(basename($dockerFile)) . '  >/dev/null 2>&1');
					chdir($pwd);
				}
			}

			// Create temporary storage directory.
			if (!file_exists('./.aocbench_run/')) {
				mkdir('./.aocbench_run/');
			}
		}

		public function getCodeDir() { return $this->getAOCBenchConfig()['code'] ?? '/code'; }
		public function getPersistence() { return $this->getAOCBenchConfig()['persistence'] ?? []; }
		public function getEnvironment() { return $this->getAOCBenchConfig()['environment'] ?? []; }

		private function getCanary() {
			if ($this->canary == null) {
				$this->canary = uniqid('AOCBENCH-CANARY-', true);
			}
			return $this->canary;
		}

		private function getRunScript($day) {
			$runOnce = $this->getValueWithReplacements('runonce', $day);
			$cmd = $this->getValueWithReplacements('cmd', $day);;
			$workdir = $this->getValueWithReplacements('workdir', $day) ?? $this->getAOCBenchConfig()['code'];
			$canary = $this->getCanary();
			$hyperfineOutput = '/tmp/' . uniqid('aocbench-hyperfine-', true) . '.csv';
			return <<<RUNSCRIPT
#!/bin/bash

cd $workdir
$runOnce
cd $workdir

if [ "\${1}" == "hyperfine" ]; then
	echo '### $canary START - HYPERFINE ###';
	hyperfine -w 1 -m 5 -M 20  --export-json $hyperfineOutput -- "$cmd"
	echo '### $canary END ###';
	echo '### $canary START - HYPERFINEDATA ###';
	cat $hyperfineOutput;
	echo '### $canary END ###';
else
	echo '### $canary START - TIME ###';
	time $cmd
	echo '### $canary END ###';
fi;
RUNSCRIPT;
		}

		private function getReplacements($day, $includeInput = true) {
			global $leaderboardYear;

			$replacements = [];
			$replacements['%year%'] = $leaderboardYear;
			$replacements['%day%'] = $day;
			$replacements['%zeroday%'] = sprintf('%0d', $day);
			if ($includeInput) {
				$replacements['%input%'] = $this->getCodeDir() . '/' . $this->getInputFilename($day);
			}

			return $replacements;
		}

		private function getValueWithReplacements($value, $day) {
			$config = $this->getAOCBenchConfig();
			if (isset($config[$value])) {
				$replacements = $this->getReplacements($day, ($value != 'inputfile'));
				return str_replace(array_keys($replacements), array_values($replacements), $config[$value]);
			}
			return null;
		}

		private function parseRunOutput($output) {
			$realOutput = [];
			$canary = $this->getCanary();
			$section = null;
			foreach ($output as $line) {
				if (preg_match('/^### ' . preg_quote($canary) . ' START - (.*) ###$/', trim($line), $m)) {
					$section = $m[1];
					$realOutput[$section] = [];
				} else if (trim($line) == "### $canary END ###") {
					$section = null;
				} else if ($section != null) {
					$realOutput[$section][] = $line;
				}
			}
			return $realOutput;
		}

		public function getInputFilename($day) {
			return $this->getValueWithReplacements('inputfile', $day) ?? $this->getDayFilename($day) . '/input.txt';
		}

		public function getInputAnswerFilename($day) {
			return $this->getValueWithReplacements('answerfile', $day) ?? $this->getDayFilename($day) . '/answers.txt';
		}

		public function getDayFilename($day) {
			return $this->getValueWithReplacements('daypath', $day) ?? $day;
		}

		/**
		 * Run the given day.
		 *
		 * @param int $day Day number.
		 * @return Array Array of [returnCode, outputFromRun] where outputFromRun is an array of lines of output.
		 */
		public function run($day) {
			[$ret, $result] = $this->doRun($day, false);
			return [$ret, $result['TIME'] ?? []];
		}

		/**
		 * Run the given day with hyperfine
		 *
		 * @param int $day Day number.
		 * @return Array Array of [returnCode, outputFromRun] where outputFromRun is an array of lines of output.
		 */
		public function runHyperfine($day) {
			[$ret, $result] = $this->doRun($day, true);

			foreach (array_keys($result) as $key) {
				foreach ($result[$key] as &$data) {
					$data = preg_replace("/\r$/D", '', $data);
				}
			}

			$result['HYPERFINEDATA'] = json_decode(implode("\n", $result['HYPERFINEDATA']), true);

			return [$ret, $result];
		}

		/**
		 * Run the given day.
		 *
		 * @param int $day Day number.
		 * @param bool $useHyperfine Use hyperfine for this run.
		 * @return Array Array of [returnCode, outputFromRun] where outputFromRun is an array of arrays of sections of output.
		 */
		private function doRun($day, $useHyperfine) {
			global $execTimeout;

			if ($this->getAOCBenchConfig()['version'] != "1") {
				return [1, ['TIME' => ['AoCBench Error: Unknown config file version (Got: "' . $this->getAOCBenchConfig()['version'] . '" - Wanted: "1").']]];
			}

			$imageName = $this->getDockerImageName();
			if ($imageName == null) {
				return [1, ['TIME' => ['AoCBench Error: Unable to find docker image name']]];
			}

			$containerName = 'aocbench_' . uniqid(strtolower($this->getName()), true);

			// Run Command:
			$pwd = getcwd();

			$runScriptFilename = './.aocbench_run/aocbench-' . uniqid(true) . '.sh';
			// $runScriptFilename = './.aocbench_run/aocbench.sh';
			file_put_contents($runScriptFilename, $this->getRunScript($day));
			chmod($runScriptFilename, 0777);

			$replacements = $this->getReplacements($day);

			$cmd = 'docker run --rm ';
			if (!($this->getAOCBenchConfig()['notty'] ?? false)) {
				$cmd .= ' -it';
			}
			$cmd .= ' --name ' . escapeshellarg($containerName);
			$cmd .= ' -v ' . escapeshellarg($pwd . ':' . $this->getCodeDir());
			foreach ($this->getPersistence() as $location) {
				$location = str_replace(array_keys($replacements), array_values($replacements), $location);
				$path = $pwd . '/.aocbench_run/' . crc32($location) . '_' . basename($location);
				if (!file_exists($path)) { mkdir($path); }
				chmod($path, 0777);
				$cmd .= ' -v ' . escapeshellarg($path . ':' . $location);
			}
			$cmd .= ' -v ' . escapeshellarg($pwd . '/' . $runScriptFilename . ':/aocbench.sh');
			foreach ($this->getEnvironment() as $env) {
				$env = explode('=', $env, 2);
				$env[1] = str_replace(array_keys($replacements), array_values($replacements), $env[1] ?? '');
				$cmd .= ' -e ' . escapeshellarg(implode('=', $env));
			}
			$cmd .= ' ' . escapeshellarg($imageName);
			$cmd .= ' ' . '/aocbench.sh';
			if ($useHyperfine) {
				$cmd .= ' hyperfine';
			}
			$cmd .= ' 2>&1';

			$output = [];
			$ret = -1;
			dockerTimedExec($containerName, $cmd, $output, $ret, $execTimeout);
			@unlink($runScriptFilename);

			return [$ret, $this->parseRunOutput($output)];
		}

		/**
		 * Get the arguments used to run the day.
		 *
		 * @param int $day Day to run.
		 * @return String command to run to start the day.
		 */
		public function getRunArgs($day) { return $day; }
	}
