<?php
	require_once(__DIR__ . '/functions.php');

	$pageid = 'ranking';
	$fluid = count($data['results']) > 4;
	require_once(__DIR__ . '/header.php');

	$method = $_REQUEST['method'] ?? ($_SESSION['method'] ?? 'MEDIAN');
	$timeFormat = $_REQUEST['times'] ?? ($_SESSION['times'] ?? 'DEFAULT');
	$lang = $_REQUEST['lang'] ?? ($_SESSION['lang'] ?? '*');
	if (!is_array($lang)) { $lang = [$lang]; }

	$_SESSION['method'] = $method;
	$_SESSION['times'] = $timeFormat;
	$_SESSION['lang'] = $lang;

	if ($hasResults) {
		echo '<h1>Rankings</h1>', "\n";

		$dayLinks = [];
		if (isset($_REQUEST['day'])) {
			$dayLinks[] = ' <a href="?">All</a>';
		}
		for ($day = 1; $day <= 25; $day++) {
			$times = getDayParticipantTimes($day, $method, $timeFormat);
			if (!empty($times)) {
				if (isset($_REQUEST['day'])) {
					if ($day == $_REQUEST['day']) {
						$dayLinks[] = ' <strong>' . $day . '</strong>';
					} else {
						$dayLinks[] = ' <a href="?day=' . $day . '">' . $day . '</a>';
					}
				} else {
					$dayLinks[] = ' <a href="#day' . $day . '">' . $day . '</a>';
				}
			}
		}

        $dayLink = isset($_REQUEST['day']) ? '&day=' . $_REQUEST['day'] : '';

		$averagingLinks = [];
		foreach (['MEDIAN' => 'Median', 'MIN' => 'Minimum', 'Mean' => 'Mean', 'MAX' => 'Maximum'] as $m => $title) {
			$link = '<a href="?method=' . $m . $dayLink . '">' . $title . '</a>';
			if (strtoupper($m) == strtoupper($method)) { $link = '<strong>' . $link . '</strong>'; }

			$averagingLinks[] = $link;
		}

		$timeLinks = [];
		foreach (['DEFAULT' => 'Default', 'SECONDS' => 's', 'MILLISECONDS' => 'ms', 'MICROSECONDS' => 'μs', 'NANOSECONDS' => 'ns', 'PICOSECONDS' => 'ps'] as $m => $title) {
			$link = '<a href="?times=' . $m . $dayLink . '">' . $title . '</a>';
			if (strtoupper($m) == strtoupper($timeFormat)) { $link = '<strong>' . $link . '</strong>'; }

			$timeLinks[] = $link;
		}

		echo '<div class="text-muted text-right sticky"><div style="display: inline-block; background: white; border: 1px solid black; padding: 5px">';
		echo '<small>';
		echo '<strong>Averaging:</strong> ', implode(' - ', $averagingLinks);
		echo '<br>';
		echo '<strong>Times:</strong> ', implode(' - ', $timeLinks);
		if ($lang != ['*']) {
			echo '<br>';
			echo '<strong>Language Filter:</strong> <a href="?lang=*">Reset Language Filter</a>';
		}
		echo '<br>';
		echo '<strong>Days:</strong> ', implode(' - ', $dayLinks);
		echo '</small>';
		echo '</div></div>';

		// Participants
		$p = 1;
		if (empty($displayParticipants)) {
			if ($lang === True) {
				$displayParticipants = array_keys($data['results']);
			} else {
				$displayParticipants = [];
				foreach ($data['results'] as $person => $pdata) {
					if (!isset($pdata['language']) || empty($pdata['language'])) { continue; }
					$langList = is_array($pdata['language']) ? $pdata['language'] : [$pdata['language']];
					$includeMe = false;
					foreach ($langList as $thisLang) {
						if (in_array($thisLang, $lang) || in_array('*', $lang)) {
							$includeMe = true;
						}
						if (in_array('!' . $thisLang, $lang)) {
							$includeMe = false;
							break;
						}
					}
					if ($includeMe) {
						$displayParticipants[] = $person;
					}
				}
			}
		}

        for ($day = 1; $day <= 25; $day++) {
            if (isset($_REQUEST['day']) && $_REQUEST['day'] != $day) { continue; }

            $times = getDayParticipantTimes($day, $method);
			if (empty($times)) { continue; }

			$lastTime = $firstTime = array_column($times, 'timeraw')[0] ?? 0;
			$ranks = array_count_values(array_column($times, 'rank'));

			$lastRank = 0;
			echo '<br><br>';
			echo '<h2 id="day' , $day, '">Day ', $day, '</h2>';
			echo '<table class="table table-striped table-bordered">';
			echo '<tr>';
			echo '<th>Rank</th>';
			echo '<th>Participant</th>';
			echo '<th>Language</th>';
			echo '<th>' . ucfirst(strtolower($method)) . ' Time</th>';
			echo '<th>Difference from first</th>';
			echo '<th>Difference from previous</th>';
			echo '</tr>';
			foreach ($times as $participant => $timeData) {
				if (!isset($data['results'][$participant])) { continue; }
				$pdata = $data['results'][$participant];
				// $time = getParticipantTime($pdata['days'][$day]['times'], $method);
				$time = $times[$participant]['timeraw'];
				$min = getParticipantTime($pdata['days'][$day]['times'], 'MIN');
				$max = getParticipantTime($pdata['days'][$day]['times'], 'MAX');

				$diffFirst = $time - $firstTime;
				$diffPrev = $time - $lastTime;
				$lastTime = $time;

				if (isset($pdata['repo']) && !empty($pdata['repo'])) {
					$link = '<a href="' . $pdata['repo'] . '"><img height="16px" width="16px" src="github.ico" alt="github"></a>';
				} else {
					$link = '';
				}

				if (!isset($pdata['language']) || empty($pdata['language'])) { $pdata['language'] = []; }
				$langList = is_array($pdata['language']) ? $pdata['language'] : [$pdata['language']];

				$langList = is_array($pdata['language']) ? $pdata['language'] : [$pdata['language']];
				$language = '';
				foreach ($langList as $l) {
					$language .= '<a href="?method=' . urlencode($method) . '&lang[]=' . urlencode($l) . '">' . $l . '</a> / ';
				}
				$language = rtrim($language, ' /');

				$rankClass = ['1' => 'table-first', '2' => 'table-second', '3' => 'table-third'];

				echo '<tr class="', ($rankClass[$timeData['rank']] ?? ''), '">';
				if ($lastRank != $timeData['rank']) {
					echo '<th rowspan="', $ranks[$timeData['rank']], '">', $timeData['rank'], '</th>';
				}
				echo '<th>', $participant, ' ' , $link, '</th>';
				echo '<td>', $language, '</td>';
				echo '<td>', formatTime($time, $timeFormat), '</td>';
				echo '<td>', $diffFirst > 0 ? '+' . formatTime($diffFirst, $timeFormat) : '-', '</td>';
				echo '<td>', $diffPrev > 0 ? '+' . formatTime($diffPrev, $timeFormat) : '-', '</td>';
				echo '</tr>';

				$lastRank = $timeData['rank'];
			}
			echo '</table>';
        }

        echo '<p class="text-muted text-right"><small>';
        if (isset($data['time'])) {
            echo ' <span>Last updated: ', date('r', $data['time']), '</span>';
        }
        if (!empty($logFile) && file_exists($logFile)) {
            echo ' <span><a href="log.php">log</a></span>';
        }
        echo '</small></p>';

        echo '<script src="./index.js"></script>';
	} else {
		echo 'No results yet.';
	}

	require_once(__DIR__ . '/footer.php');
