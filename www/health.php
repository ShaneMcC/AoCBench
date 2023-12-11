<?php
	require_once(__DIR__ . '/functions.php');

	$pageid = 'health';
	require_once(__DIR__ . '/header.php');

	if ($hasHealthCheck) {
		echo '<h1>Health Check</h1>', "\n";

        echo '<ul>';
        foreach ($data['healthcheck'] as $person => $pdata) {
            echo '<li><a href="?person=', $person, '">', $pdata['name'], '</a></li>';
        }
        echo '<li><a href="?person=*">All Participants</a></li>';
        echo '<li><a href="?person=*&onlybad">All Participants, Bad Only</a></li>';
        echo '</ul>';

        foreach ($data['healthcheck'] as $person => $pdata) {
            if ($_REQUEST['person'] != '*' && $_REQUEST['person'] != $person) {
                continue;
            }

            echo '<h3 id="', $person, '">', $pdata['name'], '</h3>';

			echo '<strong>Valid:</strong> ', ($pdata['valid'] ? 'true' : 'false - ' . $data['valid_info']), '<br>';
            echo '<strong>Prepared:</strong> ', ($pdata['prepared'] ? 'true' : 'false');
            if (!$pdata['prepared']) {
                echo '<button href="#" data-toggle="collapse" data-target="#' . $person . '-prepare" class="btn btn-sm btn-secondary">show/hide</button>';
                echo '<code id="' . $person . '-prepare" class="collapse codeview"><pre>';
                echo htmlspecialchars($data['prepare_info']);
                echo '</pre></code><br>';
            }
            echo '<br>';
            echo '<strong>Participant Type:</strong> ', ($pdata['participanttype'] == 2 ? 'yaml' : 'legacy'), '<br>';
            echo '<strong>Config:</strong> ';
            echo '<button href="#" data-toggle="collapse" data-target="#' . $person . '-config" class="btn btn-sm btn-secondary">show/hide</button>';
            echo '<code id="' . $person . '-config" class="collapse codeview"><pre>';
            echo spyc_dump($pdata['config']);
            echo '</pre></code><br>';

			if (isset($pdata['repo']) && !empty($pdata['repo'])) {
				echo '<strong>Repo:</strong> <a href="' . $pdata['repo'] . '"><img height="16px" width="16px" src="github.ico" alt="github"> ' . $pdata['repo'] . '</a>';
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

                $unlock = strtotime($leaderboardYear . '-12-' . $day . ' 05:00+0000');
                if ($unlock > time()) { continue; }

                $rowspan = 1;

                $dayClass = 'table-success';

                ob_start();
                echo '<tr>';
                echo '<th>Log</th>';
                echo '<td>';
                echo $ddata['log'];
                if (!empty($ddata['log']) && $ddata['logtime'] > 0) {
                    echo '<br><span class="muted"><small>@ ' . date('r', $ddata['logtime']) . '</small></span>';
                }
                echo '</td>';
                echo '</tr>';
                $rowspan++;

                echo '<tr class="collapse dayinfo ', ($ddata['exists'] ? 'table-success' : 'table-danger'), '">';
                echo '<th>Day Exists</th>';
                echo '<td>', ($ddata['exists'] ? 'true' : 'false'), '</td>';
                echo '</tr>';
                $rowspan++;

                if ($ddata['exists']) {
                    echo '<tr class="collapse dayinfo ', ($ddata['wip'] ? 'table-warning' : 'table-success'), '">';
                    echo '<th>WIP</th>';
                    echo '<td>', ($ddata['wip'] ? 'true' : 'false'), '</td>';
                    echo '</tr>';
                    if ($ddata['wip']) { $dayClass = 'table-warning'; }
                    $rowspan++;

                    echo '<tr class="collapse dayinfo ', ($ddata['ignored'] ? 'table-warning' : 'table-success'), '">';
                    echo '<th>Ignored</th>';
                    echo '<td>', ($ddata['ignored'] ? 'true' : 'false'), '</td>';
                    echo '</tr>';
                    if ($ddata['ignored']) { $dayClass = 'table-warning'; }
                    $rowspan++;

                    $bits = explode(':', $ddata['version']);
                    echo '<tr class="collapse dayinfo">';
                    echo '<th>Common Version</th>';
                    echo '<td>', $bits[0], '</td>';
                    echo '</tr>';
                    $rowspan++;

                    echo '<tr class="collapse dayinfo">';
                    echo '<th>Day Version</th>';
                    echo '<td>', $bits[1], '</td>';
                    echo '</tr>';
                    $rowspan++;

                    echo '<tr class="collapse dayinfo ', (empty($ddata['input']['version']) ? 'table-danger' : ''), '">';
                    echo '<th>Input Version</th>';
                    echo '<td>', $ddata['input']['version'], '</td>';
                    echo '</tr>';
                    if (empty($ddata['input']['version'])) { $dayClass = 'table-danger'; }
                    $rowspan++;

                    echo '<tr class="collapse dayinfo ', (empty($ddata['answers']['version']) ? 'table-danger' : ''), '">';
                    echo '<th>Answers Version</th>';
                    echo '<td>', $ddata['answers']['version'], '</td>';
                    echo '</tr>';
                    if (empty($ddata['answers']['version'])) { $dayClass = 'table-danger'; }
                    $rowspan++;

                    echo '<tr class="collapse dayinfo ', ($ddata['answers']['part1'] ? 'table-success' : 'table-danger'), '">';
                    echo '<th>Has part 1 answer?</th>';
                    echo '<td>', ($ddata['answers']['part1'] ? 'true' : 'false'), '</td>';
                    echo '</tr>';
                    if (!$ddata['answers']['part1']) { $dayClass = 'table-danger'; }
                    $rowspan++;

                    echo '<tr class="collapse dayinfo ', ($ddata['answers']['part2'] ? 'table-success' : 'table-danger'), '">';
                    echo '<th>Has part 2 answer?</th>';
                    echo '<td>', ($ddata['answers']['part2'] ? 'true' : 'false'), '</td>';
                    echo '</tr>';
                    if (!$ddata['answers']['part2']) { $dayClass = 'table-danger'; }
                    $rowspan++;

                    if (isset($ddata['runonce'])) {
                        echo '<tr class="collapse dayinfo ', ($ddata['runonce'] ? 'table-success' : 'table-danger'), '">';
                        echo '<th>Run Once</th>';
                        echo '<td>';
                        echo ($ddata['runonce'] ? 'true' : 'false');

                        if (isset($data['runonce_info'])) {
                            echo '<button href="#" data-toggle="collapse" data-target="#' . $person . '-' . $day . '-runonce" class="btn btn-sm btn-secondary">show/hide</button>';
                            echo '<code id="' . $person . '-' . $day . '-runonce" class="collapse codeview"><pre>';
                            echo htmlspecialchars($data['runonce_info']);
                            echo '</pre></code><br>';
                        }

                        echo '</td>';
                        echo '</tr>';
                        if (!$ddata['runonce']) { $dayClass = 'table-danger'; }
                        $rowspan++;
                    }

                    if (empty($ddata['runtype'])) { $runClass = 'table-success'; } // For now.
                    else if ($ddata['runtype'] == 'hyperfine') { $runClass = 'table-success'; }
                    else if ($ddata['runtype'] == 'time' && $pdata['participantversion'] != 1) {
                        $runClass = 'table-warning';
                    } else {
                        $runClass = 'table-danger';
                    }

                    if ($runClass == 'table-warning' && $dayClass != 'table-danger') { $dayClass = 'table-warning'; }
                    if ($runClass == 'table-danger') { $dayClass = 'table-danger'; }

                    echo '<tr class="collapse dayinfo ', $runClass, '">';
                    echo '<th>Run Type</th>';
                    echo '<td>', $ddata['runtype'], '</td>';
                    echo '</tr>';
                    $rowspan++;

                    if (!empty($ddata['length']) && $ddata['length'] != 'normal' && $dayClass != 'table-danger') { $dayClass = 'table-warning'; }
                    echo '<tr class="collapse dayinfo ', (!empty($ddata['length']) && $ddata['length'] != 'normal' ? 'table-warning' : 'table-success'), '">';
                    echo '<th>Run Length</th>';
                    echo '<td>', $ddata['length'], '</td>';
                    echo '</tr>';
                    $rowspan++;

                    $rowClass = 'table-success';
                    $rowData = '';
                    if (isset($matrix['results'][$person]['days'][$day])) {
                        foreach ($matrix['results'][$person]['days'][$day]['outputs'] as $targetPerson => $odata) {
                            $matrixClass = '';
                            if ($odata['return'] != '0') {
                                $result = 'Failed to run';
                                $matrixClass = 'text-danger';
                                $dayClass = $rowClass = 'table-danger';
                            } else if (isset($odata['correct']) && $odata['correct']) {
                                $result = 'Correct';
                                $matrixClass = 'text-success';
                            } else if (isset($odata['correct']) && !$odata['correct']) {
                                $result = 'Failed';
                                $matrixClass = 'text-danger';
                                $dayClass = $rowClass = 'table-danger';
                            } else {
                                $result = 'Unknown';
                                $matrixClass = 'text-secondary';
                            }

                            $rowData .= $targetPerson . ' => <span class="' . $matrixClass . '">' . $result . '</span><br>';
                        }
                    } else {
                        $rowData = 'No matrix runs found.';
                    }

                    echo '<tr class="collapse dayinfo ' . $rowClass .'">';
                    echo '<th>Matrix Results</th>';
                    echo '<td>';
                    echo $rowData;
                    echo '</td>';
                    echo '</tr>';
                    $rowspan++;
                } else {
                    $dayClass = 'table-danger';
                }
                $dayRows = ob_get_clean();

                $showRow = true;
                if (isset($_REQUEST['onlybad'])) {
                    $showRow = ($dayClass != 'table-success');
                }

                if ($showRow) {
                    echo '<tbody id="' . $person . '-day'.$day.'">';
                    echo '<tr>';
                    echo '<th rowspan=' . $rowspan. ' style="width: 200px" class="', $dayClass, '">';
                    echo '<a class="daylink" href="./matrix.php?day=', $day, '">Day ', $day, '</a><br>';
                    echo '<button href="#" data-toggle="collapse" data-target="#' . $person . '-day'.$day.' tr.dayinfo" class="btn btn-sm btn-secondary">show/hide</button>';
                    echo '</th>';
                    echo '</tr>';
                    echo $dayRows;
                    echo '</tbody>';
                }
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
