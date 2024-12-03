<?php
	class Participant {
		private $name;
		private $repo;

		/**
		 * Create a basic participant.
		 */
		public function __construct($name = '', $repo = '') {
			$this->name = $name;
			$this->repo = $repo;
		}

		public function isValidParticipant() {
			return (file_exists('./docker.sh') || file_exists('./run.sh')) ?? 'Missing docker.sh or run.sh file.';
		}

		/**
		 * Name of Participant
		 *
		 * @return String Participant Name
		 */
		function getName() {
			if (empty($this->name)) { throw new Exception('Participant was created without a name.'); }

			return $this->name;
		}

		/**
		 * Get the directory name that we should use for this participant.
		 *
		 * This is a "safe" version of the name (remove all non-alphanumeric characters.)
		 *
		 * This name is also used for other things such as the key in the results or
		 * docker container names etc.
		 *
		 * @param $full Also include the full $participantsDir path not just the name.
		 */
		final function getDirName($full = false) {
			global $participantsDir;
			$name = preg_replace('#[^A-Z0-9-_]#i', '', $this->getName());

			if ($full) { $name = $participantsDir . '/' . $name; }
			return $name;
		}

		/**
		 * URL to check out repo.
		 *
		 * @return String Repo URL
		 */
		function getRepo() {
			if (empty($this->repo)) { throw new Exception('Participant was created without a repo.'); }

			return $this->repo;
		}

		/**
		 * Branch to use if not-default for checking out repo.
		 *
		 * @return String Branch name
		 */
		function getBranch() { return null; }

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
			$out = [];
			$ret = 0;
			if (file_exists('./docker.sh')) {
				exec('bash ./docker.sh 2>&1 </dev/null', $out, $ret);
			} else if (file_exists('./run.sh')) {
				exec('bash ./run.sh 2>&1 </dev/null', $out, $ret);
			}
			return $ret == 0 ? true : $out;
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
		 * @param String $path Path to check (single or array).
		 * @return String Git version or NULL.
		 */
		protected function getGitVersion($path) {
			$cmd = 'git rev-list -1 HEAD --';
			if (!is_array($path)) { $path = [$path]; }

			$hasValidPaths = false;
			foreach ($path as $p) {
				if (!empty(glob($p))) {
					$hasValidPaths = true;
					$cmd .= ' ' . escapeshellarg($p);
				}
			}
			$cmd .= ' 2>&1';

			if ($hasValidPaths) {
				exec($cmd, $output);
				return $output[0] ?? NULL;
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
					return file_exists($file . '/.wip') || file_exists($file . '/.nobench');
				} else {
					return !empty(glob($this->getDayFilename($day) . '.wip')) || !empty(glob($this->getDayFilename($day) . '.nobench'));
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
		 * @param bool $includeAnswers [Default: true] Include the answers file when deciding the version?
		 * @return String version for the given input.
		 */
		public function getInputVersion($day, $includeAnswers = true) {
			return $includeAnswers ? $this->getGitVersion([$this->getInputFilename($day), $this->getInputAnswerFilename($day)]) : $this->getGitVersion($this->getInputFilename($day));
		}

		/**
		 * Get the Version for the input answers file for this day.
		 *
		 * @param int $day Day number.
		 * @param bool $includeInput [Default: true] Include the input file when deciding the version?
		 * @return String version for the given input.
		 */
		public function getInputAnswerVersion($day, $includeInput = true) {
			return $includeInput ? $this->getGitVersion([$this->getInputFilename($day), $this->getInputAnswerFilename($day)]) : $this->getGitVersion($this->getInputAnswerFilename($day));
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
		 * Do one-time run on the given day.
		 *
		 * @param int $day Day number.
		 * @return Array Array of [returnCode, outputFromRun] where outputFromRun is an array of lines of output.
		 */
		public function runOnce($day) { return [0, []]; }

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
			global $runDebugMode;
			$out = [];
			$ret = 0;
			if (file_exists($dir . '/.git')) {
				echo 'Updating Repo.', "\n";
				chdir($dir);
				@chmod($dir, 0777); // YOLO.
				exec('git fetch 2>&1', $out, $ret);
				exec('git reset --hard @{upstream} 2>&1', $out, $ret);
				exec('git submodule update --init --recursive 2>&1', $out, $ret);
				if ($ret != 0) { return false; }
			} else {
				echo 'Cloning Repo.', "\n";
				@mkdir($dir, 0755, true);
				$branch = $this->getBranch();
				if (empty($branch)) {
					exec('git clone ' . $this->getRepo() . ' ' . $dir . ' 2>&1', $out, $ret);
				} else {
					exec('git clone --branch ' . $branch . ' '. $this->getRepo() . ' ' . $dir . ' 2>&1', $out, $ret);
				}
				exec('git submodule update --init --recursive 2>&1', $out, $ret);
				chdir($dir);
				@chmod($dir, 0777); // YOLO.
			}

			if ($runDebugMode) {
				echo "\n=[DEBUG]=========\n", implode("\n", $out), "\n=========[DEBUG]=\n";
			}

			return $ret == 0 ? file_exists($dir . '/.git') : false;
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

	class V2Participant extends Participant {
		private $yaml = null;
		private $canary = null;
		private $imageInfo = null;

		public function isValidParticipant() {
			$config = $this->getAOCBenchConfig();
			if (empty($config)) { return 'No config found.'; }
			if (($config['version'] ?? '') != "1") {
				return 'Unknown config file version (Got: "' . $this->getAOCBenchConfig()['version'] . '" - Wanted: "1").';
			}

			return true;
		}

		public function useHyperfine() {
			return $this->getAOCBenchConfig()['hyperfine'] ?? true;
		}

		public function getAOCBenchConfigOverride($config) {
			return $config;
		}

		public function getAOCBenchConfig() {
			global $participantsDir;

			if ($this->yaml === null) {
				$validFiles = [];
				$validFiles[] = $participantsDir . '/' . $this->getDirName(false) . '.yaml';
				$validFiles[] = $participantsDir . '/' . $this->getDirName(false) . '.yml';
				$validFiles[] = $this->getDirName(true) . '/.aocbench.yaml';
				$validFiles[] = $this->getDirName(true) . '/.aocbench.yml';

				$filename = null;
				foreach ($validFiles as $f) {
					if (file_exists($f)) {
						$filename = $f;
						break;
					}
				}

				$this->yaml = $this->getAOCBenchConfigOverride($filename != null ? yaml_decode_file($filename) : []);
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
					$imageName = 'aocbench-' . strtolower($this->getDirName(false)) . '-' . crc32($this->getRepo()) . ':' . crc32($dockerFile) . '-' . filemtime($dockerFile);
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
			global $runDebugMode;

			$this->imageInfo = [];
			$imageName = $this->getAOCBenchConfig()['image'] ?? null;
			$dockerFile = $this->getDockerfile();
			if ($imageName != null) {
				exec('docker pull ' . escapeshellarg($imageName) . ' >/dev/null 2>&1');
				$this->imageInfo['image'] = $imageName;
				$this->imageInfo['hash'] = exec('docker image inspect ' . escapeshellarg($imageName) . ' --format "{{.Id}}" 2>&1');
			}

			if ($imageName == null && $dockerFile != null) {
				$imageName = $this->getDockerImageName();
				$out = [];
				$ret = -1;
				exec('docker image inspect ' . escapeshellarg($imageName) . ' >/dev/null 2>&1', $out, $ret);
				if ($ret != 0) {
					$pwd = getcwd();
					chdir(dirname($dockerFile));
					$cmd = 'docker build . -t ' . escapeshellarg($imageName) . ' --file ' . escapeshellarg(basename($dockerFile)) . ' 2>&1';
					if ($runDebugMode) {
						echo "\n=[DEBUG]=========\n", $cmd, "\n=========[DEBUG]=\n";
					}
					exec($cmd, $out, $ret);
					if ($ret != 0) {
						return $out;
					}
					chdir($pwd);
				}

				$this->imageInfo['dockerfile'] = $dockerFile;
				$this->imageInfo['imageName'] = $imageName;
			}

			// Create temporary storage directory.
			if (!file_exists('./.aocbench_run/')) {
				mkdir('./.aocbench_run/');
			}

			return true;
		}

		/**
		 * Clean up after running the day(s).
		 */
		public function cleanup() {
			$branch = $this->getBranch();
			if (empty($branch)) {
				exec('git reset --hard origin 2>&1');
			} else {
				exec('git reset --hard origin/' . $branch . ' 2>&1');
			}
			exec('git clean -fx 2>&1');
		}

		public function getImageInfo() {
			return $this->imageInfo;
		}

		public function getCodeDir() { return $this->getAOCBenchConfig()['code'] ?? '/code'; }
		public function getPersistence() { return $this->getAOCBenchConfig()['persistence'] ?? []; }
		public function getEnvironment() { return $this->getAOCBenchConfig()['environment'] ?? []; }
		public function getCommonFiles() {
			$common = $this->getAOCBenchConfig()['common'] ?? [];
			if (!is_array($common)) { $common = [$common]; }
			$dockerFile = $this->getDockerfile();

			if ($dockerFile != null) {
				$common[] = $dockerFile;
			}
			$common[] = './.aocbench.yaml';
			$common[] = './.aocbench.yml';

			return $common;
		}

		private function getCanary() {
			if ($this->canary == null) {
				$this->canary = uniqid('AOCBENCH-CANARY-', true);
			}
			return $this->canary;
		}

		private function getRunScript($day, $scriptType, $opts) {
			$prerun = $this->getValueWithReplacements('prerun', $day);
			$runOnce = $this->getValueWithReplacements('runonce', $day);
			$hyperfineShell = $this->getAOCBenchConfig()['hyperfineshell'] ?? False;
			$cmd = $this->getValueWithReplacements('cmd', $day);
			$cmdWrapped = escapeshellarg($cmd);
			$workdir = $this->getValueWithReplacements('workdir', $day) ?? $this->getAOCBenchConfig()['code'];
			$canary = $this->getCanary();
			$hyperfineOutput = '/tmp/' . uniqid('aocbench-hyperfine-', true) . '.csv';

			switch ($scriptType) {
				case 'runonce':
					return <<<RUNSCRIPT
						#!/bin/bash

						cd $workdir
						echo '### $canary START - ONCE ###';
						$runOnce
						EXITCODE=\${?}
						echo '### $canary END ###';

						exit \${EXITCODE}
						RUNSCRIPT;

				case 'hyperfine':
					$warmup = $opts['warmup'] ?? 1;
					$min = $opts['min'] ?? ($opts['count'] ?? 5);
					$max = $opts['max'] ?? ($opts['count'] ?? 20);
					$shell = $opts['shell'] ?? ($hyperfineShell ? 'default' : 'none');

					return <<<RUNSCRIPT
						#!/bin/bash

						cd $workdir
						echo '### $canary START - PRE ###';
						$prerun
						echo '### $canary END ###';

						cd $workdir

						HYPERFINE=\$(which hyperfine)
						if [ -e "/aocbench-hyperfine" ]; then
							HYPERFINE=/aocbench-hyperfine
						elif [ "\${HYPERFINE}" == "" ]; then
							HYPERFINE=hyperfine
						fi;

						echo '### $canary START - HYPERFINEPATH ###';
						echo \$HYPERFINE
						echo '### $canary END ###';

						echo '### $canary START - HYPERFINEVERSION ###';
						\$HYPERFINE --version
						echo '### $canary END ###';

						echo '### $canary START - HYPERFINE ###';
						\$HYPERFINE -S $shell -w $warmup -m $min -M $max --export-json $hyperfineOutput -- $cmdWrapped
						EXITCODE=\${?}
						echo '### $canary END ###';

						echo '### $canary START - HYPERFINEDATA ###';
						cat $hyperfineOutput;
						echo '### $canary END ###';

						exit \${EXITCODE}
						RUNSCRIPT;

				case 'bulkinput':
					$inputFile = $this->getCodeDir() . '/' . $this->getInputFilename($day);
					// Allow the script to override it.
					chmod($this->getInputFilename($day), 0777);

					$script = <<<RUNSCRIPT
						#!/bin/bash

						cd $workdir
						echo '### $canary START - PRE ###';
						$prerun
						echo '### $canary END ###';

						cd $workdir
						RUNSCRIPT;
					$script .= "\n";

					foreach (array_keys($opts['files'] ?? []) as $file) {
						$script .= <<<RUNSCRIPT
							echo '### $canary START - INPUT - $file ###';
							cat /.aocbench_run/$file > $inputFile
							time $cmd
							EXITCODE=\${?}
							echo '### $canary END ###';
							echo '### $canary START - EXITCODE - $file ###';
							echo \${EXITCODE};
							echo '### $canary END ###';
							RUNSCRIPT;
						$script .= "\n";
					}

					$script .= <<<RUNSCRIPT
						exit 0;
						RUNSCRIPT;
					$script .= "\n";

					return $script;

				case 'time':
					return <<<RUNSCRIPT
						#!/bin/bash

						cd $workdir
						echo '### $canary START - PRE ###';
						$prerun
						echo '### $canary END ###';

						cd $workdir

						echo '### $canary START - TIME ###';
						time $cmd
						EXITCODE=\${?}
						echo '### $canary END ###';

						exit \${EXITCODE}
						RUNSCRIPT;

				default:
					return <<<RUNSCRIPT
						#!/bin/bash
						echo '### $canary START - ERROR ###';
						echo 'Unknown runscript type provided.';
						echo '### $canary END ###';
						exit 1
						RUNSCRIPT;
			}
		}

		private function getReplacements($day, $includeInput = true) {
			global $leaderboardYear;

			$replacements = [];
			$replacements['%year%'] = $leaderboardYear;
			$replacements['%day%'] = $day;
			$replacements['%zeroday%'] = sprintf('%02d', $day);
			$replacements['%dayzero%'] = sprintf('%02d', $day);
			if ($includeInput) {
				$replacements['%input%'] = $this->getCodeDir() . '/' . $this->getInputFilename($day);
			}

			return $replacements;
		}

		private function doReplacements($value, $day, $includeInput = true) {
			$replacements = $this->getReplacements($day, $includeInput);
			return str_replace(array_keys($replacements), array_values($replacements), $value);
		}

		private function getValueWithReplacements($value, $day) {
			$config = $this->getAOCBenchConfig();
			if (isset($config[$value])) {
				return $this->doReplacements($config[$value], $day, !in_array($value, ['daypath', 'inputfile', 'answerfile']));
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
					$realOutput[$section][] = preg_replace("/\r$/D", '', $line);
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

		public function getDayVersion($day) {
			$commonVersion = $this->getGitVersion($this->getCommonFiles());
			$dayVersion = $this->getGitVersion($this->getDayFilename($day));

			$versionPrefix = $this->getAOCBenchConfig()['versionprefix'] ?? '';
			if (!empty($versionPrefix)) {
				$commonVersion = $versionPrefix . ':' . $commonVersion;
			}

			return $commonVersion.':'.$dayVersion;
		}

		/**
		 * Do one-time run on the given day.
		 *
		 * @param int $day Day number.
		 * @return Array Array of [returnCode, outputFromRun] where outputFromRun is an array of lines of output.
		 */
		public function runOnce($day) {
			[$ret, $result] = $this->doRun($day, 'runonce');
			return [$ret, $result['ONCE'] ?? []];
		}

		/**
		 * Run the given day.
		 *
		 * @param int $day Day number.
		 * @return Array Array of [returnCode, outputFromRun] where outputFromRun is an array of lines of output.
		 */
		public function run($day) {
			[$ret, $result] = $this->doRun($day, 'run');
			return [$ret, $result['TIME'] ?? []];
		}

		/**
		 * Run the given day with hyperfine
		 *
		 * @param int $day Day number.
		 * @param array $opts Options for hyperfine run.
		 * @return Array Array of [returnCode, outputFromRun] where outputFromRun is an array of lines of output.
		 */
		public function runHyperfine($day, $opts = null) {
			[$ret, $result] = $this->doRun($day, 'hyperfine', !empty($opts) ? $opts : null);
			$result['HYPERFINEDATA'] = json_decode(implode("\n", $result['HYPERFINEDATA']), true);

			return [$ret, $result];
		}

		/**
		 * Run the given day.
		 *
		 * @param int $day Day number.
		 * @param bool $runType Run Type
		 * @param array $opts Options for this run
		 * @return Array Array of [returnCode, outputFromRun] where outputFromRun is an array of arrays of sections of output.
		 */
		public function doRun($day, $runType, $opts = []) {
			global $execTimeout, $localHyperfine, $runDebugMode;

			if (!is_array($opts)) { $opts = []; }

			if (($this->getAOCBenchConfig()['version'] ?? '') != "1") {
				return [1, ['TIME' => ['AoCBench Error: Unknown config file version (Got: "' . $this->getAOCBenchConfig()['version'] . '" - Wanted: "1").']]];
			}

			$imageName = $this->getDockerImageName();
			if ($imageName == null) {
				$dockerFile = $this->getDockerfile();
				if ($dockerFile == null) {
					return [1, ['TIME' => ['AoCBench Error: Unable to find docker file.']]];
				}
				return [1, ['TIME' => ['AoCBench Error: Unable to find docker image.']]];
			}

			$containerName = 'aocbench_' . uniqid(strtolower($this->getDirName(false)), true);

			// Run Command:
			$pwd = getcwd();

			if ($runType == 'hyperfine') { $scriptType = 'hyperfine'; }
			else if ($runType == 'runonce') { $scriptType = 'runonce'; }
			else if ($runType == 'bulkinput') { $scriptType = 'bulkinput'; }
			else { $scriptType = 'time'; }

			$runScriptFilename = './.aocbench_run/aocbench-' . uniqid(true) . '.sh';
			if ($runDebugMode) {
				$runScriptFilename = './.aocbench_run/aocbench-' . $day . '-' . $scriptType . '.sh';
			}

			file_put_contents($runScriptFilename, $this->getRunScript($day, $scriptType, $opts));
			chmod($runScriptFilename, 0777);

			$extraFilePath = $pwd . '/.aocbench_run/.aocbench_run/';
			if (file_exists($extraFilePath)) {
				rrmdir($extraFilePath);
			}
			mkdir($extraFilePath);
			chmod($extraFilePath, 0777);
			foreach ($opts['files'] ?? [] as $filename => $content) {
				$fullPath = $extraFilePath . '/' . $filename;
				file_put_contents($fullPath, $content);
				chmod($fullPath, 0777);
			}

			$cmd = 'docker run --init --rm ';
			if (!($this->getAOCBenchConfig()['notty'] ?? false)) {
				$cmd .= ' -it';
			}
			$cmd .= ' --name ' . escapeshellarg($containerName);
			$cmd .= ' --entrypoint ' . '/aocbench.sh';

			$cmd .= ' -v ' . escapeshellarg($pwd . ':' . $this->getCodeDir());

			foreach ($this->getPersistence() as $location) {
				if (str_starts_with($location, '/.aocbench_run')) { continue; }

				$location = $this->doReplacements($location, $day);
				$path = $pwd . '/.aocbench_run/' . crc32($location) . '_' . basename($location);
				if (!file_exists($path)) { mkdir($path); }
				chmod($path, 0777);
				$cmd .= ' -v ' . escapeshellarg($path . ':' . $location);
			}

			$cmd .= ' -v ' . escapeshellarg($extraFilePath . ':/.aocbench_run');

			if (file_exists($localHyperfine) && $this->useHyperfine() === true) {
				$cmd .= ' -v ' . escapeshellarg($localHyperfine . ':/aocbench-hyperfine');
			}
			if (file_exists('/usr/bin/bash-static')) {
				$cmd .= ' -v ' . escapeshellarg('/usr/bin/bash-static:/bin/bash');
			} else if (file_exists('/bin/bash-static')) {
				$cmd .= ' -v ' . escapeshellarg('/bin/bash-static:/bin/bash');
			}
			$cmd .= ' -v ' . escapeshellarg($pwd . '/' . $runScriptFilename . ':/aocbench.sh');

			foreach ($this->getEnvironment() as $env) {
				$env = explode('=', $env, 2);
				$env[1] = $this->doReplacements($env[1] ?? '', $day);
				$cmd .= ' -e ' . escapeshellarg(implode('=', $env));
			}

			$cmd .= ' ' . escapeshellarg($imageName);
			$cmd .= ' 2>&1';

			if ($scriptType == 'hyperfine') {
				$estimatedTime = ceil($opts['estimated'] ?? $execTimeout);
				$runCount = $opts['max'] ?? ($opts['count'] ?? 20);

				$thisExecTimeout = ($estimatedTime * $runCount) + 60;
			} else if ($scriptType == 'bulkinput') {
				$estimatedTime = ceil($opts['estimated'] ?? $execTimeout);
				$runCount = count($opts['files'] ?? []);

				$thisExecTimeout = ($estimatedTime * $runCount) + 60;
			} else {
				$thisExecTimeout = $execTimeout;
			}

			if ($runDebugMode && !empty($opts)) {
				echo "\n=[DEBUG opts]=========\n", json_encode($opts, JSON_PRETTY_PRINT), "\n=========[DEBUG]=\n";
			}

			if ($runDebugMode) {
				echo "\n=[DEBUG {$scriptType} {$thisExecTimeout}]=========\n", $cmd, "\n=========[DEBUG]=\n";
			}

			$output = [];
			$ret = -1;
			dockerTimedExec($containerName, $cmd, $output, $ret, $thisExecTimeout);
			if (!$runDebugMode) {
				@unlink($runScriptFilename);
			}

			if ($runDebugMode) {
				echo "\n=[DEBUG output]=========\n", json_encode($output, JSON_PRETTY_PRINT), "\n=========[DEBUG]=\n";
			}

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
