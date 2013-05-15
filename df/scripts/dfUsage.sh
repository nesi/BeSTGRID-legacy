#!/bin/bash

ZONE_NAME="BeSTGRID"
HUMAN_READABLE="y"
INCLUDE_TRASH="y"
SORT_FLAGS="-r"


function PrintHumanReadable {
NUM=$1
UNITS=" kMGTPE"
INCR="1024"
STEPS=0
MAX_STEPS=6
TRESH=$(( $INCR * 10 ))

while [ $NUM -ge $TRESH -a $STEPS -lt $MAX_STEPS ] ; do
  NUM=$(( $NUM / $INCR ))
  STEPS=$(( $STEPS + 1 ))
done

if [ $STEPS -eq 0 ] ; then
  echo -n $NUM
else
  UNIT="${UNITS:$STEPS:1}B"
  printf "%d%s" $NUM $UNIT
fi
return 0
}



function PrintUsageForUser {
I_USER_NAME="$1"
I_USER_HOME="/$ZONE_NAME/home/$I_USER_NAME"
I_USER_TRASH="/$ZONE_NAME/trash/home/$I_USER_NAME"

I_USER_HOME_USAGE1="$( iquest "%s" "select sum(DATA_SIZE) where COLL_NAME = '$I_USER_HOME'" )"
I_USER_HOME_USAGE2="$( iquest "%s" "select sum(DATA_SIZE) where COLL_NAME like '$I_USER_HOME/%'" )"

I_USER_USAGE=$(( ${I_USER_HOME_USAGE1:-0} + ${I_USER_HOME_USAGE2:-0} ))

# alternative if not giving format specifier to iquest
# | grep ^DATA_SIZE | cut -d ' ' -f 3 ) 

if [ -n "$INCLUDE_TRASH" ] ; then
# TODO check usage for trash as well - include into I_USER_USAGE
  :
fi

if [ -n "$HUMAN_READABLE" ] ; then
  I_USER_USAGE=$( PrintHumanReadable $I_USER_USAGE ) 
fi

echo $I_USER_USAGE $I_USER_NAME 

return 0
}


function ReportUsage {
  # get user list
  iquest "%s" "select USER_NAME where USER_ZONE = 'BeSTGRID'" | sort |
  # and feed it throgh getting usage for each user
  while read USER ; do 
    HUMAN_READABLE=""
    PrintUsageForUser $USER
  done | 
  # sort numerically 
  sort -n $SORT_FLAGS |
  while read USAGE USER ; do
    echo $( PrintHumanReadable $USAGE ) $USER
  done
  return 0
}

function Help {
  echo "Usage: $0 [--human-readable|--no-human-readable] [--reverse-sort|--no-reverse-sort]"
  exit $1
}
  
while [ $# -gt 0 ] ; do
  case $1 in
-h)
	Help 0
	;;
--help)
	Help 0
	;;
--human-readable)
	HUMAN_READABLE=y
	;;
--no-human-readable)
	HUMAN_READABLE=""
	;;
--reverse-sort)
	SORT_FLAGS="-r"
	;;
--no-reverse-sort)
	SORT_FLAGS=""
	;;
*)
	echo "Invalid argument $1"
	Help 2
	;;
esac
shift
done

# do the main job now
ReportUsage


