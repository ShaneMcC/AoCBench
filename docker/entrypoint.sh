#!/bin/bash
set -e

cd /aocbench

if [ "${1}" == "shell" ]; then
    echo "Run Mode: Shell"
    exec /bin/bash
fi;

if [ "${1}" == "dobench" ]; then
    shift
    echo "Run Mode: doBench"
    exec /aocbench/run.sh "${@}"
fi;

echo "Run Mode: Webserver"
exec /usr/local/bin/docker-php-entrypoint "apache2-foreground"
