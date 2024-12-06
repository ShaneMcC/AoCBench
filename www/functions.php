<?php
	require_once(__DIR__ . '/../functions.php');

	session_start();

	$method = $_REQUEST['method'] ?? ($_SESSION['method'] ?? 'MEDIAN');
	$timeFormat = $_REQUEST['times'] ?? ($_SESSION['times'] ?? 'DEFAULT');
	$lang = $_REQUEST['lang'] ?? ($_SESSION['lang'] ?? '*');
	if (!is_array($lang)) { $lang = [$lang]; }

	$_SESSION['method'] = $method;
	$_SESSION['times'] = $timeFormat;
	$_SESSION['lang'] = $lang;

	// Default to 0, to disable showing banner.
	$lastScheduledRunTime = 0;

	if (file_exists(__DIR__ . '/../.doRun')) {
		$lastScheduledRunTime = filemtime(__DIR__ . '/../.doRun');
	}

	if (isset($instanceid) && !empty($instanceid) && file_exists($schedulerStateFile)) {
		$schedulerState = json_decode(file_get_contents($schedulerStateFile), true);

		$lastScheduledRunTime = $schedulerState[$instanceid]['time'] ?? 0;
	}

	$lastMatrixStartTime = $lastMatrixEndTime = $lastBenchStartTime = $lastBenchEndTime = time();

	$hasResults = false;
	$hasHealthCheck = false;
	if (file_exists($resultsFile)) {
		$data = json_decode(file_get_contents($resultsFile), true);
		if (isset($data['results'])) {
			$hasResults = true;
			$lastBenchEndTime = $data['finishtime'] ?? time();
			$lastBenchStartTime = $data['starttime'] ?? time();
		}
		if (isset($data['healthcheck'])) {
			$hasHealthCheck = true;
		}
	}

	$hasMatrix = false;
	if (file_exists($outputResultsFile)) {
		$matrix = json_decode(file_get_contents($outputResultsFile), true);
		if (isset($matrix['results'])) {
			$hasMatrix = true;
			$lastMatrixEndTime = $matrix['finishtime'] ?? time();
			$lastMatrixStartTime = $matrix['starttime'] ?? time();
		}
	}

	function getDayParticipantTimes($day, $method, $format = null) {
		global $data, $displayParticipants;

		if (!isset($data['results'])) { return []; }

		$times = [];
		foreach ($data['results'] as $participant => $pdata) {
			if (!empty($displayParticipants) && !in_array($participant, $displayParticipants)) { continue; }

			if (isset($pdata['days'][$day]['times'])) {
				$times[$participant] = ['timeraw' => getParticipantTime($pdata['days'][$day]['times'], $method)];

				if ($format == null) {
					$times[$participant]['time'] = $times[$participant]['timeraw'];
				} else {
					$times[$participant]['time'] = formatTime($times[$participant]['timeraw'], $format);
				}

				$times[$participant]['at'] = $pdata['days'][$day]['time'] ?? 0;
			}
		}

		uksort($times, function($a, $b) use ($times) {
			$r = $times[$a]['timeraw'] <=> $times[$b]['timeraw'];
			if ($r == 0) { $r = $times[$a]['at'] <=> $times[$b]['at']; }
			if ($r == 0) { $r = $a <=> $b; }
			return $r;
		});

		$lastTime = null;
		$displayRank = $rankCount = 0;
		foreach ($times as &$data) {
			$rankCount++;
			if ($lastTime !== $data['time']) {
				$displayRank = $rankCount;
			}

			$data['rank'] = $displayRank;

			$lastTime = $data['time'];
		}

		return $times;
	}

	function getDayBestTimes($day, $method) {
		return array_column(getDayParticipantTimes($day, $method), 'time');
	}

	function getParticipantTime($times, $method) {
		$parsedTimes = getSortedTimes($times, false);

		switch (strtoupper($method)) {
			case 'STDDEV':
				return stats_standard_deviation($parsedTimes);

			case 'SPECIAL':
			case 'MEANBEST':
				$parsedTimes = array_chunk($parsedTimes, count($parsedTimes) - 5)[0];
				return array_sum($parsedTimes) / count($parsedTimes);

			case 'AVG':
			case 'MEAN':
				return array_sum($parsedTimes) / count($parsedTimes);

			case 'MIN':
			case 'MINIMUM':
				return $parsedTimes[0];

			case 'MAX':
			case 'MAXIMUM':
				return $parsedTimes[count($parsedTimes) - 1];

			case 'MEDIAN':
			default:
				$count = count($parsedTimes);
				$middle = floor(($count - 1) / 2);

				if ($count % 2) {
					return $parsedTimes[$middle];
				} else {
					$low = $parsedTimes[$middle];
					$high = $parsedTimes[$middle + 1];
					return (($low + $high) / 2);
				}
		}

	}

	if (!function_exists('stats_standard_deviation')) {
		/**
		 * This user-land implementation follows the implementation quite strictly;
		 * it does not attempt to improve the code or algorithm in any way. It will
		 * raise a warning if you have fewer than 2 values in your array, just like
		 * the extension does (although as an E_USER_WARNING, not E_WARNING).
		 *
		 * From: https://www.php.net/manual/en/function.stats-standard-deviation.php
		 *
		 * @param array $a
		 * @param bool $sample [optional] Defaults to false
		 * @return float|bool The standard deviation or false on error.
		 */
		function stats_standard_deviation(array $a, $sample = false) {
			$n = count($a);
			if ($n === 0) {
				trigger_error("The array has zero elements", E_USER_WARNING);
				return false;
			}
			if ($sample && $n === 1) {
				trigger_error("The array has only 1 element", E_USER_WARNING);
				return false;
			}
			$mean = array_sum($a) / $n;
			$carry = 0.0;
			foreach ($a as $val) {
				$d = ((double) $val) - $mean;
				$carry += $d * $d;
			};
			if ($sample) {
			   --$n;
			}
			return sqrt($carry / $n);
		}
	}

	function cacheBuster() {
		$files = ['style.css', 'index.js', 'graphs.js'];

		$mtime = 0;
		foreach ($files as $f) {
			if (file_exists(__DIR__ . '/' . $f)) {
				$mtime = max($mtime, filemtime(__DIR__ . '/' . $f));
			}
		}

		return $mtime;
	}

	function convertRepoURL($repourl) {
		if (!str_starts_with($repourl, 'http') && preg_match('#^.*@(.*):(.*?)(?:\.git)?$#', $repourl, $m)) {
			$repourl = 'https://' . $m[1] . '/' . $m[2];
		}
		return $repourl;
	}

	function repoFileAsLink($participant, $file, $title = null) {
		if ($title == null) { $title = $file; }

		$repourl = convertRepoURL($participant['repo']);

		$link = $title;
		if (stristr($repourl, 'github.com') !== false) {
			$branch = $participant['branch'] ?? '-';
			$url = $repourl . '/blob/' . $branch .'/' . $file;
			$link = '<a href="' . $url . '">' . $title . '</a>';
		} else if (stristr($repourl, 'gitlab.com') !== false) {
			// HEAD is not *quite* right, but close enough in most cases I guess.
			$branch = $participant['branch'] ?? 'HEAD';
			$url = $repourl . '/-/blob/' . $branch . '/' . $file;
			$link = '<a href="' . $url . '">' . $title . '</a>';
		} else {
			// Guess at something half-standard hopefully?
			$branch = $participant['branch'] ?? 'HEAD';
			$url = $repourl . '/src/branch/' . $branch . '/' . $file;
			$link = '<a href="' . $url . '">' . $title . '</a>';
		}

		return $link;
	}

	function repoCommitAsLink($participant, $commit, $title = null) {
		if ($title == null) { $title = $commit; }

		$repourl = convertRepoURL($participant['repo']);

		$link = $title;
		if (stristr($repourl, 'github.com') !== false) {
			$url = $repourl . '/commit/' . $commit;
			$link = '<a href="' . $url . '">' . $title . '</a>';
		} else if (stristr($repourl, 'gitlab.com') !== false) {
			$url = $repourl . '/-/commit/' . $commit;
			$link = '<a href="' . $url . '">' . $title . '</a>';
		} else {
			// Guess at something half-standard hopefully?
			$url = $repourl . '/commit/' . $commit;
			$link = '<a href="' . $url . '">' . $title . '</a>';
		}


		return $link;
	}

	function getPartitipantLink($pdata) {
		global $hasHealthCheck;

		$link = $pdata['name'];

		if (isset($pdata['repo']) && !empty($pdata['repo'])) {
			$link .= ' <a href="' . $pdata['repo'] . '"><img height="16px" width="16px" src="github.ico" alt="github"></a>';
		}

		if ($hasHealthCheck) {
			$link .= ' <a href="./health.php?person=' . $pdata['name'] . '">🗹</a>';
		}

		$link .= ' <a href="./matrix.php?participant=' . $pdata['name'] . '">📋</a>';

		return $link;
	}

	if (!function_exists('str_starts_with')) {
		function str_starts_with($haystack, $needle) {
			return strlen($needle) === 0 || strpos($haystack, $needle) === 0;
		}
	}
