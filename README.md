# AoC Bench

This application allows for benchmarking [Advent of Code](https://adventofcode.com/) solutions.

There are 2 parts to this, the web frontend (under `web`) and the benchmarker (`bench.php`).

The benchmarker should be run on a cron and is responsible for generating a results.json file that is then used by the web frontend to display the data.

## Installing

 - Check out the code somewhere.
 - Configure `config.local.php` (See `Configuration` section)
 - Cron `bench.php`
 - Make `www/` available via a web server.

## Configuration

Most of the configuration should be done in a file called `config.local.php`, all the supported config settings are defined in `config.php`.

The important bit is configuring participants.

An example participant:

```php
	$participants[] = new class extends Participant {
		public function getName() { return 'Dataforce'; }
		public function getRepo() { return 'https://github.com/ShaneMcC/aoc-2018'; }
		public function getInputFilename($day) { return $day . '/input.txt'; }

		public function getVersion($day) {
			if (file_exists($day)) {
				exec('git rev-list -1 HEAD -- "' . $day . '" 2>&1', $output);
				return $output[0];
			}

			return NULL;
		}

		public function run($day) { return $this->doRun('./docker.sh --time ' . $day . ' 2>&1'); }
	};
```

The important bits are:
 - The participant name (Must not contain any spaces)
 - The participant repo (This will be automatically checked out)
 - The `getVersion($day)` function should return the most recent commit that changed a given day.
   - This usually checks the version of the directory or a specific per-day file.
 - The input for each day needs to be a text file on disk (If all participants are to be benchmarked against the same input)
 - It is assumed (Though not explicitly required) that each participant's code will run in Docker.
   - In most cases, the Docker image is just a basic runtime environment for the language of choice, with the code mounted as a bind-mount from the on-disk repo.
   - At the very least `getInputFilename($day)` should return a (relative to the repo root) path that will ultimately be mounted inside the container.

When benchmarking, the following happens per-participant:
 - The `updateRepo($dir)` method of the Participant is called.
   - The default implementation should suffice for most people, it checks out the latest copy of the repo or calls `git update`
 - The `prepare()` method of the Participant is called.
   - The default implementation will run docker.sh or run.sh if found.
   - This should build the required docker container for the Participant for example.
 - `hasDay($day)` will be checked for the participant.
   - The default implementation checks if getVersion($day) returns non-null
 - If `$normaliseInput` is set, then the file specified by `getInputFilename($day)` will be overwritten with the input for the day.
   - This will either be taken from `./inputs/<day>.txt` or fallback to the file referenced by `getInputFilename($day)` on the first-defined participant.
 - `run($day)` will be called multiple times to run the day the required number of times
 - `extractTime($output)` will be called on the output from `run($day)` to extract the time value.
   - The default implementation assumes that the 3rd-from-last line of the output will contain `real 0m0.000s` or so as per the `time` function in `bash`.
   - If the output time is not in `real` format, it should be converted to `0m0.000s` format for the frontend to understand.
 - After all the days are run, `cleanup()` will be called.
   - The default implementation does nothing.


## Updating

`git pull` or equivalent.

## Comments, Questions, Bugs, Feature Requests etc.

Bugs and Feature Requests should be raised on the [issue tracker on github](https://github.com/ShaneMcC/aocbench/issues), and I'm happy to receive code pull requests via github (Though I do not guarantee that all will be merged.)

I can be found idling on various different IRC Networks, but the best way to get in touch would be to message "Dataforce" on Quakenet, or drop me a mail (email address is in my [github profile](https://github.com/ShaneMcC)). I can also probably be found on the unofficial AoC Discord and IRC Channels as Dataforce and on the subreddit.

## Screenshots

### Main Index
![Main Index](/AoCBench.png?raw=true "Main Table View")
