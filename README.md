# AoC Bench

This application allows for benchmarking [Advent of Code](https://adventofcode.com/) solutions.

There are 2 parts to this, the web frontend (under `www`) and the benchmarker (`bench.php`).

The benchmarker should be run on a cron and is responsible for generating a `results.json` file that is then used by the web frontend to display the data.

There is additionally `inputMatrix.php` which will attempt to run each participant against all the inputs from all participants for each day for comparison.

## Installing

 - Check out the code somewhere.
 - Configure `config.local.php` (See `Configuration` section)
 - Cron `bench.php`
 - Optionally Cron `inputMatrix.php`
 - Make `www` available via a web server.

## Configuration

Most of the configuration should be done in a file called `config.local.php`, all the supported config settings are defined in `config.php`.

The important bit is configuring participants.

An example participant:

```php
	$participants[] = new class extends Participant {
      public function getName() { return 'Dataforce'; }
      public function getRepo() { return 'https://github.com/ShaneMcC/aoc-2018'; }
      public function getDayFilename($day) { return $day; }
      public function getInputFilename($day) { return $this->getDayFilename($day) . '/input.txt'; }
      public function getRunCommand($day) { return './docker.sh --time ' . $day; }
	};
```

The important bits are:
 - The participant name (Spaces will be removed and used as the folder name for the repo on disk)
 - The participant repo (This will be automatically checked out)
 - The `getDayFilename($day)` function should return the path to the directory or file containing the day.
 - The `getInputFilename($day)` function should return the path to the input file for the day.
   - Shown here is the default implementation of this, which if sufficient, can be ignored.
 - The input for each day needs to be a text file on disk (If all participants are to be benchmarked against the same input)
 - It is assumed (Though not explicitly required) that each participant's code will run in Docker.
   - In most cases, the Docker image is just a basic runtime environment for the language of choice, with the code mounted as a bind-mount from the on-disk repo.
   - At the very least `getInputFilename($day)` should return a (relative to the repo root) path that will ultimately be mounted inside the container.
 - `getInputAnswer($day, $part)` can also be defined, by default this will look for answers.txt within `getDayFilename($day)` with part 1 on line 1 and part 2 on line 2.

When benchmarking, the following happens per-participant:
 - The `updateRepo($dir)` method of the Participant is called.
   - The default implementation should suffice for most people, it checks out the latest copy of the repo or calls `git update`
 - The `prepare()` method of the Participant is called.
   - The default implementation will run docker.sh or run.sh if found.
   - This should build the required docker container for the Participant for example.
 - `hasDay($day)` will be checked for the participant.
   - The default implementation checks the git version of `getDayFilename($day)` returns non-null
 - If `$normaliseInput` is set, then the file specified by `getInputFilename($day)` will be overwritten with the input for the day.
   - This will either be taken from `./inputs/<day>.txt` or fallback to the file referenced by `getInputFilename($day)` on the first-defined participant.
   - The user should implement `getInputAnswer($day, $part)` as either a global function `config.local.php` or within the first-defined participant.
     - This should return a non-`NULL` string to look for in the output to allow for validation.
 - `run($day)` will be called multiple times to run the day the required number of times
   - By default this will call `getRunCommand($day)` and then run that and return the output.
   - If a custom `run($day)` implementation is required, this should return `[$returnCode, $outputArray]`. A non-0 `$returnCode` is considered a fail and `$outputArray` will be displayed for debugging.
 - `extractTime($outputArray)` will be called on the result from `run($day)` to extract the time value.
   - The default implementation assumes that the 3rd-from-last line of the output will contain `real 0m0.000s` or so as per the `time` function in `bash`.
   - If the output time is not in `real` format, it should be converted to `0m0.000s` format for the frontend to understand.
 - After all the days are run, `cleanup()` will be called.
   - The default implementation runs `cleanup.sh` if it exists, and then `git reset --hard origin`


## Repo Requirements
A repo that conforms to the following behaviour should just work "out of the box" with a `Participant` that consists of just a `getName()` and `getRepo()` configuration.

  - Days are stored in directories named `1`, `2`, ... `24`, `25`
  - Input file for each day is stored as `input.txt` within the appropriate directory (eg `1/input.txt`)
  - Expected answers for each day optionally within `answers.txt` within the appropriate directory (eg `1/answers.txt`)
  - A script in the root repo called `run.sh` or `docker.sh`
    - This will be run with the checked out repo as `pwd`
    - When run without any arguments, this should build any required containers. It is expected that this container can be built infrequently and reused.
    - When run with a day argument (eg `./run.sh 1`) this will compile (if required) and then run that day using bash `time`.
      - Any compilation output should be stored between runs of the same day for speed/efficiency between test runs.
        - Compilation should happen within the docker container.
      - `time` output should be the very last 3 lines in the result of the `./run.sh 1` command.
        - `time` should not include any time spent compiling, just the time to run the script or compiled binary
      - The code should always use `${pwd}/<day>/input.txt` as the input source.
        - The input file may be overwritten before running to ensure the same input is tested for each participant in case some yield a faster solve time.
        - The input file should be mounted within the container (either on it's own or the whole `pwd` or so)
      - The container should exit as soon as the single-run has finished.
  - A `cleanup.sh` script can optionally exist in the repo to run any post-test cleanup required beyond `git reset --hard origin`


## Updating

`git pull` or equivalent.

## Comments, Questions, Bugs, Feature Requests etc.

Bugs and Feature Requests should be raised on the [issue tracker on github](https://github.com/ShaneMcC/aocbench/issues), and I'm happy to receive code pull requests via github (Though I do not guarantee that all will be merged.)

I can be found idling on various different IRC Networks, but the best way to get in touch would be to message "Dataforce" on Quakenet, or drop me a mail (email address is in my [github profile](https://github.com/ShaneMcC)). I can also probably be found on the unofficial AoC Discord and IRC Channels as Dataforce and on the subreddit.

## Screenshots

### Benchmark Results
![Benchmark Results](/AoCBench.png?raw=true "Benchmark Results")

### Output Comparison Matrix
![Output Matrix](AoCBenchMatrix.png?raw=true "Output Comparison Matrix")
