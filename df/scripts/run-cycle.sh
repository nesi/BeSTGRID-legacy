#!/bin/bash
#
# Wrapper script to run a command. The script includes locking so that
# only one instance of the command would be run.

if [ $# -lt 1 ] ; then
	echo "Invalid arguments: $*" >&2
	echo "Usage: $0 command-to-run with arguments" >&2
	exit 1
fi

# determine the mount point for the file system
HOSTNAME=`/bin/hostname -s`
PROG="$0"
TOUCH="/bin/touch"

# local configuration
NOTIFY_EMAIL="datafabric@nesi.org.nz"
# log file - named /var/log/GPFS_Daily.YYYY-MM-DD_EVENT_NAME_HH-MM-SS.out
RUN_NAME="`date +'%Y-%m-%d_%H-%M-%S'`"
LOGFILE="/home/rods/logs/replicator.$RUN_NAME.out"

# lock file
LOCKDIR="/opt/iRODS/lockdir/quiescence.lock"

# exit codes and text for them
ENO_SUCCESS=0; ETXT[0]="ENO_SUCCESS"
ENO_GENERAL=1; ETXT[1]="ENO_GENERAL"
ENO_LOCKFAIL=2; ETXT[2]="ENO_LOCKFAIL"
ENO_RECVSIG=3; ETXT[3]="ENO_RECVSIG"

#
# Attempt to get a lock
#
trap 'ECODE=$?; echo "`date` [${PROG}] Exit: ${ETXT[ECODE]}($ECODE)" >&2' 0
echo -n "`date` [${PROG}] Locking: ... " >&2

if mkdir "${LOCKDIR}" &>/dev/null; then

       	# lock succeeded, install signal handlers
       	trap 'ECODE=$?;
       	echo "`date` [${PROG}] Removing lock. Exit: ${ETXT[ECODE]}($ECODE)" >&2
		rm -rf "${LOCKDIR}"' 0
       	# the following handler will exit the script on receiving these signals
       	# the trap on "0" (EXIT) from above will be triggered by this scripts
       	# "exit" command!
       	trap 'echo "`date` [${PROG}] Killed by a signal." >&2
		exit ${ENO_RECVSIG}' 1 2 3 15
       	echo "success, installed signal handlers"
else
       	# exit, we're locked!
       	echo "lock failed other operation running" >&2

       	# determine whether to send an alert - if the lock-file is too new
	# must be in /tmp (not in LOCKDIR) - we'd otherwise update LOCKDIR's
	# timestamp
	TESTFILE="/tmp/$RUN_NAME.$HOSTNAME.$$"
	$TOUCH -d "1 day ago" "$TESTFILE"
	if [ -e "$TESTFILE" -a "$TESTFILE" -ot "$LOCKDIR" ] ; then
		echo "`date` Not sending any alert - lock file not that old"
	else
		 
		# send an email notification
		{
		    cat <<-EOF
			Error invoking
			$0 $*
			on $HOSTNAME
			
			Lock failed: other operation running
			
			Existing lock details:
EOF
		    ls -ld $LOCKDIR
		    ls -l $LOCKDIR
		} | mail -s "Lock failed" $NOTIFY_EMAIL
	fi
	rm "$TESTFILE"
	exit ${ENO_LOCKFAIL}
fi

# note what we are doing and where we are doing it
/bin/touch $LOCKDIR/${RUN_NAME}.${HOSTNAME}

# run the commnad
echo "`date` Logging commnad output into: $LOGFILE"
echo "`date` Running the commnad: $@"
$@ > $LOGFILE 2>&1

# DEBUG: wait 5 minutes before exiting/releasing the lock
#sleep 300

exit 0;

