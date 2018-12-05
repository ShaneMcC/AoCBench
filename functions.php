<?php
	require_once(__DIR__ . '/Participant.php');
	require_once(__DIR__ . '/config.php');

	function getLock() {
		global $lockfile;

		if (!file_exists($lockfile)) { file_put_contents($lockfile, ''); }

		$fp = fopen($lockfile, 'r+');
		if (!flock($fp, LOCK_EX | LOCK_NB)) {
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
	function saveData($file, $data) {
		if (!isset($data['time'])) { $data['time'] = time(); }

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
		if (empty($time)) { return ''; }

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
