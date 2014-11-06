#!/bin/bash
# replicator.sh  Replicator script intended for invocation (as the iRODS user)
#                from /etc/init.d/replicator
#                Graham Jenkins <graham@vpac.org> Jan. 2010. Rev: 20101117
#                Vladimir Mencl <vladimir.mencl@canterbury.ac.nz> - 2010-2014
#                  2014-07-17: VM: remove timout wrapper on replication tasks -
#                    no longer needed as BeSTGRID has no resources recalling
#                    files from tapes (and getting stuck in the process).
#                    Also remove escaped quotes pre-inserted in the awk code.
#                    And add -r (raw) flag to read and -E (no backslash escape
#                    interpretation) to echo - both to support backslashes in
#                    filenames.
#                  2014-09-24: VM: remove the inherited sed code that was
#                    escaping every $ sign in file names with a double
#                    backslash:
#                        sed 's/\$/\\\\$/g' 
#                    No longer needed since we are doing proper qutation when
#                    using the filenames.
#                  2014-11-06: VM: apply the same fixes (remove sed escaping of
#                    "$" and "\" and eval execution) also from the code handling
#                    dirty replicas.
#                  2014-11-07: VM: add support for mapping path components to
#                    individual resources.  In the replication loop, check if a
#                    file already has a replica on the mapped resource.  If not,
#                    replicate there, otherwise revert to the default resource.
 

# support mappings of directories to resources
REPLMAP_count=0
# for each mapping (indexed from 0)
# REPLMAP_<n>_DIR = /BeSTGRID/home/example
# REPLMAP_<n>_RES = whatever.resource

function addMapping {
# accepts mapping as DIR:RES
  DIR="$( echo "$1" | cut -d : -f 1 )"
  RES="$( echo "$1" | cut -d : -f 2- )"
  eval REPLMAP_${REPLMAP_count}_DIR="$DIR"
  eval REPLMAP_${REPLMAP_count}_RES="$RES"
  REPLMAP_count=$(( $REPLMAP_count + 1 ))
}

function listMappings {
  REPLMAP_idx=0
  while [ $REPLMAP_idx -lt $REPLMAP_count ] ; do
    REPLMAP_DIR_VAR=REPLMAP_${REPLMAP_idx}_DIR
    REPLMAP_RES_VAR=REPLMAP_${REPLMAP_idx}_RES
    echo "Mapping path ${!REPLMAP_DIR_VAR} to resource ${!REPLMAP_RES_VAR}"
    REPLMAP_idx=$(( $REPLMAP_idx + 1 ))
  done
}

function findMapping {
# accepts path name as parameter
# returns mapped resource if found, blank otherwise
  REPLMAP_idx=0
  while [ $REPLMAP_idx -lt $REPLMAP_count ] ; do
    REPLMAP_DIR_VAR=REPLMAP_${REPLMAP_idx}_DIR
    REPLMAP_RES_VAR=REPLMAP_${REPLMAP_idx}_RES
    if echo -E "$1" | grep -q ^${!REPLMAP_DIR_VAR} ; then
        echo "${!REPLMAP_RES_VAR}"
        break
    fi
    REPLMAP_idx=$(( $REPLMAP_idx + 1 ))
  done
}
  

# Batch size, path, usage check
BATCH=8
[ -z "$IRODS_HOME" ] && IRODS_HOME=/opt/iRODS/iRODS
PATH=/bin:/usr/bin:$IRODS_HOME/clients/icommands/bin:/usr/local/bin
Zone=`iquest "%s" "select ZONE_NAME" 2>/dev/null | head -1`
while getopts nshlvM: Option; do
  case $Option in
    n   ) ListOnly=Y;;
    l   ) Loop=Y;;
    v   ) Verbose=Y;;
    M   ) addMapping "$OPTARG";;
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

[ -n "$Verbose"  ]  && listMappings

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
      and   DATA_SIZE        <> '0'" 2>/dev/null |
        grep -v '^CAT_NO_ROWS_FOUND: Nothing was found matching your query$' | 
    while read -r Object; do
      ils -l "$Object" | grep " & " >/dev/null 2>&1 || continue
      [ -n "$ListOnly" ] && echo DIRTY: irepl -MUT "\"$Object\"" && continue
      DirtyTotal=`ils -l "$Object" | grep -v " & " | wc -l`
      for Count in `seq 1 $DirtyTotal`; do
        if [ -n "$ListOnly" -o -n "$Verbose" ] ; then
            echo irepl -MUT "\"$Object\""
            if [ -n "$ListOnly" ] ; then continue; fi
        fi
        irepl -MUT "$Object"
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
      if ($1!="C-") { # and skip only stale replicas
        amperpos=index($0," & ")
        if(amperpos>0) print Dir"/"substr($0,amperpos+3)
      }
    }
  }' | uniq -u | # shuf |
  
  # Feed the randomly-ordered list records into a parallel-job launch-pipe
  while read -r Line || { echo "Replication pass almost complete - waiting for pending jobs" >&2 ; wait ; false ; } ; do
    # we need to first determine whether this file maps to a dedicated resource - and if it isn't there yet, map it there.
    MAPPED_Resource=$( findMapping "$Line" )
    if [ -n "$MAPPED_Resource" ] ; then
        [ -n "$Verbose"  ] && echo -E "File $Line is being mapped to $MAPPED_Resource"
        # Hack: ils show resource names trimmed to 20 characters
        MAPPED_Resource_ils="$( echo "$MAPPED_Resource" | cut -c 1-20 )"
        if ils -l "$Line" | grep -q " $MAPPED_Resource_ils " ; then
           # We already have a replica on the mapped resource, so leave it to default replication
           [ -n "$Verbose"  ] && echo -E "File \"$Line\" already has a replica on $MAPPED_Resource, defaulting to $Resource"
           MAPPED_Resource=""
        fi
    fi
    # in the following use MAPPED_Resource if set, otherwise Resource
    # in bash:  ${MAPPED_Resource:-$Resource}
        
    [ -n "$ListOnly"  ] &&echo -E REPLIC: irepl -MBT -R ${MAPPED_Resource:-$Resource} "'$Line'"&&continue
    [ -n "$Verbose"  ]  &&echo -E REPLIC: irepl -MBT -R ${MAPPED_Resource:-$Resource} "'$Line'"
    ( irepl -MBT -R ${MAPPED_Resource:-$Resource} "$Line" ||
      logger -i -t `basename $0` "Failed: \"$Line\""           ) &
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
      #sleep 600 # 10 minutes
      sleep 1800 # 30 minutes
      #sleep 10800 # 3 hours
  else
      unset NextIter ; 
  fi
done
