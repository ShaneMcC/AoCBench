#!/bin/sh

MYDIR=$(dirname "$0")
cd ${MYDIR}

if [ -e ${MYDIR}/.doRun -a ! -e ${MYDIR}/.running ]; then
	PRE=$(stat ${MYDIR}/.doRun)
	${MYDIR}/run.sh
	POST=$(stat ${MYDIR}/.doRun)

	# Only delete file if run was successful, and it
	# hasn't been re-touched since we started.
	if [ $? -eq 0 -a "${PRE}" = "${POST}" ]; then
		rm ${MYDIR}/.doRun
	fi;
fi;
