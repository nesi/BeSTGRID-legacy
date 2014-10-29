<?php

# CONFIG
include 'config.php';
//$db_name = 'dfUsers';
//$db_table = 'dfUser';
//$db_server_port = 'localhost';
//$db_user = 'DB-USER';
//$db_password = 'DB-PASSWORD';

//$notify_to = "help@bestgrid.org";
//$notify_from = "BeSTGRID DataFabric <no-reply@$_SERVER[SERVER_NAME]";
//$notify_subject_prefix = "BeSTGRID DataFabric";

$st_name = 'auEduPersonSharedToken';
$cn_name = 'cn';
$o_name = 'o';
$affiliation_name = 'unscoped-affiliation';
$email_name = 'mail';
$idp_name = 'Shib-Identity-Provider';

# END-CONFIG

print "<PRE>\n";

if (isset($_SERVER["HTTPS"]) && ($_SERVER["HTTPS"]=="on")) {
  die("This URL MUST NOT be accessed over HTTPS");
};

if (!isset($_SERVER[$st_name])) {
  die("Shared token attribute not defined (user likely not logged in)");
};


# get the data from the current session
$user_data = array();
$user_data["duSharedToken"] = $_SERVER[$st_name];
if (isset($_SERVER[$cn_name])) { $user_data["duCN"]=$_SERVER[$cn_name]; };
if (isset($_SERVER[$email_name])) { $user_data["duEmail"]=$_SERVER[$email_name]; };
if (isset($_SERVER[$idp_name])) { $user_data["duIdP"]=$_SERVER[$idp_name]; };
if (isset($_SERVER[$o_name])) { $user_data["duOrgName"]=$_SERVER[$o_name]; };
if (isset($_SERVER[$affiliation_name])) { $user_data["duAffiliation"]=$_SERVER[$affiliation_name]; };
if (isset($_REQUEST["username"]) && ($_REQUEST["username"] != "anonymous")) { $user_data["duUsername"]=$_REQUEST["username"]; };


$link = mysql_connect($db_server_port, $db_user, $db_password);
if (!$link) {
    die('Could not connect: ' . mysql_error());
}
echo "Connected successfully\n";

// make foo the current db
$db_selected = mysql_select_db($db_name, $link);
if (!$db_selected) {
    die ('Can\'t use $db_name : ' . mysql_error());
}
echo "Selected $db_name\n";

$query = sprintf("SELECT * FROM $db_table WHERE duSharedToken='%s'",
    mysql_real_escape_string($_SERVER[$st_name]));


// Perform Query
$result = mysql_query($query);

$num_rows = mysql_num_rows($result);

print "num_rows=$num_rows\n";
if ($num_rows >1) {
   die("Error: more than one row ($num_rows) returned from query");
}

if ($num_rows == 0) {
   mysql_free_result($result);

   # new user - register the user
   # insert into database:
   $fields = "duFirstAccess, duLastAccess";
   $values = "NOW(), NOW()";
   foreach ($user_data as $key => $value) {
     $fields = "$fields, $key";
     $values = "$values, '" . mysql_real_escape_string($value) . "'";
   };

   $query_insert = "INSERT INTO $db_table ($fields) VALUES ($values)";
   print "Invoking $query_insert\n";

   // Perform Query
   $result = mysql_query($query_insert);
   if ($result) {
       print "Successful (" . mysql_affected_rows() . " rows)\n";
   } else {
       print "Failed: " . mysql_error();
   };

   # send out email
   $message = $notify_subject_prefix . ": New User has registered\n\n" 
            . "User data:\n";
   foreach ($user_data as $key => $value) { $message = "$message$key: $value\n"; };
   mail($notify_to, $notify_subject_prefix . ": New User has registered", $message, "From: $notify_from");

} else {
   # existing user: retrieve the info:
   $row = mysql_fetch_assoc($result);
   if (!$row) die ("mysql_fetch_assoc failed");
   mysql_free_result($result);

   # sort multiple eduPersonAffiliation values before comparing them with the stored value
   if (isset($user_data["duAffiliation"]) && $user_data["duAffiliation"]) {
      $values = explode(";",$user_data["duAffiliation"]);
      sort($values);
      $user_data["duAffiliation"] = implode(";",$values);
   };
   if (isset($row["duAffiliation"]) && $row["duAffiliation"]) {
      $values = explode(";",$row["duAffiliation"]);
      sort($values);
      $row["duAffiliation"] = implode(";",$values);
   };

   $update_fields = array();
   foreach ($user_data as $key => $value) {
     print "$key: new value: \"$value\" old value: \"". $row[$key] . "\"\n";
     if (!isset($row[$key]) || !$row[$key] || ($row[$key] != $value)) {
         array_push($update_fields, $key);
     };
   };
   
   if (count($update_fields) > 0) {
       #UPDATE record
       $field_update = "duLastAccess=NOW()";
       foreach ($update_fields as $key) {
           $field_update = "$field_update, $key='" . mysql_real_escape_string($user_data[$key]) . "'";
       };
       $update_query="UPDATE $db_table SET $field_update WHERE duSharedToken='". mysql_real_escape_string($user_data["duSharedToken"]) . "'";
       print "Updating user data: $update_query\n";
       $result = mysql_query($update_query);
       if ($result) {
	   print "Successfully updated user data timestamp (" . mysql_affected_rows() . " rows)\n";
       } else {
	   print "Failed to update last access timestamp: " . mysql_error();
       };

       #TODO email update
       $message = $notify_subject_prefix . ": User registration data has been updated\n\n" 
		. "Updated data:\n";
       
       foreach ($update_fields as $key) { $message = "$message$key: $user_data[$key] (was: " . ( $row[$key] ? $row[$key] : "NULL" ) . ")\n"; };
       $message = "$message\nCurrent user data:\n";
       foreach ($user_data as $key => $value) { $message = "$message$key: $value\n"; };
       mail($notify_to, $notify_subject_prefix . ": User registration data has been updated", $message, "From: $notify_from");
   } else {
       # Update last access timestamp
       # UPDATE table_name SET field1=new-value1, field2=new-value2 [WHERE Clause]
       $update_query="UPDATE $db_table SET duLastAccess=NOW() WHERE duSharedToken='". mysql_real_escape_string($user_data["duSharedToken"]) . "'";
       print "Updating last access timestamp: $update_query\n";
       $result = mysql_query($update_query);
       if ($result) {
	   print "Successfully updated last access timestamp (" . mysql_affected_rows() . " rows)\n";
       } else {
	   print "Failed to update last access timestamp: " . mysql_error();
       };
   };
};

// regardless of whether we were creating the user, updating the data or only
// updating last login time, record the session now
$log_session_query = "INSERT INTO dfUserLogin (duSharedToken, duLoginTime, duIPAddress, duUserAgent) VALUES ( '" .
    mysql_real_escape_string($user_data["duSharedToken"]) . "', NOW(), '" .
    mysql_real_escape_string($_SERVER["REMOTE_ADDR"]) . "', '" .
    mysql_real_escape_string($_SERVER["HTTP_USER_AGENT"]) . "');";
print "Recording session: $log_session_query\n";
$result = mysql_query($log_session_query);
if ($result) {
   print "Successfully recorded session (" . mysql_affected_rows() . " rows)\n";
} else {
   print "Failed to record session: " . mysql_error();
};


mysql_close($link);
print "\n</PRE>\n";
?>

