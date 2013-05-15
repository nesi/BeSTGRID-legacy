#!/bin/sh
# createInbox.sh Creates a publicly-writeable collection with a name like:
#                /$irodsZone/home/__INBOX/jane.doe for each of one or all users.                
#                Also creates a publicly-readable collection with a name like:
#                /$irodsZone/home/__PUBLIC/jane.doe for each of those users.
#                Graham Jenkins <graham@vpac.org> Nov. 2009. Rev: 20091224

# Usage check
while getopts hau: Option; do
  case $Option in
    u   ) Users=$OPTARG;;
    a   ) Users=`iadmin lu`;;
    h|\?) Bad=Y;;
  esac
done
shift `expr $OPTIND - 1`
[ \( -n "$Bad" \) -o \( -z "$Users" \) ] &&
  echo "Usage: `basename $0` [-u user |-a]">&2 && exit 2

# Fail function
fail() {
  echo "$@"
  exit 1
}

# Process each user, skipping 'rods', 'anonymous', 'rodsBoot' etc.
for User in $Users ; do
  Username=`iadmin lu $User | awk '{if($1=="user_name:")print $2}'`
  Zonename=`iadmin lu $User | awk '{if($1=="zone_name:")print $2}'`
  [ \( -z "$Username" \) -o \( -z "$Zonename" \) ] && 
    fail "Couldn't ascertain Username and/or Zonename for: $User"
  case "$Username" in
    [a-z]*.[a-z]* )          ;; # Note: some legitimate usernames like
                * ) continue ;; # 'madonna' will have to be processed manually!
  esac
  #
  imkdir -p "/$Zonename/home/__INBOX/$Username"               ||
    fail "Couldn't create directory: /$Zonename/home/__INBOX/$Username"
  ichmod write public "/$Zonename/home/__INBOX/$Username"     &&
    ichmod own $Username "/$Zonename/home/__INBOX/$Username"  &&
    ichmod -r inherit "/$Zonename/home/__INBOX/$Username"     ||
    fail "Couldn't do permissions for: /$Zonename/home/__INBOX/$Username"
  #
  imkdir -p "/$Zonename/home/__PUBLIC/$Username"              ||
    fail "Couldn't create directory: /$Zonename/home/__PUBLIC/$Username"
  ichmod read public "/$Zonename/home/__PUBLIC/$Username"     &&
    ichmod read anonymous "/$Zonename/home/__PUBLIC/$Username" &&
    ichmod own $Username "/$Zonename/home/__PUBLIC/$Username" &&
    ichmod -r inherit "/$Zonename/home/__PUBLIC/$Username"    ||
    fail "Couldn't do permissions for: /$Zonename/home/__PUBLIC/$Username"
  #
  echo "Completed 'inbox'/'public' collection creation for: $Username"
done

# All done
exit 0
