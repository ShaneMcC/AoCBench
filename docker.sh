#!/bin/bash

set -e

if [ "${1}" == "build" ]; then
    shift
    docker build . -t aocbench-container
fi;

docker run -it --rm -p 8036:80 \
  -v /tmp/dockerbench-participants:/aocbench/participants \
  -v /tmp/dockerbench-results:/aocbench/results \
  -v /tmp/dockerbench.config.local.php:/aocbench/config.local.php \
  -v /var/run/docker.sock:/var/run/docker.sock \
  aocbench-container ${@}
