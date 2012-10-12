<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">

<?
include '../config.php';

?>

<title><?=$df_title?> - Setting your password</title>
<link href="../bg_style.css" rel="stylesheet" type="text/css" />

</head>
<body>

<table width="100%">
<tbody><tr><th align="LEFT"><img id="logo" src="/images/lg_BeSTGRID-DataFabric.gif" alt="BeSTGRID logo"></th></tr></tbody>
</table>

<?php

# CONFIG
include 'config.php';

$st_name = 'shared-token';
$cn_name = 'cn';
$idp_name = 'Shib-Identity-Provider';

$dfUrl = "http://$_SERVER[SERVER_NAME]/";

# END-CONFIG

if (!isset($_SERVER["HTTPS"]) || ($_SERVER["HTTPS"]!="on")) {
  die("This URL MUST be accessed over HTTPS");
};

if (!isset($_SERVER[$st_name])) {
  die("Shared token attribute not defined (user likely not logged in)");
};


# get the data from the current session
$user_data = array();
$duSharedToken = $_SERVER[$st_name];
$user_data["duSharedToken"] = $_SERVER[$st_name];
if (isset($_SERVER[$cn_name])) { $user_data["duCN"]=$_SERVER[$cn_name]; };
if (isset($_SERVER[$idp_name])) { $user_data["duIdP"]=$_SERVER[$idp_name]; };

print "<H1>Welcome $user_data[duCN]</H1>\n<P>Your sharedToken is $duSharedToken\n";

$retval = 0;
$output = array();
$lastline = exec("$iquest \"%s\" \"select USER_NAME where  USER_INFO like '<ST>" . escapeshellcmd($duSharedToken) . "</ST>'\"", $output, $retval);

if ( $retval != 0 || count($output) != 1 || ! ($duUserName = $output[0]) ) { 
   #{ die("could not invoke iquest (Error code: $retval)"); };
   print "No DataFabric account found, please register first as a DataFabric user by going to $dfUrl and logging in with your institutional login\n";
} else {
   //phpinfo();

   print "<P>Your DataFabric account name is $duUserName\n";
   $err_msg = "";
 
   if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {
       if ($_POST["DFPassword1"] && $_POST["DFPassword2"] && ($_POST["DFPassword1"] == $_POST["DFPassword2"]) && (strlen($_POST["DFPassword1"]) >= $min_password_length) ) {
	   // Log to $log_file
	   $log_r = fopen($log_file, "a");
           $log_ok = true;
	   if ($log_r) { 
	       $write_res = fwrite($log_r, strftime("%c") . " Setting password for: username=$duUserName SharedToken=$duSharedToken\n"); 
	       $close_res = fclose($log_r);
	       if (!$write_res || !$close_res) { $log_ok = false; };
	   } else { $log_ok = false; };
	       if (! $log_ok) {
		   $err_msg = "Unfortunately, there has been an internal error: unable to log.";
	       } else {
	       $new_password = $_POST["DFPassword1"];
	       $output = array();
	       $retval = 0;
	       exec("$iadmin moduser $duUserName password " . escapeshellarg($new_password), $output, $retval);
	       if ($retval == 0 ) { 
		   print "<P><STRONG>Your password has been succesfully set.</STRONG>\n";
	       } else {
		   $err_msg = "Unfortunately, there was an error setting your password (error code $retval).  Your password was not changed.  You may try again";
	       };
	   };
       } else {
           if (!$_POST["DFPassword1"] || !$_POST["DFPassword2"]) { 
               $err_msg = "You must enter the same password into both fields";
           } elseif ($_POST["DFPassword1"] != $_POST["DFPassword2"]) {
               $err_msg = "The passwords did not match";
           } elseif (strlen($_POST["DFPassword1"]) < $min_password_length) {
               $err_msg = "The password was too short (must be at least $min_password_length characters long)";
           } else {
               $err_msg = "There was an error receiving your password";
           };
       };
   };

   if ($err_msg) print "<P><STRONG>Error:</STRONG> $err_msg\n";

   if ( $_SERVER["REQUEST_METHOD"] <> "POST" || $err_msg ) {
     print "<P>You may set a new password for your account by entering it here (retyping the same password twice):\n";
     print '<FORM METHOD="POST" action="'.$_SERVER['REQUEST_URI'].'" name="DataFabricSetPassword"><p>' . "\n";
     print '<INPUT TYPE="password" name="DFPassword1" size="20" value="" maxlength="20" ><p>' . "\n";
     print '<INPUT TYPE="password" name="DFPassword2" size="20" value="" maxlength="20" ><p>' . "\n";
     print '<INPUT NAME="Submit" TYPE="SUBMIT" VALUE="Submit" ><p>' . "\n";
     print '</FORM><p>' . "\n";
   }

}
 
?>
</body>
</html>
