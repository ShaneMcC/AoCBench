#!/bin/bash

MYDIR=$(dirname "$0")
SLUGNAME=$(basename "${MYDIR}")

cd ${MYDIR}

(
	flock -x -n 200

	if [ ${?} -eq 0 ]; then
		touch ${MYDIR}/.running
		rm /tmp/${SLUGNAME}.log

		${MYDIR}/bench.php | tee -a /tmp/${SLUGNAME}.log
		${MYDIR}/inputMatrix.php --no-update | tee -a /tmp/${SLUGNAME}.log

		rm ${MYDIR}/.running
		exit 0;
	fi;
	exit 42;

) 200>/tmp/.aocbench-runlock

exit ${?}
