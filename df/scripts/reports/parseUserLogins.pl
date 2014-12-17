#!/usr/bin/perl -w

use URI::Escape;
use DateTime;
use DBI;
use strict; # strongly recommended for DBI


$main::verbose=0;
$main::debug=0;
$main::dryrun=0; # if set to 1, skip writing to the database


$main::local_hostname = `hostname`; # Override here - used for populating ds_host in wayf_access_record.
chomp $main::local_hostname;

# Initialaze connection to FR database
# set the the connection parameters either here or load them from dbconfig.pm
$main::database = '';
$main::hostname = '';
$main::user = '';
$main::password = '';
%main::ignore_users = ();

# load the config from the directory we are invoked from
# the c
my $base_dir = `dirname $0`;
chomp $base_dir;
do ($base_dir ne ""?$base_dir:".") . "/dbconfig.pm";

while ($ARGV[0] =~ /^--/ ) {
  if ($ARGV[0] eq "--verbose") { $main::verbose++; }
  elsif ($ARGV[0] eq "--no-verbose") { $main::verbose=0; }
  elsif ($ARGV[0] eq "--debug") { $main::debug++; }
  elsif ($ARGV[0] eq "--no-debug") { $main::debug=0; }
  elsif ($ARGV[0] eq "--dry-run") { $main::dryrun=1; }
  elsif ($ARGV[0] eq "--no-dry-run") { $main::dryrun=0; }
  elsif ($ARGV[0] eq "--server-host") { $main::local_hostname=$ARGV[1]; shift; }
  else { die "Invalid option $ARGV[0]"; };
  
  shift;
}

my $dsn = "DBI:mysql:database=$main::database;host=$main::hostname;mysql_enable_utf8=1";
#$dsn = "DBI:mysql:database=$database;host=$hostname;port=$port";
my $dbh = DBI->connect($dsn, $main::user, $main::password, { RaiseError => 1, AutoCommit => 0 });

my $sth_sess = $dbh->prepare("insert into dfIrodsLogin (duLoginTime, duUsername, duIPAddress, duServerName) values (?, ?, ?, ?)");

my @month_names = ( "None", "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec", "None");
my $datetime_now = DateTime->from_epoch(epoch => time());

sub parseRodsDateTime() {
  my $rodsDateTime = shift @_;
  
  if ( $rodsDateTime !~ /^([A-Za-z]{3})\s+(\d+)\s+(\d+):(\d+):(\d+)$/ ) { return undef; };

  my ($month_str, $date_day, $time_hour, $time_minute, $time_second) = ( $1, $2, $3, $4, $5);

  my $date_month = 0;
  my $date_year = 0;
  for my $month_name (@month_names) {
      if ( $month_name eq $month_str ) {
          last;
      } else {
          $date_month++;
      };
  }
  if ( ($date_month < 1) || ($date_month > 12) ) { return undef; };

  if ( $date_month * 35 + $date_day <= $datetime_now->month() * 35 + $datetime_now->day) {
      $date_year = $datetime_now->year
  } else {
      $date_year = $datetime_now->year-1;
  };

  return new DateTime( year => $date_year, month => $date_month, day => $date_day, hour => $time_hour, minute => $time_minute, second => $time_second, time_zone => "local");
};

# Parse rodsLog log files and extract information on user logins
# look for records like:

# Dec 15 17:17:32 pid:31096 NOTICE: Agent process 8445 started for puser=rods and cuser=vladimir.mencl from 132.181.39.14
# Dec 15 17:17:32 pid:8445 NOTICE: rsAuthCheck user rods
# Dec 15 17:17:32 pid:8445 NOTICE: rsAuthResponse set proxy authFlag to 5, client authFlag to 3, user:rods proxy:rods client:vladimir.mencl

my %rods_agent_launch=();
my $agent_pid;

