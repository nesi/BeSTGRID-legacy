# database config for parseUserLogins.pl

# initialaze connection to DF tracking database
$main::database = "dfUsers";
$main::hostname = "localhost";
$main::user = "";
$main::password = "";

%main::ignore_users = ( "rods" => 1, "rodsBoot" => 1 );

