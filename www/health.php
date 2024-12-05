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

            if (isset($pdata['repo']) && !empty($pdata['repo'])) {
                echo '<strong>Repo:</strong> <a href="' . $pdata['repo'] . '"><img height="16px" width="16px" src="github.ico" alt="github"> ' . $pdata['repo'] . '</a>';
                if (isset($pdata['branch']) && !empty($pdata['branch'])) {
                    echo ' (<strong>Branch:</strong> ' . $pdata['branch'] . ')';
                }
                echo '<br>';
            }

            echo '<strong>Valid:</strong> ', ($pdata['valid'] ? '<span class="text-success">true</span>' : '<span class="text-danger">false - ' . $data['valid_info'] . '</span>'), '<br>';
            echo '<strong>Prepared:</strong> ', ($pdata['prepared'] ? '<span class="text-success">true</span>' : '<span class="text-danger">false</span>');
            if (!$pdata['prepared']) {
                echo '<button href="#" data-toggle="collapse" data-target="#' . $person . '-prepare" class="btn btn-sm btn-secondary">show/hide</button>';
                echo '<div id="' . $person . '-prepare" class="collapse"><br><code class="codeview"><pre>';
                echo htmlspecialchars($data['prepare_info']);
                echo '</pre></code><br>';
            }
            echo '<br>';
            echo '<strong>Participant Type:</strong> ', ($pdata['participanttype'] == 2 ? '<span class="text-success">yaml</span>' : '<span class="text-warning">legacy</span>'), '<br>';
            echo '<strong>Config:</strong> ';
            echo '<button href="#" data-toggle="collapse" data-target="#' . $person . '-config" class="btn btn-sm btn-secondary">show/hide</button><br>';
            echo '<div id="' . $person . '-config" class="collapse"><br><code class="codeview"><pre>';
            echo yaml_encode($pdata['config']);
            echo '</pre></code></div><br>';

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
                    echo '<td>', repoCommitAsLink($pdata, $bits[0]), '</td>';
                    echo '</tr>';
                    $rowspan++;

                    echo '<tr class="collapse dayinfo">';
                    echo '<th>Day Version</th>';
                    echo '<td>', repoCommitAsLink($pdata, $bits[1]), '</td>';
                    echo '</tr>';
                    $rowspan++;

                    if (isset($ddata['path'])) {
                        echo '<tr class="collapse dayinfo">';
                        echo '<th>Day Path</th>';
                        echo '<td>', repoFileAsLink($pdata, $ddata['path']), '</td>';
                        echo '</tr>';
                        $rowspan++;
                    }

                    $inputpdata = $pdata;
                    $inputpdata['repo'] = $ddata['input']['gitRepoURL'] ?? $pdata['repo'];

                    $inputVersion = $ddata['input']['version'] ?? '';
                    echo '<tr class="collapse dayinfo ', (empty($inputVersion) ? 'table-danger' : ''), '">';
                    echo '<th>Input Version</th>';
                    echo '<td>', repoCommitAsLink($inputpdata, $inputVersion), '</td>';
                    echo '</tr>';
                    if (empty($inputVersion)) { $dayClass = 'table-danger'; }
                    $rowspan++;

                    if (isset($ddata['input']['path'])) {
                        echo '<tr class="collapse dayinfo">';
                        echo '<th>Input Path</th>';
                        if (empty($ddata['input']['gitRelativePath'] ?? '') || $ddata['input']['path'] == $ddata['input']['gitRelativePath']) {
                            echo '<td>', repoFileAsLink($inputpdata, $ddata['input']['path']), '</td>';
                        } else {
                            echo '<td>';
                            echo str_replace($ddata['input']['gitRelativePath'], '', $ddata['input']['path']);
                            echo repoFileAsLink($inputpdata, $ddata['input']['gitRelativePath']);
                            echo '</td>';
                        }
                        echo '</tr>';
                        $rowspan++;
                    }

                    $answerspdata = $pdata;
                    $answerspdata['repo'] = $ddata['answers']['gitRepoURL'] ?? $pdata['repo'];

                    $answerVersion = $ddata['input']['version'] ?? '';
                    echo '<tr class="collapse dayinfo ', (empty($answerVersion) ? 'table-danger' : ''), '">';
                    echo '<th>Answers Version</th>';
                    echo '<td>', repoCommitAsLink($answerspdata, $answerVersion), '</td>';
                    echo '</tr>';
                    if (empty($answerVersion)) { $dayClass = 'table-danger'; }
                    $rowspan++;

                    if (isset($ddata['answers']['path'])) {
                        echo '<tr class="collapse dayinfo">';
                        echo '<th>Answer Path</th>';
                        if (empty($ddata['answers']['gitRelativePath'] ?? '') || $ddata['answers']['path'] == $ddata['answers']['gitRelativePath']) {
                            echo '<td>', repoFileAsLink($answerspdata, $ddata['answers']['path']), '</td>';
                        } else {
                            echo '<td>';
                            echo str_replace($ddata['answers']['gitRelativePath'], '', $ddata['answers']['path']);
                            echo repoFileAsLink($answerspdata, $ddata['answers']['gitRelativePath']);
                            echo '</td>';
                        }
                        echo '</tr>';
                        $rowspan++;
                    }

                    $answerPart1 = $ddata['answers']['part1'] ?? False;
                    echo '<tr class="collapse dayinfo ', ($answerPart1 ? 'table-success' : 'table-danger'), '">';
                    echo '<th>Has part 1 answer?</th>';
                    echo '<td>', ($answerPart1 ? 'true' : 'false'), '</td>';
                    echo '</tr>';
                    if (!$answerPart1) { $dayClass = 'table-danger'; }
                    $rowspan++;

                    $answerPart2 = $ddata['answers']['part2'] ?? False;
                    echo '<tr class="collapse dayinfo ', ($answerPart2 ? 'table-success' : 'table-danger'), '">';
                    echo '<th>Has part 2 answer?</th>';
                    echo '<td>', ($answerPart2 ? 'true' : 'false'), '</td>';
                    echo '</tr>';
                    if (!$answerPart2) { $dayClass = 'table-danger'; }
                    $rowspan++;

                    if (isset($ddata['runonce'])) {
                        echo '<tr class="collapse dayinfo ', ($ddata['runonce'] ? 'table-success' : 'table-danger'), '">';
                        echo '<th>Run Once</th>';
                        echo '<td>';
                        echo ($ddata['runonce'] ? 'true' : 'false');

                        if (isset($ddata['runonce_info'])) {
                            echo '<button href="#" data-toggle="collapse" data-target="#' . $person . '-' . $day . '-runonce" class="btn btn-sm btn-secondary">show/hide</button>';
                            echo '<div id="' . $person . '-' . $day . '-runonce" class="collapse"><br><code class="codeview"><pre>';
                            echo htmlspecialchars($ddata['runonce_info']);
                            echo '</pre></code></div><br>';
                        }

                        echo '</td>';
                        echo '</tr>';
                        if (!$ddata['runonce']) { $dayClass = 'table-danger'; }
                        $rowspan++;
                    }

                    $runType = $ddata['runtype'] ?? 'unknown';
                    if (empty($runType)) { $runClass = 'table-success'; } // For now.
                    else if ($runType == 'hyperfine') { $runClass = 'table-success'; }
                    else if ($runType == 'time' && $pdata['participantversion'] != 1) {
                        $runClass = 'table-warning';
                    } else {
                        $runClass = 'table-danger';
                    }

                    if ($runClass == 'table-warning' && $dayClass != 'table-danger') { $dayClass = 'table-warning'; }
                    if ($runClass == 'table-danger') { $dayClass = 'table-danger'; }

                    echo '<tr class="collapse dayinfo ', $runClass, '">';
                    echo '<th>Run Type</th>';
                    echo '<td>';
                    echo $runType;

                    if ($runClass == 'table-danger') {
                        $runOutput = implode("\n", $matrix['results'][$person]['days'][$day]['outputs'][$ddata['testInputSource']]['output'] ?? []);

                        if (!empty(trim($runOutput)) || isset($ddata['testInputAnswers'])) {
                            echo '<button href="#" data-toggle="collapse" data-target="#' . $person . '-' . $day . '-runtype" class="btn btn-sm btn-secondary">show/hide</button>';
                            echo '<div id="' . $person . '-' . $day . '-runtype" class="collapse"><br><code class="codeview"><pre>';
                            if (!empty(trim($runOutput))) {
                                echo htmlspecialchars($runOutput);
                            }

                            if (isset($ddata['testInputAnswers'])) {
                                echo "\n\n";
                                echo 'Wanted Answers:', "\n";
                                echo "\t", 'Part 1:', $ddata['testInputAnswers']['part1'], "\n";
                                echo "\t", 'Part 2:', $ddata['testInputAnswers']['part2'], "\n";
                            }

                            echo '</pre></code></div><br>';
                        }
                    }

                    echo '</td>';
                    echo '</tr>';
                    $rowspan++;

                    echo '<tr class="collapse dayinfo">';
                    echo '<th>Run Input Source</th>';
                    echo '<td>', $ddata['testInputSource'], '</td>';
                    echo '</tr>';
                    $rowspan++;

                    $runLength = $ddata['length'] ?? 'unknown';
                    if (!empty($runLength) && $runLength != 'normal' && $dayClass != 'table-danger') { $dayClass = 'table-warning'; }
                    echo '<tr class="collapse dayinfo ', (!empty($runLength) && $runLength != 'normal' ? 'table-warning' : 'table-success'), '">';
                    echo '<th>Run Length</th>';
                    echo '<td>', $runLength, '</td>';
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
                    echo '<th><a class="daylink" href="./matrix.php?day=', $day, '&participant=', $person,'">My Matrix Results</a></th>';
                    echo '<td>';
                    echo $rowData;
                    echo '</td>';
                    echo '</tr>';
                    $rowspan++;

                    $rowClass = '';
                    $rowData = '';
                    foreach (array_keys($matrix['results']) as $matrixperson) {
                        if (isset($matrix['results'][$matrixperson]['days'][$day])) {
                            $odata = $matrix['results'][$matrixperson]['days'][$day]['outputs'][$person] ?? [];
                            if (!empty($odata)) {
                                $matrixClass = '';
                                if ($odata['return'] != '0') {
                                    $result = 'Failed to run';
                                    $matrixClass = 'text-danger';
                                } else if (isset($odata['correct']) && $odata['correct']) {
                                    $result = 'Correct';
                                    $matrixClass = 'text-success';
                                } else if (isset($odata['correct']) && !$odata['correct']) {
                                    $result = 'Failed';
                                    $matrixClass = 'text-danger';
                                } else {
                                    $result = 'Unknown';
                                    $matrixClass = 'text-secondary';
                                }

                                $rowData .= $matrixperson . ' => <span class="' . $matrixClass . '">' . $result . '</span><br>';
                            } else {
                                $rowData .= $matrixperson . ' => No matrix run not found.<br>';
                            }
                        } else {
                            $rowData .= $matrixperson . ' => No matrix runs found.<br>';
                        }
                    }

                    echo '<tr class="collapse dayinfo ' . $rowClass .'">';
                    echo '<th><a class="daylink" href="./matrix.php?day=', $day, '&input=', $person,'">Other People Matrix Results</a></th>';
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
        echo 'No healthcheck data yet.';
    }

    require_once(__DIR__ . '/footer.php');
