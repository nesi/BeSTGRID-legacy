#!/bin/ksh
# replicator.sh  Replicator script intended for invocation (as the iRODS user)
#                from /etc/init.d/replicator
#                Graham Jenkins <graham@vpac.org> Jan. 2010. Rev: 20101117

# Batch size, path, usage check
BATCH=16
[ -z "$IRODS_HOME" ] && IRODS_HOME=/opt/iRODS/iRODS
PATH=/bin:/usr/bin:$IRODS_HOME/clients/icommands/bin:/usr/local/bin
Zone=`iquest "%s" "select ZONE_NAME" 2>/dev/null | head -1`
while getopts nshlv Option; do
  case $Option in
    n   ) ListOnly=Y;;
    l   ) Loop=Y;;
    v   ) Verbose=Y;;
    s   ) Skip=Y ;;
    h|\?) Bad="Y"   ;;
  esac
done
shift `expr $OPTIND - 1`
[ \( -n "$Bad" \) -o \( -z "$2" \) ] &&
  ( echo "  Usage: `basename $0` [-s] [-n] Resource Collection [Collection2 ..]"
    echo "   e.g.: `basename $0` ARCS-REPLISET /$Zone/home /$Zone/projects/IMOS"
    echo "Options: -s .. skips cleaning of dirty replicas"
    echo "         -n .. shows what would be done, then exits"
    echo "         -l .. keep running in a loop (otherwise exit after one pass)"
    echo "         -v .. verbose (print out files being replicated)"
  ) >&2 && exit 2

# Extract resource-name, loop forever
Resource="$1"; shift
NextIter="Y"
while [ -n "$NextIter" ] ; do

  # Clean dirty replicas
  if [ -z "$Skip" ]; then
    logger -i -t `basename $0` "Cleaning Dirty Replicas"
    iquest --no-page "%s/%s" "select COLL_NAME,DATA_NAME
      where COLL_NAME not like '/$Zone/trash/%'
      and   DATA_REPL_STATUS <> '1'
      and   DATA_SIZE        <> '0'" 2>/dev/null | sed 's/\$/\\\\$/g' |
    while read Object; do
      eval ils -l "\"$Object\"" | grep " & " >/dev/null 2>&1 || continue
      [ -n "$ListOnly" ] && echo DIRTY: irepl -MUT "\"$Object\"" && continue
      DirtyTotal=`eval ils -l "\"$Object\"" | grep -v " & " | wc -l`
      for Count in `seq 1 $DirtyTotal`; do
        if [ -n "$ListOnly" -o -n "$Verbose" ] ; then
            echo irepl -MUT "\"$Object\""
            if [ -n "$ListOnly" ] ; then continue; fi
        fi
        eval irepl -MUT "\"$Object\""
      done
    done
  fi

  # List all files with full collection path, print those that appear only once
  logger -i -t `basename $0` "Replicating to $Resource .. $@"
  J=0
  echo "ils -lr $@"
  ils -lr "$@" 2>/dev/null | awk '{
    if ($1~"^/") {    # Extract collection names from records starting in "/".
      Dir=substr($0,1,length-1)
    }
    else {            # Extract file names from non-collection records,
      if ($1!="C-") { # and skip those whose size is non-positive ..
        amperpos=index($0," & ")
        if(amperpos>0) if($4>0) print "\""Dir"/"substr($0,amperpos+3)"\""
      }
    }
  }' | uniq -u | sed 's/\$/\\\\$/g' | # shuf |
  
  # Feed the randomly-ordered list records into a parallel-job launch-pipe
  while read Line || { echo "Replication pass almost complete - waiting for pending jobs" >&2 ; wait ; false ; } ; do
    [ -n "$ListOnly"  ] &&echo REPLIC: irepl -MBT -R $Resource "$Line"&&continue
    [ -n "$Verbose"  ]  &&echo REPLIC: irepl -MBT -R $Resource "$Line"
    ( eval timeout 7200 irepl -MBT -R $Resource "$Line" ||
      logger -i -t `basename $0` "Failed: $Line"           ) &
    while [ `jobs | wc -l` -ge $BATCH ] ; do
      sleep 1
    done
  done
  wait

  # All done; either exit, or release the array and sleep for 2 hours
  logger -i -t `basename $0` "Replication pass completed!"
  echo "Replication pass completed!" >&2
  [ -n "$ListOnly" ] && exit 0

  if [ -n "$Loop" ] ; then 
      sleep 10800
  else
      unset NextIter ; 
  fi
done