while (<>) {
    if ( /^(.{15}) pid:\d+ NOTICE: Agent process (\d+) started for puser=[^ \n]+ and cuser=([^ \n]+) from ([0-9\.]+)$/ ) {
        %rods_agent_launch=();
        $rods_agent_launch{"timestamp"} = &parseRodsDateTime($1);
        $rods_agent_launch{"agent_pid"} = $2;
        $rods_agent_launch{"username"} = $3;
        $rods_agent_launch{"ip_address"} = $4;
        if ($main::debug) {
		print "Agent launch session found:\n";
		foreach my $key (keys(%rods_agent_launch)) {
		    print "Key: \"$key\" value: \"$rods_agent_launch{$key}\"\n";
		};
        };
    } elsif ( /^(.{15}) pid:(\d+) NOTICE: rsAuthResponse set proxy authFlag to.* client:([^ \n]+)$/ ) {
	# Collect the information to record into rodsLog hash (with key names matching the database schema)
	my %rods_session=();
        $rods_session{"duLoginTime"} = &parseRodsDateTime($1);
        $rods_session{"duUsername"} = $3;
        
        # if we do have an rods_agent_launch session that matches this information, use the IP address from there.
        $agent_pid = $2;
        if ( $agent_pid && exists($rods_agent_launch{"agent_pid"}) && 
             ( $agent_pid == $rods_agent_launch{"agent_pid"} ) && 
             ( $rods_session{"duUsername"} eq $rods_agent_launch{"username"} ) && 
             ( abs($rods_session{"duLoginTime"}->subtract_datetime_absolute($rods_agent_launch{"timestamp"})->seconds) < 5) ) {
          $rods_session{"duIPAddress"} = $rods_agent_launch{"ip_address"};
        } else {
          $rods_session{"duIPAddress"} = "";
        };
	$rods_session{"duServerName"} = $main::local_hostname;

        ### $rods_session{"date_created"} = DateTime->from_epoch( epoch => $rods_session{"date_created"} );
	# Timestamp MySQL expects: "value to be in the ODBC standard SQL_DATETIME format, which is ’YYYY-MM-DD HH:MM:SS’."
	# And it very happily accepts the format returned by this function: 2011-08-19T14:02:17

	# Timezone: store the date as a local time (not UTC) in MySQL
	### $rods_session{"date_created"}->set_time_zone("local");
        ### (already done by parseRodsDateTime)


	# timestamp: ??? parse apache stamp?
	# request type: ??? DS/WAYF based on whether it's SAML1 SSO-request or SAML2 SAMLDS ?
	# source: parse user IP?

	# Do we have sufficient information about the session
	if (exists($rods_session{"duUsername"}) && $rods_session{"duUsername"} && !exists($main::ignore_users{$rods_session{"duUsername"}}) ) {
            ###Should we be excluding rods/QuickShare/anonymous ???
            ###&& ( $rods_session{"duUsername"} ne "QuickShare" ) && ( $rods_session{"duUsername"} ne "rods" ) && ( $rods_session{"duUsername"} ne "anonymous" ) 
	    # insert the session into the database now

	    if ($main::verbose >= 1) {
		printf "Complete session found, %s into database: duLoginTime=\"%s\", duUsername=\"%s\", duIPAddress=%s, duServerName=\"%s\"\n",
                    ( $main::dryrun ? "pretending to insert" : "inserting" ),
		    $rods_session{"duLoginTime"}, $rods_session{"duUsername"}, $rods_session{"duIPAddress"}, $rods_session{"duServerName"};
	    };

	    # based on example:
	    #
	    # insert into wayf_access_record (date_created, ds_host, idpid, request_type, robot, source, spid) 
	    #     values (curdate(), 'ds.aaf.edu.au', 1, 'DS', false, 'bradley-machine.at.home.com', 22);
            if (!$main::dryrun) {
	        $sth_sess->execute($rods_session{"duLoginTime"}, $rods_session{"duUsername"}, 
                        $rods_session{"duIPAddress"}, $rods_session{"duServerName"});
            };
	} else {
	    if ($main::verbose >= 2) {
		print "Incomplete or filtered out session found:\n";
		foreach my $key (keys(%rods_session)) {
		    print "Key: \"$key\" value: \"$rods_session{$key}\"\n";
		};
	    };
	}

    }; # if (/log pattern/)
} # while (<>)

$dbh->commit;
$dbh->disconnect;

