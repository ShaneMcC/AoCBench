<?php
	require_once(__DIR__ . '/Participant.php');
	require_once(__DIR__ . '/config.php');

	function getLock() {
		global $lockfile, $__LOCK;

		if (!file_exists($lockfile)) { file_put_contents($lockfile, ''); }

		$__LOCK = fopen($lockfile, 'r+');
		if (!flock($__LOCK, LOCK_EX | LOCK_NB)) {
			echo 'Unable to get lock on ', $lockfile, "\n";
			exit(1);
		}
	}

	function loadData($file) {
		$data = ['results' => []];
		if (file_exists($file)) {
			$data = json_decode(file_get_contents($file), true);
		}

		return $data;
	}

	// Save Data.
	function saveData($file, $data, $setTime = false) {
		if ($setTime || !isset($data['time'])) { $data['time'] = time(); }

		// Output results to disk.
		file_put_contents($file, json_encode($data));
	}

	function getOptionValue($short = NULL, $long = NULL, $default = '') {
		global $__CLIOPTS;

		if ($short !== NULL && array_key_exists($short, $__CLIOPTS)) { $val = $__CLIOPTS[$short]; }
		else if ($long !== NULL && array_key_exists($long, $__CLIOPTS)) { $val = $__CLIOPTS[$long]; }
		else { $val = $default; }

		if (is_array($val)) { $val = array_pop($val); }

		return $val;
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

	function parseTime($time) {
		if (preg_match('#^([0-9]+)m\s?([0-9]+).([0-9]+)s$#', $time, $match)) {
			list($all, $m, $s, $ms) = $match;

			$ms = str_pad($ms, 3, '0');
			$time = $ms + ($s * 1000) + ($m * 60 * 1000);

			return $time;
		}

		return NULL;
	}

	function formatTime($time) {
		if ($time === FALSE) { return ''; }

		$m = $s = $ms = 0;

		if ($time > 60 * 1000) {
			$m = floor($time / (60 * 1000));
			$time -= $m * 60 * 1000;
		}

		if ($time > 1000) {
			$s = floor($time / (1000));
			$time -= $s * 1000;
		}

		$ms = $time;

		return sprintf('%dm%d.%03ds', $m, $s, $ms);
	}

	function getSortedTimes($times, $format = true) {
		$parsedTimes = [];
		foreach ($times as $time) {
			$time = parseTime($time);
			if ($time !== NULL) {
				$parsedTimes[] = $time;
			}
		}
		sort($parsedTimes);

		if ($format) {
			foreach ($parsedTimes as &$time) {
				$time = formatTime($time);
			}
		}

		return $parsedTimes;
	}

	function appexec($path, &$process, $stderr = false, $cwd = null, $env = null, $options = null) {
		$descriptorspec = array(0 => array("pty"),
		                        1 => array("pipe", "w"),
		                        2 => ($stderr ? array("pipe", "w") : array("file", "/dev/null", "a"))
		                       );
		$process = array();
		$process['process'] = proc_open($path, $descriptorspec, $process['pipes'], $cwd, $env, $options);
		return is_resource($process['process']);
	}

	function getLastContainerID() {
		$out = [];
		exec('docker ps -n 1', $out);
		return isset($out[1]) ? explode(' ', $out[1])[0] : '';
	}

	function dockerTimedExec($command, &$output = array(), &$return_var = 0, $timeout = 0) {
		$before = getLastContainerID();

		$options = '';
		appexec($command.' 2>&1', $proc, false);

		sleep(1);
		$after = getLastContainerID();

		$commandout = '';
		$timedout = false;
		if ($timeout > 0) {
			$endtime = time() + $timeout;
			while (true) {
				$r = array($proc['pipes'][1]);
				$w = null;
				$e = null;
				$num = @stream_select($r, $w, $e, 0, 200000);
				if ($num !== false && $num > 0) {
					$line = fgets($proc['pipes'][1]);
					$commandout .= rtrim($line, "\r");
					if (strlen($line) == 0) { break; }
				}

				if (time() > $endtime) {
					$commandout .= "\n\n".'*** Command timeout ('.$timeout.') exceeded.';
					$timedout = true;
					break;
				}
			}
		} else {
			$commandout = stream_get_contents($proc['pipes'][0]);
		}
		$commandout = explode("\n", trim($commandout));

		foreach ($proc['pipes'] as $p) { fclose($p); }
		if ($timedout) {
			if ($before != $after) {
				// Kill the docker container (we think) we started.
				exec('docker kill ' . $after);
			}

			proc_terminate($proc['process']);
			proc_close($proc['process']);
			$return_var = 124;
		} else {
			$return_var = proc_close($proc['process']);
		}
		$output = array_merge($output, $commandout);

		if ($timedout) {
			return FALSE;
		} else {
			$lines = count($commandout);
			return ($lines > 0) ? $commandout[$lines - 1] : '';
		}
	}
