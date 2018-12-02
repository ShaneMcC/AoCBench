<?php

	$participants = [];

	abstract class Participant {
		abstract function getName();
		abstract function getRepo();
		abstract function prepare();
		abstract function run($day);
	}


	$participants[] = new class extends Participant {
		public function getName() { return 'Dataforce'; }
		public function getRepo() { return 'https://github.com/ShaneMcC/aoc-2018'; }

		public function prepare() {
			// Ensures container is built.
			exec('./docker.sh 2>&1');
		}

		public function run($day) {
			$output = [];
			$ret = -1;
			exec('./docker.sh --time ' . $day . ' 2>&1', $output, $ret);

			return $ret === 0 ? $output : null;
		}
	};

