<?php
	require_once(__DIR__ . '/functions.php');

	$pageid = 'health';
	require_once(__DIR__ . '/header.php');

	if ($hasHealthCheck) {
		echo '<h2>Health Check</h2>', "\n";

        echo '<ul>';
        foreach ($data['healthcheck'] as $person => $pdata) {
            echo '<li><a href="#', $person, '">', $pdata['name'], '</a></li>';
        }
        echo '</ul>';

        foreach ($data['healthcheck'] as $person => $pdata) {
            echo '<h3 id="', $person, '">', $pdata['name'], '</h3>';

			echo '<strong>Valid:</strong> ', ($pdata['valid'] ? 'true' : 'false'), '<br>';
            echo '<strong>Prepared:</strong> ', ($pdata['prepared'] ? 'true' : 'false'), '<br>';
            echo '<strong>Participant Type:</strong> ', ($pdata['participanttype'] == 2 ? 'yaml' : 'legacy'), '<br>';
            echo '<strong>Config:</strong>';
            echo '<code><pre>';
            echo spyc_dump($pdata['config']);
            echo '</pre></code>';

			if (isset($pdata['repo']) && !empty($pdata['repo'])) {
				echo '<strong>Repo:</strong> <a href="' . $pdata['repo'] . '"><img height="16px" width="16px" src="github.ico" alt="github">' . $pdata['repo'] . '</a>';
                if (isset($pdata['branch']) && !empty($pdata['branch'])) {
                    echo ' (<strong>Branch:</strong> ' . $pdata['branch'] . ')';
                }
                echo '<br>';
			}

			if (isset($pdata['language']) && !empty($pdata['language'])) {
				$langList = is_array($pdata['language']) ? $pdata['language'] : [$pdata['language']];
				foreach ($langList as $l) {
					$language .= $l . ' / ';
				}
				$language = rtrim($language, ' /');
			} else {
				$language = '';
			}
            if (!empty($language)) {
                $language = '<strong>Language:</strong> ' . $language;
            }

            echo '<table class="table table-bordered table-striped">';
            foreach ($pdata['days'] as $day => $ddata) {
                $rowspan = 1;

                ob_start();
                echo '<tr class="', ($ddata['exists'] ? 'table-success' : 'table-true'), '">';
                echo '<th>Day Exists</th>';
                echo '<td>', ($ddata['exists'] ? 'true' : 'false'), '</td>';
                echo '</tr>';
                $rowspan++;

                if ($ddata['exists']) {
                    echo '<tr class="', ($ddata['wip'] ? 'table-danger' : 'table-success'), '">';
                    echo '<th>WIP</th>';
                    echo '<td>', ($ddata['wip'] ? 'true' : 'false'), '</td>';
                    echo '</tr>';
                    $rowspan++;

                    echo '<tr class="', ($ddata['ignored'] ? 'table-danger' : 'table-success'), '">';
                    echo '<th>Ignored</th>';
                    echo '<td>', ($ddata['ignored'] ? 'true' : 'false'), '</td>';
                    echo '</tr>';
                    $rowspan++;

                    $bits = explode(':', $ddata['version']);
                    echo '<tr>';
                    echo '<th>Common Version</th>';
                    echo '<td>', $bits[0], '</td>';
                    echo '</tr>';
                    $rowspan++;

                    echo '<tr>';
                    echo '<th>Day Version</th>';
                    echo '<td>', $bits[1], '</td>';
                    echo '</tr>';
                    $rowspan++;

                    echo '<tr>';
                    echo '<th>Input Version</th>';
                    echo '<td>', $ddata['input']['version'], '</td>';
                    echo '</tr>';
                    $rowspan++;

                    echo '<tr>';
                    echo '<th>Answers Version</th>';
                    echo '<td>', $ddata['answers']['version'], '</td>';
                    echo '</tr>';
                    $rowspan++;

                    echo '<tr class="', ($ddata['answers']['part1'] ? 'table-success' : 'table-danger'), '">';
                    echo '<th>Has part 1 answer?</th>';
                    echo '<td>', ($ddata['answers']['part1'] ? 'true' : 'false'), '</td>';
                    echo '</tr>';
                    $rowspan++;

                    echo '<tr class="', ($ddata['answers']['part2'] ? 'table-success' : 'table-danger'), '">';
                    echo '<th>Has part 2 answer?</th>';
                    echo '<td>', ($ddata['answers']['part2'] ? 'true' : 'false'), '</td>';
                    echo '</tr>';
                    $rowspan++;

                    echo '<tr>';
                    echo '<th>Run Type</th>';
                    echo '<td>', $ddata['runtype'], '</td>';
                    echo '</tr>';
                    $rowspan++;

                    echo '<tr>';
                    echo '<th>Run Length</th>';
                    echo '<td>', $ddata['length'], '</td>';
                    echo '</tr>';
                    $rowspan++;

                    echo '<tr>';
                    echo '<th>Log</th>';
                    echo '<td>', $ddata['log'], '</td>';
                    echo '</tr>';
                    $rowspan++;
                }
                $dayRows = ob_get_clean();

                $class = '';

                echo '<tbody>';
                echo '<tr>';
                echo '<th rowspan=' . $rowspan. ' style="width: 50px" class="', $class, '">', $day, '</th>';
                echo '</tr>';
                echo $dayRows;
                echo '</tbody>';
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
