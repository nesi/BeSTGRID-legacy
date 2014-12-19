#!/bin/bash

# to be invoked from cron

# get filename: /opt/shibboleth-ds/logs/discovery-2011-08-31.log

DIR_BASE=`dirname $0`

IRODS_HOME=${IRODS_HOME:-/opt/iRODS/iRODS}

# Get last log name.  We know a new log started today.  Last log started
# typically 5 days ago, but this can very if invoked on the 1st of a month.
# So instead get the filename for yesterday and then round down.

YESTERDAY=`date -d 'yesterday' +%Y.%m.%d`
#DAY_YESTERDAY=$( echo $YESTERDAY | cut -c 9-10 )
DAY_YESTERDAY=${YESTERDAY:8:2}
#remove leading zero if present
DAY_YESTERDAY=${DAY_YESTERDAY#0}
DAY_LOG_START=$(( ( ( $DAY_YESTERDAY - 1 ) / 5 ) * 5 + 1 ))
#re-add leading zero if needed to be two digits wide
if [ "$DAY_LOG_START" -lt 10 ] ; then DAY_LOG_START="0$DAY_LOG_START" ; fi
DATE_LOG_START="${YESTERDAY:0:8}$DAY_LOG_START"

if [ -z "$PARSER_OPTIONS" ] ; then PARSER_OPTIONS="" ; fi
# sorry, options only accepted in order --verbose --dry-run
if [ "$1" == "--verbose" ] ; then
   PARSER_OPTIONS="$PARSER_OPTIONS --verbose"
   shift
fi
if [ "$1" == "--dry-run" ] ; then
   PARSER_OPTIONS="$PARSER_OPTIONS --dry-run"
   shift
fi

RODS_LOG_FILE="$IRODS_HOME/server/logs/rodsLog.$DATE_LOG_START"

PARSER_LOG_FILE="$IRODS_HOME/server/logs/parseUserLogins.log"

echo "Invoking $DIR_BASE/parseUserLogins.pl $PARSER_OPTIONS $RODS_LOG_FILE" >> $PARSER_LOG_FILE
$DIR_BASE/parseUserLogins.pl $PARSER_OPTIONS $RODS_LOG_FILE >> $PARSER_LOG_FILE 2>&1

