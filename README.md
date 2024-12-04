# AoC Bench

This application allows for benchmarking [Advent of Code](https://adventofcode.com/) solutions.

There are 2 parts to this, the web frontend (under `www`) and the benchmarker (`bench.php`).

The benchmarker should be run on a cron and is responsible for generating a `results.json` file that is then used by the web frontend to display the data.

There is additionally `inputMatrix.php` which will attempt to run each participant against all the inputs from all participants for each day for comparison.

## Installing

 - Check out the code somewhere.
 - Configure `config.local.php` (See `Configuration` section)
 - Cron `checkRun.sh` every minute (This will look for a `.doRun` file to know when to run, and handle not clobbering existing runs)
 - Either cron `checkScheduledUpdates.php` (and enable appropriate configuration options) or periodically touch a `.doRun` file to actually schedule the runs
 - Make `www` available via a web server.

## Configuration

Most of the configuration should be done in a file called `config.local.php`, all the supported config settings are defined in `config.php`.

The important bit is configuring participants.

An example participant:

```php
$participants[] = new V2Participant('Dataforce', 'https://github.com/ShaneMcC/aoc-2023');
```

The important bits are:
 - The first variable is the participant name (Spaces will be removed and used as the folder name for the repo on disk)
 - The second variable is the participant repo (This will be automatically checked out)
 - Legacy (non-V2) participants were configured using method overrides which are no longer the expected way to define participants and no longer documented (You can look back at [An older version of README.md](https://github.com/ShaneMcC/AoCBench/blob/39b4e2adb78550dfc6f4b3c98d6f9c28f7bb4f73/README.md) for configuration information for legacy participants)
 - Modern (v2) participants, it is expected that the repo must then contain a `.aocbench.yaml` file for benchmarking to work. (This file is documented later)

When benchmarking, the following happens per-participant:
 - The participant repo is cloned if it does not exist, or updated using `git fetch` and `git reset` commands
 - The repo is then prepared - this step includes building any required docker containers or pulling any required images
 - We will then check to see if a participant has a given day by checking that the `daypath` yaml setting points at a valid file or directory
 - If `$normaliseInput` is set, then the file specified by `inputfile` yaml setting will be overwritten with the input for the day.
   - This will either be taken from `./inputs/<day>.txt` or fallback to the file referenced by the first-defined participant who has completed the day
     - The answers for this input will be taken from the `answerfile` yaml setting
       - this is a 2-line text file with answers for part1 on the first and part2 on the second
 - The participant's `runonce` code will then be run a single time (for compiling a day) usually in a dedicated conatiner
 - The particpant's `cmd` will be run multiple times for benchmarking.
   - The `prerun` command if specified will be run (at least) once per new-container per day
   - This will first be once to check if the output is correct.
   - If the output is correct, it will then be run using [hyperfine](https://github.com/sharkdp/hyperfine) to get accurate benchmarks (unless disabled in the yaml file)
   - If hyperfine fails to run for some reason, then we will fallback to running multiple times using `time`
 - Containers may run multiple commands (In this case, `runonce` for a given day will always happen at least once before the `cmd` for that day)
 - After all the days are run the repo will be cleaned up using `git reset --hard`

For the input matrix, the process is mostly the same, however `cmd` will only be run once-per-input to test against.

Like with benchmarking, it's possible that a container may be re-used for multiple days/inputs, or may just be used for a single execution. `runonce`, `prerun` and `cmd` will still be run in the expected orders as required.

## Repo Requirements
All repos need a `.aocbench.yaml` file in them to tell us how to run.

Each individual day should be runable standalone, reading input from disk from the location specified in the `inputfile` setting.

A full and complete example with all possible options is here:

```yaml
### .aocbench.yaml version
### Default: none - This must be specified as 1.
version: 1

### Repo Author
### Default: none
author: "Dataforce"

### Language used.
### Default: none
language: PHP

### Optional version prefix, changing this will invalidate all previous runs without needing to make
### other code changes.
### Default: none
# versionprefix: "1"

### Path to Dockerfile to build image
### The image will be rebuilt if this file changes.
### This should just produce an environment that can compile/run the code
### (and can be reused every day), it should not compile the code itself
### Default: none - Must be specified if image is not.
dockerfile: "docker/Dockerfile"

### Or image (this takes priority, same idea as above)
### Default: none - Must be specified if dockerfile is not.
# image: "php:8.3-cli"

### Location where repo checkout should be mounted
### Default: /code
code: "/code"

### [%] Additional directories that need persisting across container runs.
### Separate container instances are created each time we run commands.
### Anything not included here will be lost across runs.
### The code directory is automatically persisted.
### Default: none
persistence:
 - /tmp

### [%] What directory to run commands from.
### Default: the value of the `code` setting
workdir: "/code/%day%"

### [%] Before benchmarking, command to run once to build a given day if needed.
### This runs once and may be in a separate container instance than the regular
### code runs.
### This will always be run at least once before the cmd for the day is run.
### Default: none
runonce: "/code/docker/build.sh %day%"

### [%] When running image, command to run before the benchmarking occurs.
### This runs once in the same container instance as the regular code runs and gets run (once) for every time that container
### is used for a given day.
### This should not be used for slow/time-consuming jobs such as compiling, and is mostly tweaking the environment if needed
### Default: none
# prerun: "/code/docker/enableJit.sh"

### [%] When running image, command to run a given day, this is passed to `hyperfine` or `time` to actually benchmark the day
### Default: none - This must be specified
cmd: "php /code/%day%/run.php --file %input%"

### When running docker images, don't use `-it` on the `docker run` command line
### Default: False
# notty: True

### Enable or disable hyperfine for measurement (Default: enabled)
### Default: True
# hyperfine: False

### Enable or disable using a shell for hyperfine runs (Default: disabled)
### Default: True
# hyperfineshell: True

### [%] Environment vars to set on container
### These will exist on every type of container run
### Default: none
environment:
 - TIMED=1

### [%] Path to per-day code. (Directory or File)
### This will be checked to decide if the day exists and needs running, and to check the code version
### If this is a file only changes to that file will trigger a re-run, if it is a directory then any changes to any files within the
### directory will trigger a re-run.
### Default: %day%
daypath: "%day%"

### Path to any additional common files that should count as changing all days.
### Behaves similar to daypath, any entry in this list can be a file or directory.
### (the .aocbench.yaml file is included in this list by default)
### This can not include any paths that are part of a submodule (they will be ignored)
### Default: none
common:
 - common

### [%] Path to per-day input file.
### This is the file that will be overwritten with other test inputs, or used to feed input to other participants
### This is also the path that will be used to generate %inputpath%
### This should be relative to the `code` dir above, not `workdir`
### This file may be within a submodule, but if it is, the answerfile must also be part of the same submodule.
### Default: `%day%/input.txt`
inputfile: "%day%/input.txt"

### [%] Path to per-day answer file used to validate other participant answers
### This should be relative to the `code` dir above, not `workdir`
### This file may be within a submodule, but if it is, the inputfile must also be part of the same submodule.
### Default: `%day%/answers.txt`
answerfile: "%day%/answers.txt"
```

Options marked with a `[%]` will allow variable replacements:

 - `%day%` - The day to run, without zero padding.
 - `%zeroday%` or `%dayzero%` - The day to run, with zero padding.
 - `%year%` - The year that is being run (for monorepos) (This is the `$leaderboardYear` config setting)
 - `%input%` - The input file path if needed (This will be an absolute path) (If the application automatically reads from the inputfile location rather than taking a parameter, then this is unrequired)

## Updating

`git pull; composer install` or equivalent.

## Comments, Questions, Bugs, Feature Requests etc.

Bugs and Feature Requests should be raised on the [issue tracker on github](https://github.com/ShaneMcC/aocbench/issues), and I'm happy to receive code pull requests via github (Though I do not guarantee that all will be merged.)

I can be found idling on various different IRC Networks, but the best way to get in touch would be to message "Dataforce" on Quakenet, or drop me a mail (email address is in my [github profile](https://github.com/ShaneMcC)). I can also probably be found on the unofficial AoC Discord (Dataforce#4726) and IRC Channels as Dataforce and on the subreddit.

## Screenshots

### Benchmark Results
![Benchmark Results](/screenshots/AoCBench.png?raw=true "Benchmark Results")

### Podium Mode
![Podium Mode](/screenshots/PodiumMode.png?raw=true "Podium Mode")

### Output Comparison Matrix
![Output Matrix](/screenshots/AoCBenchMatrix.png?raw=true "Output Comparison Matrix")
