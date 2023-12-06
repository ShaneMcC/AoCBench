#!/bin/bash

MYDIR=$(dirname "$0")
cd ${MYDIR}

SLUGNAME=$(basename "${PWD}")

(
	flock -x -n 200

	if [ ${?} -eq 0 ]; then
		touch ${MYDIR}/.running

		${MYDIR}/bench.php "${@}"
		${MYDIR}/inputMatrix.php --no-update "${@}"

		rm ${MYDIR}/.running
		exit 0;
	fi;
	exit 42;

) 200>/tmp/.aocbench-runlock

exit ${?}
