#!/bin/sh

MYDIR=$(dirname "$0")
cd ${MYDIR}

if [ -e ${MYDIR}/.doRun -a ! -e ${MYDIR}/.running ]; then
	rm ${MYDIR}/.doRun
	${MYDIR}/run.sh
fi;
