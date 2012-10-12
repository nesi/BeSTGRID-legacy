<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<?

# CONFIG
include 'config.php';

?>
<title><?=$df_title?> - Changing your password</title>
<link href="/bg_style.css" rel="stylesheet" type="text/css" />
</head>
<body>
<table width="100%">
<tbody><tr><th align="LEFT"><img id="logo" src="/images/lg_BeSTGRID-DataFabric.gif" alt="BeSTGRID logo"></th></tr></tbody>
</table>

<?php

$idp_name = 'Shib-Identity-Provider';

$dfUrl = "http://$_SERVER[SERVER_NAME]/";

# END-CONFIG

if (!isset($_SERVER["HTTPS"]) || ($_SERVER["HTTPS"]!="on")) {
  die("This URL MUST be accessed over HTTPS");
};

function log_message($message) {
  require 'config.php';

  // Log to $log_file
  $log_r = fopen($log_file, "a");
  $log_ok = true;
  if ($log_r) { 
      $write_res = fwrite($log_r, strftime("%c") . " $message\n"); 
      $close_res = fclose($log_r);
      if (!$write_res || !$close_res) { $log_ok = false; };
  } else { 
      $log_ok = false; 
  };
  return $log_ok;
}


   print "<H1>$df_title</H1>\n";

   $err_msg = "";
   if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {
       // check all input data
       
       if (!isset($_POST["DFUsername"]) || !$_POST["DFUsername"]) { $err_msg = "Username not specified"; }
       elseif (!isset($_POST["DFCurPassword"])|| !$_POST["DFCurPassword"]) { $err_msg = "Current password not specified"; }
       elseif (!isset($_POST["DFNewPassword1"]) || !isset($_POST["DFNewPassword2"]) || 
               !$_POST["DFNewPassword1"] || !$_POST["DFNewPassword2"] ) { 
               $err_msg = "You must enter the new password into both fields";
       } elseif ($_POST["DFNewPassword1"] != $_POST["DFNewPassword2"]) {
               $err_msg = "The two entries of the new password did not match";
       } elseif ($_POST["DFNewPassword1"] == $_POST["DFCurPassword"]) {
               $err_msg = "The old password and the new password are the same";
       } elseif (strlen($_POST["DFNewPassword1"]) < $min_password_length) {
	   $err_msg = "The password was too short (must be at least $min_password_length characters long)";
       } else {
           // all checks passed, let us change the password now
	   // copy _POST data into local variables, trim username BUT NOT passwords
	   $dfUserName = trim($_POST["DFUsername"]);
	   $dfCurPassword = $_POST["DFCurPassword"];
	   $dfNewPassword1 = $_POST["DFNewPassword1"];
	   $dfNewPassword2 = $_POST["DFNewPassword2"];

           $log_ok = log_message(" Attempting to change password for: username=$dfUserName based on a password login");
           if ($log_ok) {
	       $output = array();
	       $retval = 0;
               $authFileName = tempnam("/tmp","irodstmp");
               
               // now exec ipasswd, feed it the old+new passwords on stdin
               // REF: http://nz.php.net/manual/en/function.proc-open.php
	       $descriptorspec = array(
		  0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
		  1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
		  2 => array("pipe", "w")   // and so is stderr
	       );


	       $cwd = NULL; // leave to default
	       $env = array('irodsHost' => $irodsHost,
	                    'irodsPort' => $irodsPort,
			    'irodsZone' => $irodsZone,
			    'irodsUserName' => $dfUserName,
			    'irodsAuthFileName' => $authFileName
               );

	       $process = proc_open($ipasswd, $descriptorspec, $pipes, $cwd, $env);
	       $p_stdout = $p_stderr = "";
	       $pw_ok = true;

	       if (is_resource($process)) {
		   // $pipes now looks like this:
		   // 0 => writeable handle connected to child stdin
		   // 1 => readable handle connected to child stdout
		   // Any error output will be appended to /tmp/error-output.txt

		   fwrite($pipes[0], "$dfCurPassword\n$dfNewPassword1\n$dfNewPassword2\n");
		   fclose($pipes[0]);

		   $p_stdout = str_replace("\n",";",stream_get_contents($pipes[1]));
		   $p_stderr = str_replace("\n",";",stream_get_contents($pipes[2]));
		   fclose($pipes[1]);
		   fclose($pipes[2]);

		   // It is important that you close any pipes before calling
		   // proc_close in order to avoid a deadlock
		   $retval = proc_close($process);
		   if ($retval != 0) {
		       $pw_ok = false;
		       $err_msg = "Password change failed. You have likely entered an incorrect username/password combination.";
		   };
	           # regardless of the outcome, clean up
		   if (file_exists($authFileName)) {
		       unlink($authFileName);
		   };
	       } else { 
		   $pw_ok = false;
		   $err_msg = "Password change failed: could not launch ipasswd";
	       };


	       if ($pw_ok) {
		   print "<P><STRONG>Your password has been succesfully set.</STRONG>\n";
	           log_message("Password change for user $dfUserName successful");
	       } else {
	           log_message("Password change for user $dfUserName failed. Exitcode: $retval err_msg:\"$err_msg\" ipasswd stdout:\"$p_stdout\" stderr:\"$p_stderr\"");
	       };
	   } else {
	     $err_msg = "Unfortunately, there has been an internal error: unable to log.";
	   };
       };
   };

   if ($err_msg) print "<P><STRONG>Error:</STRONG> $err_msg\n";
 

   if ( $_SERVER["REQUEST_METHOD"] <> "POST" || $err_msg ) {
      print "<P>You may change your $df_title password here.<P>Please enter your current details and the new password (retyping the same password twice):\n<P>\n";
      print '<FORM METHOD="POST" action="'.$_SERVER['REQUEST_URI'].'" name="DataFabricChangePassword"><p>' . "\n";
      print "<TABLE><TBODY>\n";
      print '<TR><TD>Username:</TD><TD><INPUT TYPE="text" name="DFUsername" size="20" value="" maxlength="20" ></TD></TR>' . "\n";
      print '<TR><TD>Current password:</TD><TD><INPUT TYPE="password" name="DFCurPassword" size="20" value="" maxlength="20" ></TD></TR>' . "\n";
      print '<TR><TD>New password:</TD><TD><INPUT TYPE="password" name="DFNewPassword1" size="20" value="" maxlength="20" ></TD></TR>' . "\n";
      print '<TR><TD>New password (again):</TD><TD><INPUT TYPE="password" name="DFNewPassword2" size="20" value="" maxlength="20" ></TD></TR>' . "\n";
      print '<TR><TD COLSPAN="2"><INPUT NAME="Submit" TYPE="SUBMIT" VALUE="Submit" ><p>' . "\n";
      print "</TBODY></TABLE>\n";
      print '</FORM><p>' . "\n";
   };

?>
</body>
</html>

